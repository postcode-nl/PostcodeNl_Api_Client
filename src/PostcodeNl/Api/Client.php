<?php
declare(strict_types=1);

namespace PostcodeNl\Api;


use PostcodeNl\Api\Exception\AuthenticationException;
use PostcodeNl\Api\Exception\BadRequestException;
use PostcodeNl\Api\Exception\CurlException;
use PostcodeNl\Api\Exception\CurlNotLoadedException;
use PostcodeNl\Api\Exception\ForbiddenException;
use PostcodeNl\Api\Exception\InvalidJsonResponseException;
use PostcodeNl\Api\Exception\InvalidPostcodeException;
use PostcodeNl\Api\Exception\InvalidSessionValueException;
use PostcodeNl\Api\Exception\NotFoundException;
use PostcodeNl\Api\Exception\ServerUnavailableException;
use PostcodeNl\Api\Exception\TooManyRequestsException;
use PostcodeNl\Api\Exception\UnexpectedException;

class Client
{
	public const SESSION_HEADER_KEY = 'X-Autocomplete-Session';
	public const SESSION_HEADER_VALUE_VALIDATION = '/^[a-z\d\-_.]{8,64}$/i';

	protected const SERVER_URL = 'https://api.postcode.eu/';
	protected const VERSION = '1.0';

	/** @var string The Postcode.nl API key, required for all requests. Provided when registering an account. */
	protected $_key;
	/** @var string The Postcode.nl API secret, required for all requests */
	protected $_secret;
	/** @var string A platform identifier, a short description of the platform using the API client. */
	protected $_platform;
	/** @var resource */
	protected $_curlHandle;
	/** @var array Response headers received in the most recent API call. */
	protected $_mostRecentResponseHeaders = [];


	/**
	 * Client constructor.
	 * @param string $key The Postcode.nl API key, provided when registering an account.
	 * @param string $secret The Postcode.nl API secret, provided when registering an account.
	 * @param string $platform A platform identifier, a short description of the platform using the API client.
	 */
	public function __construct(string $key, string $secret, string $platform)
	{
		$this->_key = $key;
		$this->_secret = $secret;
		$this->_platform = $platform;

		if (!extension_loaded('curl'))
		{
			throw new CurlNotLoadedException('Cannot use Postcode.nl International Autocomplete client, the server needs to have the PHP `cURL` extension installed.');
		}

		$this->_curlHandle = curl_init();
		curl_setopt($this->_curlHandle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->_curlHandle, CURLOPT_CONNECTTIMEOUT, 2);
		curl_setopt($this->_curlHandle, CURLOPT_TIMEOUT, 5);
		curl_setopt($this->_curlHandle, CURLOPT_USERAGENT, $this->_getUserAgent());

		if (isset($_SERVER['HTTP_REFERER']))
		{
			curl_setopt($this->_curlHandle, CURLOPT_REFERER, $_SERVER['HTTP_REFERER']);
		}
		curl_setopt($this->_curlHandle, CURLOPT_HEADERFUNCTION, function($curl, string $header) {
			$length = strlen($header);

			$headerParts = explode(':', $header, 2);
			// Ignore invalid headers
			if (count($headerParts) < 2)
			{
				return $length;
			}
			[$headerName, $headerValue] = $headerParts;
			$this->_mostRecentResponseHeaders[strtolower(trim($headerName))][] = trim($headerValue);

			return $length;
		});
	}

	/**
	 * @see https://developer.postcode.eu/documentation/international/v1/Autocomplete/autocomplete
	 */
	public function internationalAutocomplete(string $context, string $term, string $session, string $language = null): array
	{
		$this->_validateSessionHeader($session);

		$params = [$context, $term];
		if (isset($language))
		{
			$params[] = $language;
		}

		$params = array_map('rawurlencode', $params);

		return $this->_performApiCall('international/v1/autocomplete/' . implode('/', $params), $session);
	}

	/**
	 * @see https://developer.postcode.eu/documentation/international/v1/Autocomplete/getDetails
	 */
	public function internationalGetDetails(string $context, string $session): array
	{
		$this->_validateSessionHeader($session);

		return $this->_performApiCall('international/v1/address/' . rawurlencode($context), $session);
	}

	/**
	 * @see https://developer.postcode.eu/documentation/international/v1/Autocomplete/getSupportedCountries
	 */
	public function internationalGetSupportedCountries(): array
	{
		return $this->_performApiCall('international/v1/supported-countries', null);
	}

	/**
	 * @see https://developer.postcode.eu/documentation/nl/v1/Address/viewByPostcode
	 */
	public function dutchAddressByPostcode(string $postcode, int $houseNumber, ?string $houseNumberAddition = null): array
	{
		// Validate postcode format
		$postcode = trim($postcode);
		if (!$this->isValidDutchPostcodeFormat($postcode))
		{
			throw new InvalidPostcodeException(sprintf('Postcode `%s` has an invalid format, it should be in the format 1234AB.', $postcode));
		}

		// Use the regular validation function
		$urlParts = [
			'nl/v1/addresses/postcode',
			rawurlencode($postcode),
			$houseNumber,
		];
		if ($houseNumberAddition !== null)
		{
			$urlParts[] = rawurlencode($houseNumberAddition);
		}
		return $this->_performApiCall(implode('/', $urlParts), null);
	}

	/**
	 * @see https://developer.postcode.eu/documentation/nl/v1/Address/matchExact
	 */
	public function dutchAddressExactMatch(string $city, string $street, int $houseNumber, string $houseNumberAddition = ''): array
	{
		$urlParts = [
			'nl/v1/addresses/exact',
			rawurlencode($city),
			rawurlencode($street),
			$houseNumber,
			rawurlencode($houseNumberAddition),
		];

		return $this->_performApiCall(implode('/', $urlParts), null);
	}

	/**
	 * @see https://developer.postcode.eu/documentation/nl/v1/Address/viewByRd
	 */
	public function dutchAddressRD(float $rdX, float $rdY): array
	{
		$urlParts = [
			'nl/v1/addresses/rd',
			rawurlencode((string) $rdX),
			rawurlencode((string) $rdY),
		];

		return $this->_performApiCall(implode('/', $urlParts), null);
	}

	/**
	 * @see https://developer.postcode.eu/documentation/nl/v1/Address/viewByLatLon
	 */
	public function dutchAddressLatLon(float $latitude, float $longitude): array
	{
		$urlParts = [
			'nl/v1/addresses/latlon',
			rawurlencode((string) $latitude),
			rawurlencode((string) $longitude),
		];

		return $this->_performApiCall(implode('/', $urlParts), null);
	}

	/**
	 * @see https://developer.postcode.eu/documentation/nl/v1/Address/viewByBagNumberDesignationId
	 */
	public function dutchAddressBagNumberDesignation(string $bagNumberDesignationId): array
	{
		$urlParts = [
			'nl/v1/addresses/bag/number-designation',
			rawurlencode($bagNumberDesignationId),
		];

		return $this->_performApiCall(implode('/', $urlParts), null);
	}

	/**
	 * @see https://developer.postcode.eu/documentation/nl/v1/Address/viewByBagAddressableObjectId
	 */
	public function dutchAddressBagAddressableObject(string $bagAddressableObjectId): array
	{
		$urlParts = [
			'nl/v1/addresses/bag/addressable-object',
			rawurlencode($bagAddressableObjectId),
		];

		return $this->_performApiCall(implode('/', $urlParts), null);
	}

	/**
	 * @see https://developer.postcode.eu/documentation/nl/v1/PostcodeRange/viewByPostcode
	 */
	public function dutchAddressPostcodeRanges(string $postcode): array
	{
		// Validate postcode format
		$postcode = trim($postcode);
		if (!$this->isValidDutchPostcodeFormat($postcode))
		{
			throw new InvalidPostcodeException(sprintf('Postcode `%s` has an invalid format, it should be in the format `1234AB`.', $postcode));
		}

		$urlParts = [
			'nl/v1/postcode-ranges/postcode',
			rawurlencode($postcode),
		];

		return $this->_performApiCall(implode('/', $urlParts), null);
	}

	/**
	 * @see https://developer.postcode.eu/documentation/international/v1/Validate/validate
	 */
	public function validate(string $country, ?string $postcode = null, ?string $locality = null, ?string $street = null, ?string $building = null, ?string $region = null, ?string $streetAndBuilding = null): array
	{
		$urlParts = [
			'international/v1/validate',
			rawurlencode($country)
		];
		$variables = [
			'postcode' => $postcode,
			'locality' => $locality,
			'street' => $street,
			'building' => $building,
			'region' => $region,
			'streetAndBuilding' => $streetAndBuilding,
		];
		$parameters = [];
		foreach ($variables as $key => $value)
		{
			if ($value === null)
			{
				continue;
			}
			$parameters[] = $key . '=' . rawurlencode($value);
		}

		return $this->_performApiCall(implode('/', $urlParts) . '?' . implode('&', $parameters), null);
	}

	/**
	 * @see https://developer.postcode.eu/documentation/international/v1/Validate/getCountry
	 */
	public function getCountry(string $country): array
	{
		$urlParts = [
			'international/v1/country',
			rawurlencode($country),
		];

		return $this->_performApiCall(implode('/', $urlParts), null);
	}

	/**
	 * @see https://developer.postcode.eu/documentation/reseller/v1/Reseller/createClientAccount
	 */
	public function createClientAccount(
		string $companyName,
		string $countryIso,
		string $vatNumber,
		string $contactEmail,
		int $subscriptionAmount,
		array $siteUrls,
		string $invoiceEmail,
		string $invoiceReference,
		string $invoiceAddressLine1,
		string $invoiceAddressLine2,
		string $invoiceAddressPostalCode,
		string $invoiceAddressLocality,
		string $invoiceAddressRegion,
		string $invoiceAddressCountryIso,
		?string $invoiceContactName = null,
		bool $isTest = false
	): array
	{
		$postData = [
			'companyName' => $companyName,
			'countryIso' => $countryIso,
			'vatNumber' => $vatNumber,
			'contactEmail' => $contactEmail,
			'subscriptionAmount' => $subscriptionAmount,
			'siteUrls' => $siteUrls,
			'invoiceEmail' => $invoiceEmail,
			'invoiceReference' => $invoiceReference,
			'invoiceAddressLine1' => $invoiceAddressLine1,
			'invoiceAddressLine2' => $invoiceAddressLine2,
			'invoiceAddressPostalCode' => $invoiceAddressPostalCode,
			'invoiceAddressLocality' => $invoiceAddressLocality,
			'invoiceAddressRegion' => $invoiceAddressRegion,
			'invoiceAddressCountryIso' => $invoiceAddressCountryIso,
		];

		if ($invoiceContactName !== null)
		{
			$postData['invoiceContactName'] = $invoiceContactName;
		}
		if ($isTest)
		{
			$postData['isTest'] = true;
		}

		return $this->_performPostApiCall('reseller/v1/client', $postData);
	}

	/**
	 * @see https://developer.postcode.eu/documentation/account/v1/Account/getInfo
	 */
	public function accountInfo(): array
	{
		return $this->_performApiCall('account/v1/info', null);
	}

	/**
	 * @return array The response headers from the most recent API call.
	 */
	public function getApiCallResponseHeaders(): array
	{
		return $this->_mostRecentResponseHeaders;
	}

	/**
	 * Validate if string has a correct Dutch postcode format. First digit cannot be zero.
	 *
	 * @param string $postcode
	 * @return bool
	 */
	public function isValidDutchPostcodeFormat(string $postcode): bool
	{
		return (bool) preg_match('~^[1-9]\d{3}\s?[a-zA-Z]{2}$~', $postcode);
	}

	public function __destruct()
	{
		if (isset($this->_curlHandle))
		{
			curl_close($this->_curlHandle);
		}
	}

	protected function _validateSessionHeader(string $session): void
	{
		if (preg_match(static::SESSION_HEADER_VALUE_VALIDATION, $session) === 0)
		{
			throw new InvalidSessionValueException(sprintf(
				'Session value `%s` does not conform to `%s`, please refer to the API documentation for further information.',
				$session,
				static::SESSION_HEADER_VALUE_VALIDATION
			));
		}
	}

	protected function _performApiCall(string $path, ?string $session): array
	{
		$url = static::SERVER_URL . $path;
		if ($session !== null)
		{
			curl_setopt($this->_curlHandle, CURLOPT_HTTPHEADER, [
				static::SESSION_HEADER_KEY . ': ' . $session,
			]);
		}
		curl_setopt($this->_curlHandle, CURLOPT_HTTPGET, true);

		return $this->_performCurlCall($url);
	}

	protected function _performPostApiCall(string $path, array $postData = []): array
	{
		$url = static::SERVER_URL . $path;
		curl_setopt($this->_curlHandle, CURLOPT_POST, true);
		curl_setopt($this->_curlHandle, CURLOPT_POSTFIELDS, http_build_query($postData));

		return $this->_performCurlCall($url);
	}

	protected function _performCurlCall(string $url): array
	{
		curl_setopt($this->_curlHandle, CURLOPT_URL, $url);
		curl_setopt($this->_curlHandle, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($this->_curlHandle, CURLOPT_USERPWD, $this->_key .':'. $this->_secret);

		$this->_mostRecentResponseHeaders = [];
		$response = curl_exec($this->_curlHandle);

		$responseStatusCode = curl_getinfo($this->_curlHandle, CURLINFO_RESPONSE_CODE);
		$curlError = curl_error($this->_curlHandle);
		$curlErrorNr = curl_errno($this->_curlHandle);
		if ($curlError !== '')
		{
			throw new CurlException(vsprintf('Connection error number `%s`: `%s`.', [$curlErrorNr, $curlError]));
		}

		// Parse the response as JSON, will be null if not parsable JSON.
		$jsonResponse = json_decode($response, true);
		switch ($responseStatusCode)
		{
			case 200:
				if (!is_array($jsonResponse))
				{
					throw new InvalidJsonResponseException('Invalid JSON response from the server for request: ' . $url);
				}

				return $jsonResponse;
			case 400:
				throw new BadRequestException(vsprintf('Server response code 400, bad request for `%s`.', [$url]));
			case 401:
				throw new AuthenticationException('Could not authenticate your request, please make sure your API credentials are correct.');
			case 403:
				throw new ForbiddenException(sprintf('API access not allowed: `%s`', $jsonResponse['exception']));
			case 404:
				throw new NotFoundException('The request was valid, but nothing could be found.');
			case 429:
				throw new TooManyRequestsException('Too many requests made, please slow down: ' . $response);
			case 503:
				throw new ServerUnavailableException('The international API server is currently not available: ' . $response);
			default:
				throw new UnexpectedException(vsprintf('Unexpected server response code `%s`.', [$responseStatusCode]));
		}
	}

	protected function _getUserAgent(): string
	{
		return sprintf(
			'%s %s/%s PHP/%s',
			$this->_platform,
			str_replace('\\', '_', static::class),
			static::VERSION,
			PHP_VERSION
		);
	}
}

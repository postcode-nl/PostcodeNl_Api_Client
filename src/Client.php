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

/**
 * Class Client
 *
 * @package PostcodeNl\Api
 */
class Client
{
    public const SESSION_HEADER_KEY = 'X-Autocomplete-Session';

    public const SESSION_HEADER_VALUE_VALIDATION = '/^[a-z\d\-_.]{8,64}$/i';

    protected const SERVER_URL = 'https://api.postcode.eu/';

    protected const VERSION = '1.0';

    /**
     * @var string The Postcode.nl API key, required for all requests. Provided when registering an account.
     */
    protected $key;

    /**
     * @var string The Postcode.nl API secret, required for all requests
     */
    protected $secret;

    /**
     * @var string A platform identifier, a short description of the platform using the API client.
     */
    protected $platform;

    /**
     * @var resource
     */
    protected $curlHandler;

    /**
     * @var array Response headers received in the most recent API call.
     */
    protected $mostRecentResponseHeaders = [];

    /**
     * Client constructor.
     *
     * @param string $key The Postcode.nl API key, provided when registering an account.
     * @param string $secret The Postcode.nl API secret, provided when registering an account.
     * @param string $platform A platform identifier, a short description of the platform using the API client.
     *
     * @throws CurlNotLoadedException
     */
    public function __construct(string $key, string $secret, string $platform)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->platform = $platform;

        if (!extension_loaded('curl')) {
            throw new CurlNotLoadedException(
                'Cannot use Postcode.nl International Autocomplete client,'
                . ' the server needs to have the PHP `cURL` extension installed.'
            );
        }

        $this->curlHandler = curl_init();
        curl_setopt_array(
            $this->curlHandler,
            [
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_USERAGENT => $this->getUserAgent(),
                CURLOPT_HEADERFUNCTION => [$this, 'curlHeader']
            ]
        );

        if (isset($_SERVER['HTTP_REFERER'])) {
            curl_setopt($this->curlHandler, CURLOPT_REFERER, $_SERVER['HTTP_REFERER']);
        }
    }

    /**
     * @see https://api.postcode.nl/documentation/international/v1/Autocomplete/autocomplete
     *
     * @param string $context
     * @param string $term
     * @param string $session
     * @param string|null $language
     *
     * @return array
     * @throws AuthenticationException
     * @throws BadRequestException
     * @throws CurlException
     * @throws ForbiddenException
     * @throws InvalidJsonResponseException
     * @throws InvalidSessionValueException
     * @throws NotFoundException
     * @throws ServerUnavailableException
     * @throws TooManyRequestsException
     * @throws UnexpectedException
     */
    public function internationalAutocomplete(
        string $context,
        string $term,
        string $session,
        string $language = null
    ): array {
        $this->validateSessionHeader($session);

        $params = [$context, $term];
        if (isset($language)) {
            $params[] = $language;
        }

        $params = array_map('rawurlencode', $params);

        return $this->performApiCall('international/v1/autocomplete/' . implode('/', $params), $session);
    }

    /**
     * @see https://api.postcode.nl/documentation/international/v1/Autocomplete/getDetails
     *
     * @param string $context
     * @param string $session
     *
     * @return array
     * @throws AuthenticationException
     * @throws BadRequestException
     * @throws CurlException
     * @throws ForbiddenException
     * @throws InvalidJsonResponseException
     * @throws InvalidSessionValueException
     * @throws NotFoundException
     * @throws ServerUnavailableException
     * @throws TooManyRequestsException
     * @throws UnexpectedException
     */
    public function internationalGetDetails(string $context, string $session): array
    {
        $this->validateSessionHeader($session);

        return $this->performApiCall('international/v1/address/' . rawurlencode($context), $session);
    }

    /**
     * @see https://api.postcode.nl/documentation/international/v1/Autocomplete/getSupportedCountries
     * @return array
     * @throws AuthenticationException
     * @throws BadRequestException
     * @throws CurlException
     * @throws ForbiddenException
     * @throws InvalidJsonResponseException
     * @throws NotFoundException
     * @throws ServerUnavailableException
     * @throws TooManyRequestsException
     * @throws UnexpectedException
     */
    public function internationalGetSupportedCountries(): array
    {
        return $this->performApiCall('international/v1/supported-countries', null);
    }

    /**
     * @see https://api.postcode.nl/documentation/nl/v1/Address/viewByPostcode
     *
     * @param string $postcode
     * @param int $houseNumber
     * @param string|null $houseNumberAddition
     *
     * @return array
     * @throws AuthenticationException
     * @throws BadRequestException
     * @throws CurlException
     * @throws ForbiddenException
     * @throws InvalidJsonResponseException
     * @throws InvalidPostcodeException
     * @throws NotFoundException
     * @throws ServerUnavailableException
     * @throws TooManyRequestsException
     * @throws UnexpectedException
     */
    public function dutchAddressByPostcode(
        string $postcode,
        int $houseNumber,
        ?string $houseNumberAddition = null
    ): array {
        // Validate postcode format
        $postcode = trim($postcode);
        if (!$this->isValidDutchPostcodeFormat($postcode)) {
            throw new InvalidPostcodeException(
                sprintf('Postcode `%s` has an invalid format, it should be in the format 1234AB.', $postcode)
            );
        }

        // Use the regular validation function
        $urlParts = [
            'nl/v1/addresses/postcode',
            rawurlencode($postcode),
            $houseNumber,
        ];
        if ($houseNumberAddition !== null) {
            $urlParts[] = rawurlencode($houseNumberAddition);
        }

        return $this->performApiCall(implode('/', $urlParts), null);
    }

    /**
     * @see https://api.postcode.nl/documentation/nl/v1/Address/matchExact
     *
     * @param string $city
     * @param string $street
     * @param int $houseNumber
     * @param string $houseNumberAddition
     *
     * @return array
     * @throws AuthenticationException
     * @throws BadRequestException
     * @throws CurlException
     * @throws ForbiddenException
     * @throws InvalidJsonResponseException
     * @throws NotFoundException
     * @throws ServerUnavailableException
     * @throws TooManyRequestsException
     * @throws UnexpectedException
     */
    public function dutchAddressExactMatch(
        string $city,
        string $street,
        int $houseNumber,
        string $houseNumberAddition = ''
    ): array {
        $urlParts = [
            'nl/v1/addresses/exact',
            rawurlencode($city),
            rawurlencode($street),
            $houseNumber,
            rawurlencode($houseNumberAddition),
        ];

        return $this->performApiCall(implode('/', $urlParts), null);
    }

    /**
     * @see https://api.postcode.nl/documentation/nl/v1/Address/viewByRd
     *
     * @param float $rdX
     * @param float $rdY
     *
     * @return array
     * @throws AuthenticationException
     * @throws BadRequestException
     * @throws CurlException
     * @throws ForbiddenException
     * @throws InvalidJsonResponseException
     * @throws NotFoundException
     * @throws ServerUnavailableException
     * @throws TooManyRequestsException
     * @throws UnexpectedException
     */
    public function dutchAddressRD(float $rdX, float $rdY): array
    {
        $urlParts = [
            'nl/v1/addresses/rd',
            rawurlencode($rdX),
            rawurlencode($rdY),
        ];

        return $this->performApiCall(implode('/', $urlParts), null);
    }

    /**
     * @see https://api.postcode.nl/documentation/nl/v1/Address/viewByLatLon
     *
     * @param float $latitude
     * @param float $longitude
     *
     * @return array
     * @throws AuthenticationException
     * @throws BadRequestException
     * @throws CurlException
     * @throws ForbiddenException
     * @throws InvalidJsonResponseException
     * @throws NotFoundException
     * @throws ServerUnavailableException
     * @throws TooManyRequestsException
     * @throws UnexpectedException
     */
    public function dutchAddressLatLon(float $latitude, float $longitude): array
    {
        $urlParts = [
            'nl/v1/addresses/latlon',
            rawurlencode($latitude),
            rawurlencode($longitude),
        ];

        return $this->performApiCall(implode('/', $urlParts), null);
    }

    /**
     * @see https://api.postcode.nl/documentation/nl/v1/Address/viewByBagNumberDesignationId
     *
     * @param string $bagNumberDesignationId
     *
     * @return array
     * @throws AuthenticationException
     * @throws BadRequestException
     * @throws CurlException
     * @throws ForbiddenException
     * @throws InvalidJsonResponseException
     * @throws NotFoundException
     * @throws ServerUnavailableException
     * @throws TooManyRequestsException
     * @throws UnexpectedException
     */
    public function dutchAddressBagNumberDesignation(string $bagNumberDesignationId): array
    {
        $urlParts = [
            'nl/v1/addresses/bag/number-designation',
            rawurlencode($bagNumberDesignationId),
        ];

        return $this->performApiCall(implode('/', $urlParts), null);
    }

    /**
     * @see https://api.postcode.nl/documentation/nl/v1/Address/viewByBagAddressableObjectId
     *
     * @param string $bagAddressableObjectId
     *
     * @return array
     * @throws AuthenticationException
     * @throws BadRequestException
     * @throws CurlException
     * @throws ForbiddenException
     * @throws InvalidJsonResponseException
     * @throws NotFoundException
     * @throws ServerUnavailableException
     * @throws TooManyRequestsException
     * @throws UnexpectedException
     */
    public function dutchAddressBagAddressableObject(string $bagAddressableObjectId): array
    {
        $urlParts = [
            'nl/v1/addresses/bag/addressable-object',
            rawurlencode($bagAddressableObjectId),
        ];

        return $this->performApiCall(implode('/', $urlParts), null);
    }

    /**
     * @see https://api.postcode.nl/documentation/nl/v1/PostcodeRange/viewByPostcode
     *
     * @param string $postcode
     *
     * @return array
     * @throws AuthenticationException
     * @throws BadRequestException
     * @throws CurlException
     * @throws ForbiddenException
     * @throws InvalidJsonResponseException
     * @throws InvalidPostcodeException
     * @throws NotFoundException
     * @throws ServerUnavailableException
     * @throws TooManyRequestsException
     * @throws UnexpectedException
     */
    public function dutchAddressPostcodeRanges(string $postcode): array
    {
        // Validate postcode format
        $postcode = trim($postcode);
        if (!$this->isValidDutchPostcodeFormat($postcode)) {
            throw new InvalidPostcodeException(
                sprintf('Postcode `%s` has an invalid format, it should be in the format `1234AB`.', $postcode)
            );
        }

        $urlParts = [
            'nl/v1/postcode-ranges/postcode',
            rawurlencode($postcode),
        ];

        return $this->performApiCall(implode('/', $urlParts), null);
    }

    /**
     * @see https://api.postcode.nl/documentation/account/v1/Account/getInfo
     * @return array
     * @throws AuthenticationException
     * @throws BadRequestException
     * @throws CurlException
     * @throws ForbiddenException
     * @throws InvalidJsonResponseException
     * @throws NotFoundException
     * @throws ServerUnavailableException
     * @throws TooManyRequestsException
     * @throws UnexpectedException
     */
    public function accountInfo(): array
    {
        return $this->performApiCall('account/v1/info', null);
    }

    /**
     * @return array The response headers from the most recent API call.
     */
    public function getApiCallResponseHeaders(): array
    {
        return $this->mostRecentResponseHeaders;
    }

    /**
     * Validate if string has a correct Dutch postcode format. First digit cannot be zero.
     *
     * @param string $postcode
     *
     * @return bool
     */
    public function isValidDutchPostcodeFormat(string $postcode): bool
    {
        return 1 !== preg_match('/^[1-9]\d{3}\s?[a-zA-Z]{2}$/', $postcode);
    }

    /**
     * Closes curl connection.
     */
    public function __destruct()
    {
        curl_close($this->curlHandler);
    }

    /**
     * @param string $session
     *
     * @throws InvalidSessionValueException
     */
    protected function validateSessionHeader(string $session): void
    {
        if (1 !== preg_match(static::SESSION_HEADER_VALUE_VALIDATION, $session)) {
            throw new InvalidSessionValueException(sprintf(
                'Session value `%s` does not conform to `%s`,'
                . ' please refer to the API documentation for further information.',
                $session,
                static::SESSION_HEADER_VALUE_VALIDATION
            ));
        }
    }

    /**
     * @param string $path
     * @param string|null $session
     *
     * @return array
     * @throws AuthenticationException
     * @throws BadRequestException
     * @throws CurlException
     * @throws ForbiddenException
     * @throws InvalidJsonResponseException
     * @throws NotFoundException
     * @throws ServerUnavailableException
     * @throws TooManyRequestsException
     * @throws UnexpectedException
     */
    protected function performApiCall(string $path, ?string $session): array
    {
        $url = static::SERVER_URL . $path;

        curl_setopt_array(
            $this->curlHandler,
            [
                CURLOPT_URL => $url,
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => $this->key . ':' . $this->secret
            ]
        );

        if ($session !== null) {
            curl_setopt($this->curlHandler, CURLOPT_HTTPHEADER, [
                static::SESSION_HEADER_KEY . ': ' . $session,
            ]);
        }

        $this->mostRecentResponseHeaders = [];
        $response = curl_exec($this->curlHandler);

        $responseStatusCode = curl_getinfo($this->curlHandler, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($this->curlHandler);
        $curlErrorNr = curl_errno($this->curlHandler);
        if ($curlError !== '') {
            throw new CurlException(sprintf('Connection error number `%s`: `%s`.', $curlErrorNr, $curlError));
        }

        // Parse the response as JSON, will be null if not parsable JSON.
        $jsonResponse = json_decode($response, true);
        switch ($responseStatusCode) {
            case 200:
                if (!is_array($jsonResponse)) {
                    throw new InvalidJsonResponseException(
                        'Invalid JSON response from the server for request: ' . $url
                    );
                }

                return $jsonResponse;
            case 400:
                throw new BadRequestException(sprintf('Server response code 400, bad request for `%s`.', $url));
            case 401:
                throw new AuthenticationException(
                    'Could not authenticate your request, please make sure your API credentials are correct.'
                );
            case 403:
                throw new ForbiddenException(
                    'Your account currently has no access to the international API,'
                    . ' make sure you have an active subscription.'
                );
            case 404:
                throw new NotFoundException('The requested address could not be found.');
            case 429:
                throw new TooManyRequestsException('Too many requests made, please slow down: ' . $response);
            case 503:
                throw new ServerUnavailableException(
                    'The international API server is currently not available: ' . $response
                );
            default:
                throw new UnexpectedException(
                    sprintf('Unexpected server response code `%s`.', $responseStatusCode)
                );
        }
    }

    /**
     * @return string
     */
    protected function getUserAgent(): string
    {
        return sprintf(
            '%s %s/%s PHP/%s',
            $this->platform,
            str_replace('\\', '_', static::class),
            static::VERSION,
            PHP_VERSION
        );
    }

    /**
     * Callback for curl header.
     *
     * @param resource $curl
     * @param string $header
     *
     * @return int
     * @noinspection PhpUnusedParameterInspection
     */
    protected function curlHeader($curl, string $header): int
    {
        $length = strlen($header);

        $headerParts = explode(':', $header, 2);
        // Ignore invalid headers
        if (count($headerParts) < 2) {
            return $length;
        }
        [$headerName, $headerValue] = $headerParts;
        $this->mostRecentResponseHeaders[strtolower(trim($headerName))][] = trim($headerValue);

        return $length;
    }
}

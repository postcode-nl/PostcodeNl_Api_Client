<?php

define('API_KEY',    '** insert key here **');
define('API_SECRET', '** insert secret here **');
define('PLATFORM',   'example proxy');


// this will load exceptions when they occur
spl_autoload_register(function($class){
	if (0 === strpos($class, 'PostcodeNl\\'))
		require(__DIR__ .'/../src/'. str_replace('\\', '/', $class) .'.php');
});

header('Content-Type: application/json');

try
{
	if (!isset($_GET['p']))
		throw new Exception('Missing parameters');

	$parts = explode('/', $_GET['p']);

	if (count($parts) < 2)
		throw new Exception('Not enough parameters');

	$action = array_shift($parts);

	if (0 !== strpos($action, 'international') && 0 !== strpos($action, 'dutchAddress'))
		throw new Exception('This example only supports calls to international or dutchAddress methods');

	// Use the session header if it was specified already (the Postcode.nl js library will generate one automatically, for example)
	// Fall back to a place holder identifier. Don't use a fixed identifier for production environments, please read:
	// https://api.postcode.nl/documentation/international/v1/Autocomplete/autocomplete
	$sessionHeaderKey = 'HTTP_' . str_replace('-', '_', strtoupper(PostcodeNl\Api\Client::SESSION_HEADER_KEY));
	$sessionId = $_SERVER[$sessionHeaderKey] ?? 'MY_SESSION_ID';

	$client = new PostcodeNl\Api\Client(API_KEY, API_SECRET, PLATFORM);

	switch ($action)
	{
		case 'internationalAutocomplete':
			$response = $client->internationalAutocomplete($parts[0], $parts[1], $sessionId, $parts[2] ?? null);
			break;
		case 'internationalGetDetails':
			$response = $client->internationalGetDetails($parts[0], $sessionId);
			break;
		default:
			$response = call_user_func_array([$client, $action], $parts);
			break;
	}

	print json_encode($response);
}
catch (Exception $e)
{
	http_response_code(400);
	print json_encode(['Exception' => $e->getMessage()]);
}
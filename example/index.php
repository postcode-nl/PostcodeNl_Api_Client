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

	if ($action == 'internationalAutocomplete' || $action == 'internationalGetDetails')
		$parts []= 'MY_SESSION_ID';

	$client = new PostcodeNl\Api\Client(API_KEY, API_SECRET, PLATFORM);
	print json_encode(call_user_func_array([$client, $action], $parts));
}
catch (Exception $e)
{
	http_response_code(400);
	print json_encode(['Exception' => $e->getMessage()]);
}
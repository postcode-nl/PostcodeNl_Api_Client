<?php

define('API_KEY',    /* as received by mail after signing up */);
define('API_SECRET', /* as received by mail after signing up */);
define('PLATFORM', 'example proxy');


// this will load exceptions when they occur
spl_autoload_register(function($class){
	if (0 === strpos($class, 'PostcodeNl\\'))
		require(__DIR__ .'/../src/'. str_replace('\\', '/', $class) .'.php');
});

header('Content-Type: application/json');

try
{
	if (0 !== strpos($_GET['action'], 'international') && 0 !== strpos($_GET['action'], 'dutchAddress'))
		throw new Exception('This example only supports calls to international or dutchAddress methods');

	$client = new PostcodeNl\Api\Client(API_KEY, API_SECRET, PLATFORM);
	print json_encode(call_user_func_array([$client, $_GET['action']], $_GET['data'] ?? []));
}
catch (Exception $e)
{
	http_response_code(400);
	print json_encode(['Exception' => $e->getMessage()]);
}
<?php
define('API_KEY',    '** insert key here **');
define('API_SECRET', '** insert secret here **');
define('PLATFORM',   'example proxy');


// this will load exceptions when they occur
spl_autoload_register(function($class){
	if (0 === strpos($class, 'PostcodeNl\\'))
		require(__DIR__ .'/../src/'. str_replace('\\', '/', $class) .'.php');
});
$result = null;
if (isset($_GET['validate']))
{
	$client = new PostcodeNl\Api\Client(API_KEY, API_SECRET, PLATFORM);
	$country = $_GET['country'];
	// If it is 3 characters it is probably already an iso3 code
	if (strlen($country) === 3)
	{
		$countryIso = strtolower($country);
	}
	else
	{
		// Request an iso3 code for the country
		$result = $client->getCountry($country);
		$countryIso = strtolower($result['iso3']);
	}

	if (isset($countryIso))
	{
		 $result = $client->validate(
			 $countryIso,
			 $_GET['postcode'],
			 $_GET['locality'],
			 $_GET['street'],
			 $_GET['building'],
			 $_GET['region'],
			 $_GET['streetAndBuilding']
		 );
	}
}

?>
<!DOCTYPE html>
<html lang="nl-NL">
<head>
	<title>Validate example</title>
	<style>
		input[type=text]{
			width: 500px;
		}

		.validation-result {
			padding: 1em;
		}
	</style>
</head>
<body>
	<h3>Address validation</h3>
	<p>For more information see README.md</p>
	<form action="validate.php" method="GET">
		<label>Country <input type="text" name="country" placeholder="Country" value="<?php print($_GET['country'] ?? 'nld'); ?>"></label><br>
		<label>Postcode <input type="text" name="postcode" placeholder="Postcode" value="<?php print($_GET['postcode'] ?? '2012 ES'); ?>"></label><br>
		<label>Locality <input type="text" name="locality" placeholder="Locality" value="<?php print($_GET['locality'] ?? 'Haarlem'); ?>"></label><br>
		<label>Street <input type="text" name="street" placeholder="Street" value="<?php print($_GET['street'] ?? 'Julianastraat'); ?>"></label><br>
		<label>Building <input type="text" name="building" placeholder="Building" value="<?php print($_GET['building'] ?? '30'); ?>"></label><br>
		<label>Region <input type="text" name="region" placeholder="Region" value="<?php print($_GET['region'] ?? ''); ?>"></label><br>
		<label>Street and building <input type="text" name="streetAndBuilding" placeholder="Street and building" value="<?php print($_GET['streetAndBuilding'] ?? ''); ?>"></label><br>

		<input type="submit" value="Validate" name="validate">
	</form>

	<pre class="validation-result"><?php print($result === null ? '' : var_export($result, true)); ?></pre>
</body>
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
if (isset($_GET['createResellerAccount']))
{
	$client = new PostcodeNl\Api\Client(API_KEY, API_SECRET, PLATFORM);
	$result = $client->createClientAccount(
		$_GET['companyName'] ?? '',
		$_GET['countryIso'] ?? '',
		$_GET['vatNumber'] ?? '',
		$_GET['contactEmail'] ?? '',
		$_GET['subscriptionAmount'] ?? 1,
		explode("\n", $_GET['siteUrls'] ?? ''),
		$_GET['invoiceEmail'] ?? '',
		$_GET['invoiceReference'] ?? '',
		$_GET['invoiceAddressLine1'] ?? '',
		$_GET['invoiceAddressLine2'] ?? '',
		$_GET['invoiceAddressPostalCode'] ?? '',
		$_GET['invoiceAddressLocality'] ?? '',
		$_GET['invoiceAddressRegion'] ?? '',
		$_GET['invoiceAddressCountryIso'] ?? '',
		$_GET['invoiceContactName'] ?? null,
		true
	);
}

?>
<!DOCTYPE html>
<html lang="nl-NL">
<head>
	<title>Reseller.createClientAccount example</title>
	<style>
	   input:not([type="submit"]), textarea {
		   width: 400px;
		   margin-left: 10px;
	   }

	   label {
		   display: grid;
		   grid-template-columns: 200px 1fr;
		   margin-bottom: 15px;
	   }

	   .create-client-account-result {
		   padding: 1em;
	   }
	</style>
</head>
<body>
<h3>Reseller.createClientAccount</h3>
<p>For more information see README.md</p>
<form action="createClientAccount.php" method="GET">
	<label>Company Name <input type="text" name="companyName" placeholder="Company name" value="<?php print($_GET['companyName'] ?? ''); ?>"></label><br>
	<label>Country ISO <input type="text" name="countryIso" placeholder="Country ISO" value="<?php print($_GET['countryIso'] ?? ''); ?>"></label><br>
	<label>VAT Number <input type="text" name="vatNumber" placeholder="VAT Number" value="<?php print($_GET['vatNumber'] ?? ''); ?>"></label><br>
	<label>Contact Email <input type="email" name="contactEmail" placeholder="Contact Email" value="<?php print($_GET['contactEmail'] ?? ''); ?>"></label><br>
	<label>Subscription Amount <input type="number" name="subscriptionAmount" placeholder="Subscription Amount" value="<?php print($_GET['subscriptionAmount'] ?? ''); ?>"></label><br>
	<label>Site Urls <textarea name="siteUrls" placeholder="Site URLs, 1 per line"><?php print($_GET['siteUrls'] ?? ''); ?></textarea></label><br>
	<label>Invoice Email" <input type="email" name="invoiceEmail" placeholder="Invoice Email" value="<?php print($_GET['invoiceEmail'] ?? ''); ?>"></label><br>
	<label>Invoice Reference <input type="text" name="invoiceReference" placeholder="Invoice Reference" value="<?php print($_GET['invoiceReference'] ?? ''); ?>"></label><br>
	<label>Invoice Address Line 1 <input type="text" name="invoiceAddressLine1" placeholder="Invoice Address Line 1" value="<?php print($_GET['invoiceAddressLine1'] ?? ''); ?>"></label><br>
	<label>Invoice Address Line 2 <input type="text" name="invoiceAddressLine2" placeholder="Invoice Address Line 2" value="<?php print($_GET['invoiceAddressLine2'] ?? ''); ?>"></label><br>
	<label>Invoice Address Postal Code <input type="text" name="invoiceAddressPostalCode" placeholder="Invoice Address Postal Code" value="<?php print($_GET['invoiceAddressPostalCode'] ?? ''); ?>"></label><br>
	<label>Invoice Address Locality <input type="text" name="invoiceAddressLocality" placeholder="Invoice Address Locality" value="<?php print($_GET['invoiceAddressLocality'] ?? ''); ?>"></label><br>
	<label>Invoice Address Region <input type="text" name="invoiceAddressRegion" placeholder="Invoice Address Region" value="<?php print($_GET['invoiceAddressRegion'] ?? ''); ?>"></label><br>
	<label>Invoice Contact Name <input type="text" name="invoiceContactName" placeholder="Invoice Contact Name" value="<?php print($_GET['invoiceContactName'] ?? ''); ?>"></label><br>

	<input type="submit" value="Create reseller account" name="createResellerAccount">
</form>

<pre class="create-client-account-result"><?php print($result === null ? '' : var_export($result, true)); ?></pre>
</body>
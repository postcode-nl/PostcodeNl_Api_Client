Example Postcode.nl Api Proxy
=============

PHP Api proxy for the [Postcode.nl Api](https://api.postcode.nl/documentation/).


Usage
=============

This directory serves as an extremely simple proxy example for the supplied client.
Set the correct `API_KEY` and `API_SECRET` and optional `PLATFORM` and you can call
any supported method, for example:

### Show all valid data from a valid postal code
`.../example/index.php?action=dutchAddressByPostcode&data[]=2012es&data[]=30`

### Return an exception for non-existing addresses
`.../example/index.php?action=dutchAddressByPostcode&data[]=2012es&data[]=31`

### Show autocomplete results for a German lookup
`.../example/index.php?action=internationalAutocomplete&data[]=deu&data[]=strasse&data[]=CLIENT_SESSION_FROM_COOKIE`

Please only use this proxy as an example for your own implementation.
What you should add:

* Don't allow random calls but implement what you use in a custom wrapper
* Add caching to prevent fetching the same data over and over
* Add Session by reading `$_COOKIE`, don't pass it in the URL

License
=============

The code is available under the Simplified BSD License, see the included LICENSE file.

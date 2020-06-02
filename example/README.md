Example Postcode.nl Api Proxy
=============

PHP Api proxy for the [Postcode.nl Api](https://api.postcode.nl/documentation/).


Usage
=============

This directory serves as an extremely simple proxy example for the supplied client.
Set the correct `API_KEY` and `API_SECRET` and optional `PLATFORM` and you can call
any supported method, for example:

### Show all valid data from a valid postal code
`example/index.php?p=dutchAddressByPostcode/2012es/30`

### Return an exception for non-existing addresses
`example/index.php?p=dutchAddressByPostcode/2012es/31`

### Show autocomplete results for a German lookup
`example/index.php?p=internationalAutocomplete/deu/strass`

### Show details for a German autocomplete lookup
`example/index.php?p=internationalGetDetails/deu6SVBbpsiLAfbIGnJSvrXjowbUfFAEQxTRQHTsHM9gj76DzsZ6P3BBv8bOknW7MXrI8OJuDanqDp7iy8WwE7woYhDTqHIpSilNHbOTx00CL8QmigIZ1yxr9PNVyRIL9cPQPhwfDpYYo0NgSeI9E`

Please only use this proxy as an example for your own implementation.
What you should add:

* Don't allow random calls but implement what you use in a custom wrapper
* Add caching to prevent fetching the same data over and over
* Add Session by reading `$_COOKIE`, don't pass it in the URL
* Configure your webserver to send all requests under a certain directory to your proxy
 so you don't need to use `?p=`

License
=============

The code is available under the Simplified BSD License, see the included LICENSE file.

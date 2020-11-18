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
`example/index.php?p=internationalAutocomplete/deu/berlin`

### Show details for a German autocomplete lookup
`example/index.php?p=internationalGetDetails/deu8jKJe6loItZqtGADAIQGhsPCv9lzLDkd8gfldjgkL4auvVBghq9FoiAuO51y2cL1WohglY2INCswqCGlak7NOm30ELGKca7R8pamRPzapHYQRDVC75lB7eDs26l38FuHTw6Ijp2ISEfN8l2thu1kUa`

Please only use this proxy as an example for your own implementation.
What you should add:

* Don't allow random calls but implement what you use in a custom wrapper.
* Add caching to prevent fetching the same data over and over.
* Properly implement session identifiers when using the international autocomplete API. Use a new identifier for each address being validated.
* Configure your webserver to send all requests under a certain directory to your proxy
 so you don't need to use `?p=`

License
=============

The code is available under the Simplified BSD License, see the included LICENSE file.

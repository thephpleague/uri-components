Uri Components
=======

[![Build Status](https://img.shields.io/travis/thephpleague/uri/master.svg?style=flat-square)](https://travis-ci.org/thephpleague/uri-components)
[![Latest Version](https://img.shields.io/github/release/thephpleague/uri-components.svg?style=flat-square)](https://github.com/thephpleague/uri-components/releases)

This package contains concrete URI components object represented as immutable value object. Each URI component object implements `League\Uri\Interfaces\Component` interface as defined in the [uri-interfaces package](https://github.com/thephpleague/uri-interfaces).

System Requirements
-------

You need:

- **PHP >= 5.6.0** but the latest stable version of PHP is recommended
- the `mbstring` extension
- the `intl` extension

Dependencies
-------

- [uri-interfaces](https://github.com/thephpleague/uri-interfaces)
- [php-domain-parser](https://github.com/jeremykendall/php-domain-parser)

Installation
--------

Clone this repo and use composer install

Documentation
--------

The following URI component object are defined:

- `League\Uri\Components\Scheme` : for the Scheme URI component
- `League\Uri\Components\UserInfo` : for the User Info URI component
- `League\Uri\Components\Host` : for the Host URI component
- `League\Uri\Components\Port` : for the Port URI component
- `League\Uri\Components\Path` : for a generic Path URI component
- `League\Uri\Components\DataPath` : for a Data Path URI component [RFC 2397](https://tools.ietf.org/html/rfc2397)
- `League\Uri\Components\HierarchicalPath` : for a Hierarchical Path URI component [RFC 3986](https://tools.ietf.org/html/rfc3986)
- `League\Uri\Components\Query` : for the Query URI component
- `League\Uri\Components\Fragment` :  for the Port URI component


Contributing
-------

Contributions are welcome and will be fully credited. Please see [CONTRIBUTING](.github/CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

Testing
-------

`uri-components` has a [PHPUnit](https://phpunit.de) test suite and a coding style compliance test suite using [PHP CS Fixer](http://cs.sensiolabs.org/). To run the tests, run the following command from the project folder.

``` bash
$ composer test
```

Security
-------

If you discover any security related issues, please email nyamsprod@gmail.com instead of using the issue tracker.

Credits
-------

- [ignace nyamagana butera](https://github.com/nyamsprod)
- [All Contributors](https://github.com/thephpleague/uri-components/contributors)

License
-------

The MIT License (MIT). Please see [License File](LICENSE) for more information.
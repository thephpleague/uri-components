Uri Components
=======

[![Quality Assurance](https://github.com/thephpleague/uri-components/workflows/Quality%20Assurance/badge.svg)](https://github.com/thephpleague/uri-components/actions?query=workflow%3A%22Quality+Assurance%22)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build Status](https://img.shields.io/travis/thephpleague/uri-components/master.svg?style=flat-square)](https://travis-ci.org/thephpleague/uri-components)
[![Latest Version](https://img.shields.io/github/release/thephpleague/uri-components.svg?style=flat-square)](https://github.com/thephpleague/uri-components/releases)

This package contains concrete URI components instances represented as immutable value objects.

System Requirements
-------

You need **PHP >= 7.2** but the latest stable version of PHP is recommended.

Dependencies
-------

- [League URI Interfaces][]
- [PSR-7][]

In order to handle IDN host you should also require the `intl` extension otherwise an exception will be thrown when attempting to validate such host.

Installation
--------

```
$ composer require league/uri-components
```

Documentation
--------

Full documentation can be found at [uri.thephpleague.com][].


Contributing
-------

Contributions are welcome and will be fully credited. Please see [CONTRIBUTING](.github/CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

Testing
-------

The library has a :

- a [PHPUnit](https://phpunit.de) test suite
- a coding style compliance test suite using [PHP CS Fixer](http://cs.sensiolabs.org/).
- a code analysis compliance test suite using [PHPStan](https://github.com/phpstan/phpstan).

To run the tests, run the following command from the project folder.

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

[PSR-2]: http://www.php-fig.org/psr/psr-2/
[PSR-4]: http://www.php-fig.org/psr/psr-4/
[PSR-7]: http://www.php-fig.org/psr/psr-7/
[RFC3986]: http://tools.ietf.org/html/rfc3986
[RFC3987]: http://tools.ietf.org/html/rfc3987
[uri.thephpleague.com]: http://uri.thephpleague.com
[League URI Interfaces]: https://github.com/thephpleague/uri-interfaces

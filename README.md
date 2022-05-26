Uri Components
=======

[![Build](https://github.com/thephpleague/uri-components/workflows/build/badge.svg)](https://github.com/thephpleague/uri-components/actions?query=workflow%3A%22build%22)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Latest Version](https://img.shields.io/github/release/thephpleague/uri-components.svg?style=flat-square)](https://github.com/thephpleague/uri-components/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/league/uri-components.svg?style=flat-square)](https://packagist.org/packages/league/uri-components)


This package contains concrete URI components instances represented as immutable value objects.

System Requirements
-------

The latest stable version of PHP is recommended.

Please find below the PHP support for `URI` version 2.

| Min. Library Version | Min. PHP Version | Max. Supported PHP Version |
|----------------------|------------------|----------------------------|
| 2.4.0                | PHP 7.4          | PHP 8.1.x                  |
| 2.0.0                | PHP 7.3          | PHP 8.0.x                  |

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

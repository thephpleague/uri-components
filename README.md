Uri Components
=======

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Latest Version](https://img.shields.io/github/release/thephpleague/uri-components.svg?style=flat-square)](https://github.com/thephpleague/uri-components/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/league/uri-components.svg?style=flat-square)](https://packagist.org/packages/league/uri-components)

This package contains concrete URI components instances represented as immutable value objects.

> ⚠️ this is a sub-split, for development, pull requests and issues, visit: https://github.com/thephpleague/uri-src

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

License
-------

The MIT License (MIT). Please see [License File](LICENSE) for more information.

[PSR-7]: http://www.php-fig.org/psr/psr-7/
[uri.thephpleague.com]: http://uri.thephpleague.com
[League URI Interfaces]: https://github.com/thephpleague/uri-interfaces

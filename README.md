Uri Components
=======

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Latest Version](https://img.shields.io/github/release/thephpleague/uri-components.svg?style=flat-square)](https://github.com/thephpleague/uri-components/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/league/uri-components.svg?style=flat-square)](https://packagist.org/packages/league/uri-components)

This package contains concrete URI components instances represented as immutable value objects.

> ⚠️ this is a sub-split, for development, pull requests and issues, visit: https://github.com/thephpleague/uri-src

System Requirements
-------

You require **PHP >= 8.1** but the latest stable version of PHP is recommended

Handling of an IDN host requires the presence of the `intl`
extension or a polyfill for the `intl` IDN functions like the
`symfony/polyfill-intl-idn` otherwise an exception will be thrown
when attempting to validate or interact with such a host.

IPv4 conversion requires at least one of the following:

- the `GMP` extension,
- the `BCMatch` extension or
- a `64-bits` PHP version

otherwise an exception will be thrown when attempting to convert a host
as an IPv4 address.

Dependencies
-------

- [League URI][]
- [League URI Interfaces][]
- [PSR-7][]

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
[League URI]: https://github.com/thephpleague/uri

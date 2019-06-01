Uri Components
=======

[![Build Status](https://img.shields.io/travis/thephpleague/uri/master.svg?style=flat-square)](https://travis-ci.org/thephpleague/uri-components)
[![Latest Version](https://img.shields.io/github/release/thephpleague/uri-components.svg?style=flat-square)](https://github.com/thephpleague/uri-components/releases)

This package contains concrete URI components object represented as immutable value object.

System Requirements
-------

You need **PHP >= 7.2** but the latest stable version of PHP is recommended.

In order to handle IDN host you should also install the `intl` extension otherwise an exception will be thrown when attempting to validate such host.

Installation
--------

```bash
$ composer require league/uri-components
```

Documentation
--------

Full documentation can be found at [uri.thephpleague.com](http://uri.thephpleague.com/components/).

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
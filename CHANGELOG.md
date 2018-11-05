# Changelog

All Notable changes to `League\Uri\Components` will be documented in this file

## 1.8.2 - 2018-10-24

### Added

- None

### Fixed

- Issues [#22](https://github.com/thephpleague/uri-components/issues/22) bug with path encoding and path validation before path modification see issue [#4](https://github.com/thephpleague/uri-manipulations/issues/4)

### Deprecated

- None

### Remove

- None

## 1.8.1 - 2018-07-06

### Added

- None

### Fixed

- Issue [#21](https://github.com/thephpleague/uri-components/issues/21) namespace collision
with exception usage in `Uri\QueryParser` and Q`Uri\QueryBuilder`

### Deprecated

- None

### Remove

- None

## 1.8.0 - 2018-03-14

### Added

- IPvFuture support

### Fixed

- Using PHPStan
- Using the new scrutinizr engine for PHP
- Bug fix Port class to conform to RFC3986 now allow any port number greater or equals to `0`.
- Improve Host parsing

### Deprecated

- None

### Remove

- `mbstring` extension requirement

## 1.7.1 - 2018-02-16

### Added

- None

### Fixed

- The `Host` resolver and its usage is lazyloaded so that `Host` only requires and used them if needed
- Bug fix issue with:
    - `Host::withPublicSuffix`
    - `Host::withRegistrableDomain`
    - `Host::withSubDomain`
methods that were leaving the current `Host` object corrupted in some cases.

### Deprecated

- None

### Remove

- None

## 1.7.0 - 2018-01-31

### Added

- Adding the possibility to use your own domain resolver object.
    - `Host::__construct` can take an optional `Rules` object as the domain resolver
    - `Host::createFromIp` can take an optional `Rules` object as the domain resolver
    - `Host::createFromLabels` can take an optional `Rules` object as the domain resolver
    - `Host::withDomainResolver` to enable switching to current domain resolver object

### Fixed

- The domain resolver as a Rules object is now injecting into the Host domain so that its data can be cached independently of the filecache. If not domain resolver is provided the Host will fallback to using the filecache with the data being kept for 7 days in a `vendor` subdirectory.

- Decoupled the `QueryBuilder` and the `QueryParser` from `ComponentTrait`

### Deprecated

- None

### Remove

- None

## 1.6.0 - 2017-12-05

### Added

- `Host::withPublicSuffix` to complete registered name manipulation methods

### Fixed

- registered name infos loading

### Deprecated

- None

### Remove

- None

## 1.5.0 - 2017-12-01

### Added

- `Uri\QueryParser` class to parse any string into key/pair value or extract PHP values
- `Uri\QueryBuilder` class to build a valid query string from a collection of Key/pair values
- `Uri\pairs_to_params` alias for `QueryParser::convert`

### Fixed

- URI Host parsing to respect RFC3986
- improve internal code

### Deprecated

- `Query::parse` replaced by `QueryParser::parse`
- `Query::extract` replaced by `QueryParser::extract`
- `Query::build` replaced by `QueryBuilder::build`
- `Host::getRegisterableDomain` replaced by `Host::getRegistrableDomain`
- `Host::withRegisterableDomain` replaced by `Host::withRegistrableDomain`

### Remove

- internal traits `QueryParserTrait`, `HostInfoTrait`

## 1.4.1 - 2017-11-24

### Added

- None

### Fixed

- URI Hostname parser local cache update

### Deprecated

- None

### Remove

- None

## 1.4.0 - 2017-11-22

### Added

- Dependencies to [League URI Hostname parser](https://github.com/thephpleague/uri-hostname-parser)

### Fixed

- Issue [#109](https://github.com/thephpleague/uri/issues/109) Dependencie on a unstable package

### Deprecated

- None

### Remove

- Dependencies to [PHP Domaine parser](https://github.com/jeremykendall/php-domain-parser/)

## 1.3.0 - 2017-11-17

### Added

- `Query::getSeparator`, `Query::withSeparator` to allow modifying query string separator.
- `Query::__construct` takes a second argument, the query separator which default to `&`.
- `Query::__debugInfo` nows adds query separator informations.
- `Query::withoutParams` to complement `Query::withoutPairs` method.
- `Query::createFromParams` to complement `Query::createFromPairs` named constructor.
- `Query::withoutNumericIndices` to normalized the query string by removing extra numeric indices added by the use of `http_build_query`.
- `Query::withoutEmptyPairs` to normalized the query string [#7](https://github.com/thephpleague/uri/pull/7) and [#8](https://github.com/thephpleague/uri/pull/8)

### Fixed

- `Query::merge` and `Query::append` normalized the query string to remove empty pairs see [#7](https://github.com/thephpleague/uri/pull/7) and [#8](https://github.com/thephpleague/uri/pull/8)
- `Query::build` and `Uri\build` now accept any iterable structure.

### Deprecated

- None

### Remove

- None

## 1.2.0 - 2017-11-06

### Added

- `League\Uri\build_query` as an alias of `Query::build`

### Fixed

- function docblocks

### Deprecated

- None

### Remove

- None

## 1.1.1 - 2017-11-03

### Added

- `League\Uri\parse_query` as an alias of `Query::parse`
- `League\Uri\extract_query` as an alias of `Query::extract`

### Fixed

- `League\Uri\parse_query` returned value was the wrong one

### Deprecated

- None

### Remove

- None

## 1.1.0 - 2017-10-24

### Added

- `League\Uri\parse_query` as an alias of `Query::extract`

### Fixed

- Internal call in PHP7.2 with incompatible definitions
- update PHP Domain Parser to be compatible with PHP7.2 deprecation notice
- remove restriction to constructor characters for `Path`, `Query` and `UserInfo`.

### Deprecated

- None

### Remove

- None

## 1.0.4 - 2017-08-10

### Added

- None

### Fixed

- Bug fix label conversion depending on locale [issue #102](https://github.com/thephpleague/uri/issues/102)

### Deprecated

- None

### Remove

- None

## 1.0.3 - 2017-04-27

### Added

- None

### Fixed

- Bug fix negative offset [issue #5](https://github.com/thephpleague/uri-components/issues/5)

### Deprecated

- None

### Remove

- None

## 1.0.2 - 2017-04-19

### Added

- None

### Fixed

- Improve registered name validation [issue #5](https://github.com/thephpleague/uri-parser/issues/5)

### Deprecated

- None

### Remove

- None

## 1.0.1 - 2017-02-06

### Added

- None

### Fixed

- Update idn to ascii algorithm from INTL_IDNA_VARIANT_2003 to  INTL_IDNA_VARIANT_UTS46

### Deprecated

- None

### Remove

- None

## 1.0.0 - 2017-01-17

### Added

- None

### Fixed

- Improve validation check for `Query::build`
- Remove `func_* function usage
- Improve `HierarchicalPath::createFromSegments`
- Internal code simplification

### Deprecated

- None

### Remove

- None

## 1.0.0-RC1 - 2017-01-09

### Added

- `ComponentInterface`
- `EncodingInterface`
- `HierarchicalPath::withDirname`
- `HierarchicalPath::withBasename`
- `HierarchicalPath::withoutSegments`
- `HierarchicalPath::replaceSegment`
- `Host::withRegisterableDomain`
- `Host::withSubdomain`
- `Host::withRootLabel`
- `Host::withoutRootLabel`
- `Host::withoutLabels`
- `Host::replaceLabel`
- `Query::getParams`
- `Query::getParam`
- `Query::append`
- `Query::hasPair`
- `Query::withoutPairs`

### Fixed

- ComponentInterface::getContent supports RFC1738
- The methods that accept integer offset supports negative offset
    - `HierarchicalPath::getSegment`
    - `HierarchicalPath::replaceSegment`
    - `HierarchicalPath::withoutSegments`
    - `Host::getLabel`
    - `Host::replaceLabel`
    - `Host::withoutLabels`
- `Query::merge` only accepts string

### Deprecated

- None

### Remove

- PHP5 support
- Implementing `League\Uri\Interfaces\Component`
- `Query::hasKey`
- `Query::without`
- `Query::filter`
- `Host::hasKey`
- `Host::without`
- `Host::filter`
- `Host::replace`
- `HierarchicalPath::hasKey`
- `HierarchicalPath::without`
- `HierarchicalPath::filter`
- `HierarchicalPath::replace`
- `League\Uri\Components\PathInterface`

## 0.5.0 - 2016-12-09

### Added

- None

### Fixed

- Remove `League\Uri\Interfaces\CollectionComponent` interface dependencies from:
    - `League\Uri\Components\Host`
    - `League\Uri\Components\HierarchicalPath`

- Bug fix `League\Uri\Components\Query::build`

- Update dependencies on `League\Uri\Interfaces`

### Deprecated

- None

### Remove

- None

## 0.4.0 - 2016-12-01

### Added

- None

### Fixed

- `League\Uri\Components\Host::getContent` now support correctly RFC3987
- `League\Uri\Components\Host::__toString` only returns RFC3986 representation
- `League\Uri\Components\UserInfo::getUser` to use the `$enc_type` parameter
- `League\Uri\Components\UserInfo::getPass` to use the `$enc_type` parameter

### Deprecated

- None

### Remove

- `League\Uri\Components\Host::isIdn`
- `League\Uri\Components\Port::getDecoded`
- `League\Uri\Components\Scheme::getDecoded`

## 0.3.0 - 2016-11-29

### Added

- `League\Uri\Components\Exception` as the base exception for the library
- `League\Uri\Components\DataPath::getDecoded` returns the non-encoded path
- `League\Uri\Components\HierarchicalPath::getDecoded` returns the non-encoded path
- `League\Uri\Components\Path::getDecoded` returns the non-encoded path
- `League\Uri\Components\Fragment::getDecoded` returns the non-encoded fragment
- `League\Uri\Components\Port::getDecoded` returns the non-encoded port
- `League\Uri\Components\Scheme::getDecoded` returns the non-encoded scheme
- `League\Uri\Components\Query::extract` public static method returns a hash similar to `parse_str` without the mangling from the query string

### Fixed

- `getContent` is updated to support RFC3987

### Deprecated

- None

### Removed

- `Query::parsed` use `Query::extract` instead
- `Query::parsedValue` use `Query::extract` instead

## 0.2.1

### Added

- None

### Fixed

- issue [#84](https://github.com/thephpleague/uri/issues/84). Query string is not well encoded.

### Deprecated

- None

### Removed

- None

## 0.2.0

### Added

- `Query::parsed` returns an array similar to `parse_str` result with a second options with unmangled key.
- `Query::getParsedValue` returns single value from the parse and unmangled PHP variables.
- `Host::createFromIp` a name constructor to create a host from a IP
- `Host::getIp` returns the IP part from the host if present otherwise returns `null`

### Fixed

- `Host::__construct` no longers takes a IPv6 without delimiter as a valid argument.

### Deprecated

- None

### Removed

- None

## 0.1.1 - 2016-11-09

- improve dependencies - broken

## 0.1.0 - 2016-10-17

### Added

- None

### Fixed

- `League\Uri\QueryParser` is now a trait `League\Uri\Components\Traits\QueryParser` used by `League\Uri\Components\Query`
- `League\Uri\Components\UserInfo` now only accepts string and null as constructor parameters.

### Deprecated

- None

### Removed

- `League\Uri\Components\User`
- `League\Uri\Components\Pass`
- `League\Uri\QueryParser`
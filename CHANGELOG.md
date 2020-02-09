# Changelog

All Notable changes to `League\Uri\Components` will be documented in this file

## 2.2.1 - 2020-02-09

### Added 

- None

### Fixed

- back port improvement made to `DataUri` by [#154](https://github.com/thephpleague/uri/issues/154) thanks to [Nicolas Grekas](https://github.com/nicolas-grekas)

### Deprecated

- None

### Remove

- None

## 2.2.0 - 2020-02-08

### Added 

- None

### Fixed

- back port improvement made to `idn_to_ascii` usage see [#150](https://github.com/thephpleague/uri/issues/150) thanks to [ntzm](https://github.com/ntzm)

### Deprecated

- None

### Remove

- Hard dependencies on the `ext-fileinfo` PHP extensions see [#154](https://github.com/thephpleague/uri/pull/154) thanks [Nicolas Grekas](https://github.com/nicolas-grekas)

## 2.1.0 - 2019-12-19

### Added 

- `League\Uri\UriModifier::removeEmptyPairs` - to remove empty pairs from the URL object.

### Fixed

- Improve UserInfo decoding [issue #28](https://github.com/thephpleague/uri-components/pull/28)
- Improve processing URI object with `League\Uri\UriModifier` with a better distinction between empty and undefined URI component.

### Deprecated

- None

### Remove

- None

## 2.0.1 - 2019-11-05

### Added 

- None

### Fixed

- Improved Domain name detection according to RFC1132 see [issue #27](https://github.com/thephpleague/uri-components/pull/27)
- Normalized exception message formatting.

### Deprecated

- None

### Remove

- None

## 2.0.0 - 2019-10-18

### Added

- `League\Uri\IPv4HostNormalizer` to ease IPV4 host string normalization. 
- `League\Uri\UriModifier` to ease manipulating `League\Uri\UriInterface` and `Psr\Http\Message\UriInterface` implementing objects.
- `League\Uri\QueryString` to parse, extract and build query string and parameters
- All components classes implement the `League\Uri\Contracts\ComponentInterface` 
- All components classes expose the `createFromUri` named constructor to instantiate a component object from a URI object
- `League\Uri\Components\Authority` to represent the URI authority component
- `League\Uri\Components\Fragment::decoded` to return the safely decoded fragment content
- `League\Uri\Components\UserInfo::decoded` to return the safely decoded user info content
- `League\Uri\Components\Port::toInt` to return the int representation of the Port or null
- `League\Uri\Components\Domain` to better process domain host
- `League\Uri\Components\HierarchicalPath::createAbsoluteFromSegments`
- `League\Uri\Components\HierarchicalPath::createRelativeFromSegments`
- `League\Uri\Components\HierarchicalPath::segments` to return the component segments
- `League\Uri\Components\HierarchicalPath::get` to return a specific segment
- `League\Uri\Components\Query` follows more closely the [URLSearchParams](https://url.spec.whatwg.org/#interface-urlsearchparams) specifications from the WHATWG group
- `League\Uri\Components\Query::createFromRFC3986` to return a new object from a RFC3986 query string
- `League\Uri\Components\Query::createFromRFC1738` to return a new object from a RFC1738 query string
- `League\Uri\Components\Query::toRFC3986` to return a RFC3986 query string
- `League\Uri\Components\Query::toRFC1738` to return a RFC1738 query string

### Fixed

- Components classes are made `final`
- `getContent` no-longer takes any parameter
- `Host` objects throws `League\Uri\Exception\IdnSupportMissing` on mis-configured or absent Intl extension presence.
- `UserInfo::__construct` expects two arguments the user and the pass instead of one.
- `Query::__construct` is now private
- Query parsing/building is fixed so that a round between parsing and building returns the original input.

### Deprecated

- None

### Remove

- support for `PHP7.0`
- support for `PHP7.1`
- support for Public Suffix List resolution
- `isEmpty` and `isNull` methods are removed
- `League\Uri\parse_query`
- `League\Uri\build_query`
- `League\Uri\extract_query`
- `League\Uri\pairs_to_params`
- `League\Uri\QueryBuilder`
- `League\Uri\QueryParser`
- `League\Uri\Components\ComponentInterface`
- `League\Uri\Components\HierarchicalPath::createFromSegments`
- `League\Uri\Components\HierarchicalPath::getSegments`
- `League\Uri\Components\HierarchicalPath::getSegment`
- `League\Uri\Components\HierarchicalPath::IS_ABSOLUTE`
- `League\Uri\Components\HierarchicalPath::IS_RELATIVE`
- The following methods are transferred to the new `League\Uri\Components\Domain` class
- `League\Uri\Components\Host::isAbsolute`
- `League\Uri\Components\Host::getLabels`
- `League\Uri\Components\Host::getLabel`
- `League\Uri\Components\Host::keys`
- `League\Uri\Components\Host::count`
- `League\Uri\Components\Host::getIterator`
- `League\Uri\Components\Host::append`
- `League\Uri\Components\Host::prepend`
- `League\Uri\Components\Host::replaceLabel`
- `League\Uri\Components\Host::withoutLabels`
- `League\Uri\Components\Host::withRootLabel`
- `League\Uri\Components\Host::withoutRootLabel`
- `League\Uri\Components\Query::ksort`
- `League\Uri\Components\Query::getParams`
- `League\Uri\Components\Query::getParam`
- `League\Uri\Components\Query::getPairs`
- `League\Uri\Components\Query::getPair`
- `League\Uri\Components\Query::hasPair`

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

# Changelog

All Notable changes to `League\Uri\Components` will be documented in this file

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
# Changelog

All Notable changes to `League\Uri\Components` will be documented in this file

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
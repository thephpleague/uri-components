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

The following URI component objects are defined:

- `League\Uri\Components\Scheme` : for the Scheme URI component
- `League\Uri\Components\Port` : for the Port URI component
- `League\Uri\Components\Fragment` :  for the Fragment URI component
- `League\Uri\Components\UserInfo` : for the User Info URI component
- `League\Uri\Components\Query` : for the Query URI component
- `League\Uri\Components\Host` : for the Host URI component
- `League\Uri\Components\Path` : for a generic Path URI component
- `League\Uri\Components\DataPath` : for a Data Path URI component [RFC 2397](https://tools.ietf.org/html/rfc2397)
- `League\Uri\Components\HierarchicalPath` : for a Hierarchical Path URI component [RFC 3986](https://tools.ietf.org/html/rfc3986)

Any Component object exposes the following methods and constant:

```php
<?php

const Component::RFC3986 = 'RFC3986';
const Component::RFC3987 = 'RFC3987';
public Component::isDefined(void): bool
public Component::getContent(string $enc_type = Component::RFC3986): mixed
public Component::__toString(): string
public Component::getUriComponent(void): string
public Component::withContent(string $content): self
```

**NEW**

- `Component::isDefined` returns `true` when the component content is not equal to `null`.
- `Component::getContent` can return a `string` or an `int`, in case of the `Port` component, if the component is defined, otherwise the method returns `null`.
- When the `$enc_type` parameter is used, the method returns a value encoded against RFC3986 or RFC3987 rules. `$enc_type` value can be `Component::RFC3986` or `Component::RFC3987`.
- `Component::withContent` accept any string in no particular encoding but will normalize the string to be RFC3986 compliant.

**BC Break:**

- `Component::sameValueAs` is removed

### Creating new objects

To instantiate a new component object you can use the default constructor as follow:

```php
<?php
public Component::__construct(string $content = null): void
```

### Manipulating the component objects

Depending on the component and on its related scheme more methods for manipulating the component are available. Below are listed the specific methods for each components.

### Fragment

```php
<?php

public Fragment::getDecoded(void): string|null
```
**NEW:**

- `Fragment::getDecoded` returns the fragment decoded without any encoding.

### UserInfo

```php
<?php

public UserInfo::getUser(string $enc_type = Component::RFC3986): string|null
public UserInfo::getPass(string $enc_type = Component::RFC3986): string|null
public UserInfo::withUserInfo(string $user [, string $password = null]): self
```

**NEW:**

- `UserInfo::withUserInfo` to ease manipulating the component

**BC Break:**

- `UserInfo::createFromString` is removed

### Query

The `Query` object also implements the following SPL interfaces: `Countable`, `IteratorAggregate`

```php
<?php

public static Query::parse(string $query, string $separator = '&'): array
public static Query::extract(string $query, string $separator = '&'): array
public static Query::build(array $pairs, string $separator = '&', string $enc_type = Query::RFC3986): string
public static Query::createFromPairs(array $pairs): self
public Query::getPairs(void): array
public Query::getValue(string $offset, mixed $default = null): mixed
public Query::hasKey(string $offset): bool
public Query::keys(mixed $value = null): string[]
public Query::merge(Query|string $content): self
public Query::ksort(callable|int $sort = SORT_REGULAR): self
public Query::filter(callable $callable, int $flag = 0): self
public Query::without(string[] $offsets): self
```

**NEW:**

- `Query::extract` returns a hash similar to `parse_str` usage with a second parameter but the array [keys are not mangled](https://wiki.php.net/rfc/on_demand_name_mangling)

### Host

The `Host` object also implements the following SPL interfaces: `Countable`, `IteratorAggregate`

```php
<?php

const Host::IS_RELATIVE = 1;
const Host::IS_ABSOLUTE = 2;
public static Host::createFromIp(string $ip): Host
public static Host::createFromLabels(array $labels, $type = self::IS_RELATIVE): self
public Host::isAbsolute(void): bool
public Host::isIp(void): bool
public Host::isIpv4(void): bool
public Host::isIpv6(void): bool
public Host::hasZoneIdentifier(void): bool
public Host::getIp(void): string|null
public Host::getLabels(void): string[]
public Host::getLabel(int $offset, mixed $default = null): mixed
public Host::hasKey(int $offset): bool
public Host::keys(mixed $value = null): int[]
public Host::getPublicSuffix(): string
public Host::getRegisterableDomain(): string
public Host::getSubdomain(): string
public Host::isPublicSuffixValid(): bool
public Host::withoutZoneIdentifier(): self
public Host::prepend(string $content): self
public Host::append(string $content): self
public Host::replace(int $offset, string $content): self
public Host::filter(callable $callable, int $flag = 0): self
public Host::without(int[] $offsets): self
```

**BC Break:**

- The constructor no longer accept *naked* IPv6 string
- The host labels are always normalized to their RFC3987 representation
- `Host::isIdn` is removed

### Path objects

URI path component objects are modelled depending on the URI as such each URI scheme specific must implement its own path object. To ease Path usage, the package comes with a generic Path object as well as two more specialized Path objects. All Path objects expose the following methods:

```php
<?php

public Path::isEmpty(void): bool
public Path::isAbsolute(void): bool
public Path::getDecoded(void): string
public Path::withLeadingSlash(void): self
public Path::withoutLeadingSlash(void): self
public Path::withoutDotSegments(void): self
public Path::withTrailingSlash(void): self
public Path::withoutTrailingSlash(void): self
public Path::withoutEmptySegments(void): self
```

**NEW:**

- `Path::getDecoded` returns the path decoded without any encoding.
- `Path::isEmpty` tell whether the path is an empty string or not.

**even if the method is exposed using this method may trigger an `InvalidArgumentException` if the specific path does not support the given modifitication**

**According to RFC3986, the `Path::isDefined` method must always returns `true`**

#### HierarchicalPath

This specific path object ease manipulating the  HTTP scheme specific URI path component. The `HierarchicalPath` object also implements the following SPL interfaces: `Countable`, `IteratorAggregate`.

```php
<?php

const HierarchicalPath::IS_RELATIVE = 1;
const HierarchicalPath::IS_ABSOLUTE = 2;
public static HierarchicalPath::createFromSegments(array $segments, $type = self::IS_RELATIVE): self
public HierarchicalPath::getSegments(void): string[]
public HierarchicalPath::getSegment(int $offset, mixed $default = null): mixed
public HierarchicalPath::getBasename(): string
public HierarchicalPath::getDirname(): string
public HierarchicalPath::getExtension(): string
public HierarchicalPath::hasKey(int $offset): bool
public HierarchicalPath::keys(mixed $value = null): int[]
public HierarchicalPath::prepend(string $content): self
public HierarchicalPath::append(string $content): self
public HierarchicalPath::replace(int $offset, string $content): self
public HierarchicalPath::filter(callable $callable, int $flag = 0): self
public HierarchicalPath::without(int[] $offsets): self
public HierarchicalPath::withExtension(string $extension): self
```

**BC Break:**

- All FTP related methods to manipulate the typecode are removed.

#### DataPath

This specific path object ease manipulating the Data scheme specific URI path component.

```php
<?php

public static DataPath::createFromPath(string $path): self
public DataPath::getData(): string
public DataPath::isBinaryData(): bool
public DataPath::getMimeType(): string
public DataPath::getParameters(): string
public DataPath::getMediaType(): string
public DataPath::save(string $path, $mode = 'w'): SplFileObject
public DataPath::toBinary(): self
public DataPath::toAscii(): self
public DataPath::withParameters(string $parameters): self
```

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
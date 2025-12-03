<?php

/**
 * League.Uri (https://uri.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\Components;

use Deprecated;
use Iterator;
use League\Uri\Contracts\QueryInterface;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriException;
use League\Uri\Contracts\UriInterface;
use League\Uri\Encoder;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\KeyValuePair\Converter;
use League\Uri\QueryString;
use League\Uri\UriString;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use Traversable;
use Uri\Rfc3986\Uri as Rfc3986Uri;
use Uri\WhatWg\Url as WhatWgUrl;
use ValueError;

use function array_column;
use function array_count_values;
use function array_filter;
use function array_flip;
use function array_intersect;
use function array_is_list;
use function array_map;
use function array_merge;
use function count;
use function get_object_vars;
use function http_build_query;
use function implode;
use function in_array;
use function is_int;
use function is_object;
use function is_string;
use function preg_match;
use function preg_quote;
use function preg_replace;

use const JSON_PRESERVE_ZERO_FRACTION;
use const PREG_SPLIT_NO_EMPTY;

final class Query extends Component implements QueryInterface
{
    private const REGEXP_NON_ASCII_PATTERN = '/[^\x20-\x7f]/';
    /** @var array<int, array{0:string, 1:string|null}> */
    private readonly array $pairs;
    /** @var non-empty-string */
    private readonly string $separator;
    private readonly array $parameters;

    /**
     * Returns a new instance.
     */
    private function __construct(Stringable|string|null $query, ?Converter $converter = null)
    {
        $converter ??= Converter::fromRFC3986();
        $this->pairs = QueryString::parseFromValue($query, $converter);
        $this->parameters = QueryString::extractFromValue($query, $converter);
        $this->separator = $converter->separator();
    }

    public static function new(Stringable|string|null $value = null): self
    {
        return self::fromRFC3986($value);
    }

    /**
     * Create a new instance from a string.or a stringable structure or returns null on failure.
     */
    public static function tryNew(Stringable|string|null $uri = null): ?self
    {
        try {
            return self::new($uri);
        } catch (UriException) {
            return null;
        }
    }

    /**
     * Returns a new instance from the input of http_build_query.
     *
     * @param non-empty-string $separator
     */
    public static function fromVariable(object|array $parameters, string $separator = '&', string $prefix = ''): self
    {
        $params = is_object($parameters) ? get_object_vars($parameters) : $parameters;

        $data = [];
        foreach ($params as $name => $value) {
            $data[$prefix.$name] = $value;
        }

        return new self(http_build_query(data: $data, arg_separator: $separator), Converter::fromRFC1738($separator));
    }

    /**
     * Returns a new instance from the result of QueryString::parse.
     *
     * @param iterable<int, array{0:string, 1:string|null}> $pairs
     * @param non-empty-string $separator
     */
    public static function fromPairs(iterable $pairs, string $separator = '&', string $prefix = ''): self
    {
        $data = [];
        foreach ($pairs as $pair) {
            if (!is_array($pair) || !array_is_list($pair) || 2 !== count($pair)) {
                throw new SyntaxError('A pair must be a sequential array starting at `0` and containing two elements.');
            }

            $data[] = [$prefix.$pair[0], $pair[1]];
        }

        $converter = Converter::fromRFC3986($separator);

        return new self(QueryString::buildFromPairs($data, $converter), $converter);
    }

    /**
     * Create a new instance from a URI object.
     */
    public static function fromUri(WhatWgUrl|Rfc3986Uri|Stringable|string $uri): self
    {
        $uri = self::filterUri($uri);

        return match (true) {
            $uri instanceof Rfc3986Uri => new self($uri->getRawQuery()),
            $uri instanceof Psr7UriInterface => new self(UriString::parse($uri)['query']),
            default => new self($uri->getQuery()),
        };
    }

    /**
     * Returns a new instance.
     *
     * @param non-empty-string $separator
     */
    public static function fromRFC3986(Stringable|string|null $query = null, string $separator = '&'): self
    {
        return new self($query, Converter::fromRFC3986($separator));
    }

    /**
     * Returns a new instance.
     *
     * @param non-empty-string $separator
     */
    public static function fromRFC1738(Stringable|string|null $query = null, string $separator = '&'): self
    {
        return new self($query, Converter::fromRFC1738($separator));
    }

    /**
     * Returns a new instance.
     *
     * @param non-empty-string $separator
     */
    public static function fromFormData(Stringable|string|null $query = null, string $separator = '&'): self
    {
        return new self($query, Converter::fromFormData($separator));
    }

    public function getSeparator(): string
    {
        return $this->separator;
    }

    public function toRFC3986(): ?string
    {
        return QueryString::buildFromPairs($this->pairs, Converter::fromRFC3986($this->separator));
    }

    public function toRFC1738(): ?string
    {
        return QueryString::buildFromPairs($this->pairs, Converter::fromRFC1738($this->separator));
    }

    public function toFormData(): ?string
    {
        return QueryString::buildFromPairs($this->pairs, Converter::fromFormData($this->separator));
    }

    public function decoded(): ?string
    {
        return Converter::new($this->separator)->toValue($this);
    }

    public function normalize(): self
    {
        return self::new(Encoder::normalizeQuery($this->value()));
    }

    public function value(): ?string
    {
        return $this->toRFC3986();
    }

    public function getUriComponent(): string
    {
        return match ([]) {
            $this->pairs => '',
            default => '?'.$this->value(),
        };
    }

    public function isEmpty(): bool
    {
        return [] === $this->pairs;
    }

    public function jsonSerialize(): ?string
    {
        return $this->toFormData();
    }

    public function count(): int
    {
        return count($this->pairs);
    }

    public function getIterator(): Iterator
    {
        yield from $this->pairs;
    }

    public function pairs(): iterable
    {
        foreach ($this->pairs as $pair) {
            yield $pair[0] => $pair[1];
        }
    }

    public function has(string ...$keys): bool
    {
        foreach ($keys as $key) {
            if (!isset(array_flip(array_column($this->pairs, 0))[$key])) {
                return false;
            }
        }

        return [] !== $keys;
    }

    public function hasPair(string $key, ?string $value): bool
    {
        return in_array([$key, $value], $this->pairs, true);
    }

    public function get(string $key): ?string
    {
        foreach ($this->pairs as $pair) {
            if ($key === $pair[0]) {
                return $pair[1];
            }
        }

        return null;
    }

    public function first(string $key): ?string
    {
        return $this->get($key);
    }

    public function last(string $key): ?string
    {
        $res = $this->getAll($key);

        return $res[count($res) - 1] ?? null;
    }

    public function getAll(string $key): array
    {
        return array_column(array_filter($this->pairs, fn (array $pair): bool => $key === $pair[0]), 1);
    }

    public function equals(mixed $value): bool
    {
        if (!$value instanceof Stringable && !is_string($value) && null !== $value) {
            return false;
        }

        if (!$value instanceof UriComponentInterface) {
            $value = self::tryNew($value);
            if (null === $value) {
                return false;
            }
        }

        return $value->getUriComponent() === $this->getUriComponent();
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    public function parameter(string $name): mixed
    {
        return $this->parameters[$name] ?? null;
    }

    public function hasParameter(string ...$names): bool
    {
        foreach ($names as $name) {
            if (!isset($this->parameters[$name])) {
                return false;
            }
        }

        return [] !== $names;
    }

    public function mergeParameters(object|array $parameter, string $prefix = ''): self
    {
        $params = is_object($parameter) ? get_object_vars($parameter) : $parameter;
        $data = [];
        foreach ($params as $name => $value) {
            $data[$prefix.$name] = $value;
        }

        return in_array($data, [$this->parameters, []], true) ? $this : new self(
            http_build_query(data: array_merge($this->parameters, $data), arg_separator: $this->separator),
            Converter::fromRFC1738($this->separator)
        );
    }

    public function replaceParameter(string $name, mixed $parameter): self
    {
        $this->hasParameter($name) || throw new ValueError('The specified name does not exist');
        if ($parameter === $this->parameters[$name]) {
            return $this;
        }

        $parameters = $this->parameters;
        $parameters[$name] = $parameter;

        return new self(http_build_query(data: $parameters, arg_separator: $this->separator), Converter::fromRFC1738($this->separator));
    }

    public function withSeparator(string $separator): self
    {
        return match ($separator) {
            $this->separator => $this,
            '' => throw new SyntaxError('The separator character cannot be the empty string.'),
            default => self::fromPairs($this->pairs, $separator),
        };
    }

    public function sort(): self
    {
        $codepoints = fn (?string $str): string => in_array($str, ['', null], true) ? '' : implode('.', array_map(
            mb_ord(...), /* @phpstan-ignore-line */
            (array) preg_split(pattern:'//u', subject: $str, flags: PREG_SPLIT_NO_EMPTY)
        ));

        $compare = fn (string $name1, string $name2): int => match (1) {
            preg_match(self::REGEXP_NON_ASCII_PATTERN, $name1.$name2) => strcmp($codepoints($name1), $codepoints($name2)),
            default => strcmp($name1, $name2),
        };

        $parameters = array_reduce($this->pairs, function (array $carry, array $pair) {
            $carry[$pair[0]] ??= [];
            $carry[$pair[0]][] = $pair[1];

            return $carry;
        }, []);

        uksort($parameters, $compare);

        $pairs = [];
        foreach ($parameters as $key => $values) {
            $pairs = [...$pairs, ...array_map(fn ($value) => [$key, $value], $values)];
        }

        return match ($this->pairs) {
            $pairs  => $this,
            default => self::fromPairs($pairs),
        };
    }

    public function withoutDuplicates(): self
    {
        if (count($this->pairs) === count(array_count_values(array_column($this->pairs, 0)))) {
            return $this;
        }

        $pairs = array_reduce($this->pairs, $this->removeDuplicates(...), []);
        if ($pairs === $this->pairs) {
            return $this;
        }

        return self::fromPairs($pairs, $this->separator);
    }

    /**
     * @template TInitial
     *
     * @param callable(TInitial|null, array{0:array-key, 1:mixed}, array-key=): TInitial $callback
     * @param TInitial|null $initial
     *
     * @return TInitial|null
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        foreach ($this->pairs as $offset => $pair) {
            $initial = $callback($initial, $pair, $offset);
        }

        return $initial;
    }

    /**
     * Adds a query pair only if it is not already present in a given array.
     */
    private function removeDuplicates(array $pairs, array $pair): array
    {
        return match (true) {
            in_array($pair, $pairs, true) => $pairs,
            default => [...$pairs, $pair],
        };
    }

    public function withoutEmptyPairs(): self
    {
        $pairs = array_filter($this->pairs, $this->filterEmptyPair(...));

        return match ($this->pairs) {
            $pairs => $this,
            default => self::fromPairs($pairs),
        };
    }

    /**
     * Empty Pair filtering.
     */
    private function filterEmptyPair(array $pair): bool
    {
        return '' !== $pair[0] && null !== $pair[1] && '' !== $pair[1];
    }

    public function withoutNumericIndices(): self
    {
        $pairs = array_map($this->encodeNumericIndices(...), $this->pairs);

        return match ($this->pairs) {
            $pairs => $this,
            default => self::fromPairs($pairs, $this->separator),
        };
    }

    /**
     * Remove numeric indices from pairs.
     *
     * @param array{0:string, 1:string|null} $pair
     *
     * @return array{0:string, 1:string|null}
     */
    private function encodeNumericIndices(array $pair): array
    {
        static $regexp = ',\[\d+],';

        $pair[0] = (string) preg_replace($regexp, '[]', $pair[0]);

        return $pair;
    }

    public function withPair(string $key, Stringable|string|int|float|bool|null $value): QueryInterface
    {
        $pairs = $this->addPair($this->pairs, [$key, $this->filterPair($value)]);

        return match ($this->pairs) {
            $pairs => $this,
            default => self::fromPairs($pairs, $this->separator),
        };
    }

    /**
     * Add a new pair to the query key/value list.
     *
     * If there are any key/value pair whose kay is kay, in the list,
     * set the value of the first such key/value pair to value and remove the others.
     * Otherwise, append a new key/value pair whose key is key and value is value, to the list.
     */
    private function addPair(array $list, array $pair): array
    {
        $found = false;
        $reducer = static function (array $pairs, array $srcPair) use ($pair, &$found): array {
            if ($pair[0] !== $srcPair[0]) {
                $pairs[] = $srcPair;

                return $pairs;
            }

            if (!$found) {
                $pairs[] = $pair;
                $found = true;

                return $pairs;
            }

            return $pairs;
        };

        $pairs = array_reduce($list, $reducer, []);
        if (!$found) {
            $pairs[] = $pair;
        }

        return $pairs;
    }

    public function merge(Stringable|string|null $query): QueryInterface
    {
        $pairs = $this->pairs;
        foreach (QueryString::parse(self::filterComponent($query), $this->separator) as $pair) {
            $pairs = $this->addPair($pairs, $pair);
        }

        return match ($this->pairs) {
            $pairs => $this,
            default => self::fromPairs($pairs, $this->separator),
        };
    }

    /**
     * Validate the given pair.
     *
     * To be valid, the pair must be the null value, a scalar or a collection of scalar and null values.
     */
    private function filterPair(Stringable|string|int|float|bool|null $value): ?string
    {
        return match (true) {
            $value instanceof UriComponentInterface => $value->value(),
            null === $value => null,
            true === $value => 'true',
            false === $value => 'false',
            is_float($value) => (string) json_encode($value, JSON_PRESERVE_ZERO_FRACTION),
            default => (string) $value,
        };
    }

    public function withoutPairByKey(string ...$keys): QueryInterface
    {
        if ([] === $keys) {
            return $this;
        }

        $keysToRemove = array_intersect($keys, array_column($this->pairs, 0));

        return match ([]) {
            $keysToRemove => $this,
            default => self::fromPairs(
                array_filter($this->pairs, static fn (array $pair): bool => !in_array($pair[0], $keysToRemove, true)),
                $this->separator
            ),
        };
    }

    public function withoutPairByValue(Stringable|string|int|float|bool|null ...$values): self
    {
        if ([] === $values) {
            return $this;
        }

        $values = array_map($this->filterPair(...), $values);
        $newPairs = array_filter($this->pairs, fn (array $pair) => !in_array($pair[1], $values, true));

        return match ($this->pairs) {
            $newPairs => $this,
            default => self::fromPairs($newPairs, $this->separator),
        };
    }

    public function withoutPairByKeyValue(string $key, Stringable|string|int|float|bool|null $value): self
    {
        $pair = [$key, $this->filterPair($value)];
        $newPairs = array_filter($this->pairs, fn (array $currentPair) => $currentPair !== $pair);

        return match ($this->pairs) {
            $newPairs => $this,
            default => self::fromPairs($newPairs, $this->separator),
        };
    }

    public function appendTo(string $key, Stringable|string|int|float|bool|null $value): QueryInterface
    {
        return self::fromPairs([...$this->pairs, [$key, $this->filterPair($value)]], $this->separator);
    }

    public function append(Stringable|string|null $query): QueryInterface
    {
        if ($query instanceof UriComponentInterface) {
            $query = $query->value();
        }

        $pairs = array_merge($this->pairs, QueryString::parse($query, $this->separator));

        return match ($this->pairs) {
            $pairs  => $this,
            default => self::fromPairs(array_filter($pairs, $this->filterEmptyValue(...)), $this->separator),
        };
    }

    public function prepend(Stringable|string|null $query): QueryInterface
    {
        return Query::new($query)->append($this);
    }

    /**
     * Replace a pair based on its offset.
     */
    public function replace(int $offset, string $key, Stringable|string|int|float|bool|null $value): QueryInterface
    {
        $index = $offset < 0 ? count($this->pairs) + $offset : $offset;
        $pair = $this->pairs[$index] ?? [];
        [] !== $pair || throw new ValueError('The given offset "'.$offset.'" does not exist');

        $newPair = [$key, $this->filterPair($value)];
        if ($pair === $newPair) {
            return $this;
        }

        $newPairs = $this->pairs;
        $newPairs[$index] = $newPair;

        return self::fromPairs($newPairs, $this->separator);
    }

    /**
     * Returns the offset of the pair based on its key and its nth occurrence.
     *
     * negative occurrences are supported
     */
    public function indexOf(string $key, int $nth = 0): ?int
    {
        if ([] === $this->pairs) {
            return null;
        }

        if ($nth < 0) {
            $matchCount = 0;
            for ($offset = count($this->pairs) - 1; $offset >= 0; --$offset) {
                if ($this->pairs[$offset][0] === $key) {
                    if (++$matchCount === -$nth) {
                        return $offset;
                    }
                }
            }

            return null;
        }

        $matchCount = 0;
        foreach ($this->pairs as $offset => $pair) {
            if ($pair[0] === $key) {
                if ($nth === $matchCount) {
                    return $offset;
                }
                ++$matchCount;
            }
        }

        return null;
    }

    /**
     * Empty Pair filtering.
     */
    private function filterEmptyValue(array $pair): bool
    {
        return '' !== $pair[0] || null !== $pair[1];
    }

    public function withoutParameters(string ...$names): QueryInterface
    {
        if ([] === $names) {
            return $this;
        }

        $mapper = static fn (string $offset): string => preg_quote($offset, ',').'(\[.*\].*)?';
        $regexp = ',^('.implode('|', array_map($mapper, $names)).')?$,';
        $filter = fn (array $pair): bool => 1 !== preg_match($regexp, $pair[0]);
        $pairs = array_filter($this->pairs, $filter);

        return match ($this->pairs) {
            $pairs => $this,
            default => self::fromPairs($pairs, $this->separator),
        };
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Query::fromParameters()
     *
     * @codeCoverageIgnore
     *
     * @param non-empty-string $separator
     *
     * Returns a new instance from the result of PHP's parse_str.
     *
     * @deprecated Since version 7.0.0
     */
    #[Deprecated(message:'use League\Uri\Components\Query::fromVariables() instead', since:'league/uri-components:7.0.0')]
    public static function createFromParams(iterable|object $params, string $separator = '&'): self
    {
        return self::fromParameters($params, $separator);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Query::fromPairs()
     *
     * @codeCoverageIgnore
     *
     *
     * Returns a new instance from the result of QueryString::parse.
     *
     * @param iterable<int, array{0:string, 1:string|null}> $pairs
     * @param non-empty-string $separator
     */
    #[Deprecated(message:'use League\Uri\Components\Query::fromPairs() instead', since:'league/uri-components:7.0.0')]
    public static function createFromPairs(iterable $pairs, string $separator = '&'): self
    {
        return self::fromPairs($pairs, $separator);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Query::fromUri()
     *
     * @codeCoverageIgnore
     *
     * Create a new instance from a URI object.
     */
    #[Deprecated(message:'use League\Uri\Components\Query::fromUri() instead', since:'league/uri-components:7.0.0')]
    public static function createFromUri(Psr7UriInterface|UriInterface $uri): self
    {
        return self::fromUri($uri);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Query::fromRFC3986()
     *
     * @codeCoverageIgnore
     *
     * Returns a new instance.
     *
     * @param non-empty-string $separator
     */
    #[Deprecated(message:'use League\Uri\Components\Query::fromRFC3986() instead', since:'league/uri-components:7.0.0')]
    public static function createFromRFC3986(Stringable|string|int|null $query = '', string $separator = '&'): self
    {
        if (null !== $query) {
            $query = (string) $query;
        }

        return self::fromRFC3986($query, $separator);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Query::fromRFC1738()
     *
     * @codeCoverageIgnore
     *
     * Returns a new instance.
     *
     * @param non-empty-string $separator
     */
    #[Deprecated(message:'use League\Uri\Components\Query::fromRFC1738() instead', since:'league/uri-components:7.0.0')]
    public static function createFromRFC1738(Stringable|string|int|null $query = '', string $separator = '&'): self
    {
        if (is_int($query)) {
            $query = (string) $query;
        }

        return self::fromRFC1738($query, $separator);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Query::parameters()
     * @see Query::parameter()
     *
     * @codeCoverageIgnore
     *
     * Returns the query as a collection of PHP variables or a single variable assign to a specific key
     */
    #[Deprecated(message:'use League\Uri\Components\Query::parameter() or League\Uri\Components\Query::parameters() instead', since:'league/uri-components:7.0.0')]
    public function params(?string $key = null): mixed
    {
        return match (null) {
            $key => $this->parameters(),
            default => $this->parameter($key),
        };
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Query::withoutParameters()
     *
     * @codeCoverageIgnore
     */
    #[Deprecated(message:'use League\Uri\Components\Query::withoutParameters() instead', since:'league/uri-components:7.0.0')]
    public function withoutParams(string ...$names): QueryInterface
    {
        return $this->withoutParameters(...$names);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.3.0
     * @see Query::withoutPairByKey()
     *
     * @codeCoverageIgnore
     */
    #[Deprecated(message:'use League\Uri\Components\Query::withoutPairByKey() instead', since:'league/uri-components:7.3.0')]
    public function withoutPair(string ...$keys): QueryInterface
    {
        return $this->withoutPairByKey(...$keys);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @param non-empty-string $separator
     *
     * @see Query::fromVariable()
     *
     * @codeCoverageIgnore
     * Returns a new instance from the result of PHP's parse_str.
     *
     * @deprecated Since version 7.0.0
     */
    #[Deprecated(message:'use League\Uri\Components\Query::fromVariable() instead', since:'league/uri-components:7.0.0')]
    public static function fromParameters(object|array $parameters, string $separator = '&'): self
    {
        if ($parameters instanceof QueryInterface) {
            return self::fromPairs($parameters, $separator);
        }

        $parameters = match (true) {
            $parameters instanceof Traversable => iterator_to_array($parameters),
            default => $parameters,
        };

        $query = match ([]) {
            $parameters => null,
            default => http_build_query(data: $parameters, arg_separator: $separator),
        };

        return new self($query, Converter::fromRFC1738($separator));
    }
}

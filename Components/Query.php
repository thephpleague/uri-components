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

use BackedEnum;
use Deprecated;
use Iterator;
use League\Uri\Contracts\QueryInterface;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriException;
use League\Uri\Contracts\UriInterface;
use League\Uri\Encoder;
use League\Uri\Exceptions\OffsetOutOfBounds;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\KeyValuePair\Converter;
use League\Uri\QueryComposeMode;
use League\Uri\QueryExtractMode;
use League\Uri\QueryString;
use League\Uri\StringCoercionMode;
use League\Uri\UriString;
use OutOfBoundsException;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use Traversable;
use TypeError;
use UnitEnum;
use Uri\Rfc3986\Uri as Rfc3986Uri;
use Uri\WhatWg\Url as WhatWgUrl;
use ValueError;

use function array_column;
use function array_filter;
use function array_flip;
use function array_intersect;
use function array_is_list;
use function array_map;
use function array_merge;
use function array_reduce;
use function count;
use function get_object_vars;
use function http_build_query;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use function iterator_to_array;
use function preg_match;
use function preg_quote;
use function preg_replace;

use const ARRAY_FILTER_USE_BOTH;
use const PREG_SPLIT_NO_EMPTY;

final class Query extends Component implements QueryInterface
{
    private const REGEXP_NON_ASCII_PATTERN = '/[^\x20-\x7f]/';
    private const REGXP_FILTER_LIST = '/^
        [^\[\]]+        # base key (no [ or ])
        (?:\[[^\]]*\])+ # one or more bracket groups
    $/x';
    /** @var array<int, array{0:string, 1:string|null}> */
    private readonly array $pairs;
    /** @var non-empty-string */
    private readonly string $separator;
    private readonly array $parameters;
    private readonly array $list;

    /**
     * Returns a new instance.
     *
     * @throws SyntaxError
     */
    private function __construct(BackedEnum|Stringable|string|null $query, ?Converter $converter = null)
    {
        $converter ??= Converter::fromRFC3986();
        $this->pairs = QueryString::parseFromValue($query, $converter);
        $this->separator = $converter->separator();
        $this->parameters = QueryString::extractFromValue($query, $converter);
        $this->list = QueryString::convert(
            array_filter($this->pairs, static fn (array $pair): bool => 1 === preg_match(self::REGXP_FILTER_LIST, $pair[0])),
            QueryExtractMode::LossLess,
        );
    }

    /**
     * @throws SyntaxError
     */
    public static function new(BackedEnum|Stringable|string|null $value = null): self
    {
        return self::fromRFC3986($value);
    }

    /**
     * Create a new instance from a string.or a stringable structure or returns null on failure.
     */
    public static function tryNew(BackedEnum|Stringable|string|null $uri = null): ?self
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
    public static function fromVariable(
        object|array $parameters,
        string $separator = '&',
        string $prefix = '',
        QueryComposeMode $composeMode = QueryComposeMode::Native
    ): self {
        if ($parameters instanceof UnitEnum && QueryComposeMode::Compatible !== $composeMode) {
            throw new TypeError('Enum can not be used as arguments.');
        }

        $params = is_object($parameters) ? get_object_vars($parameters) : $parameters;

        $data = [];
        foreach ($params as $name => $value) {
            $data[$prefix.$name] = $value;
        }

        return new self(
            QueryString::compose(data: $data, separator: $separator, composeMode: $composeMode),
            Converter::fromRFC1738($separator)
        );
    }

    /**
     * Returns a new instance from the result of QueryString::parse.
     *
     * @param iterable<int, array{0:string, 1:string|null}> $pairs
     * @param non-empty-string $separator
     */
    public static function fromPairs(iterable $pairs, string $separator = '&', string $prefix = '', StringCoercionMode $coercionMode = StringCoercionMode::Native): self
    {
        $data = [];
        foreach ($pairs as $pair) {
            if (!is_array($pair) || !array_is_list($pair) || 2 !== count($pair)) {
                throw new SyntaxError('A pair must be a sequential array starting at `0` and containing two elements.');
            }

            $data[] = [$prefix.$pair[0], $pair[1]];
        }

        $converter = Converter::fromRFC3986($separator);

        return new self(QueryString::buildFromPairs($data, $converter, $coercionMode), $converter);
    }

    /**
     * Create a new instance from a URI object.
     */
    public static function fromUri(WhatWgUrl|Rfc3986Uri|BackedEnum|Stringable|string $uri): self
    {
        return match (true) {
            $uri instanceof Rfc3986Uri => new self($uri->getRawQuery(), Converter::fromRFC3986()),
            $uri instanceof WhatWgUrl => new self($uri->getQuery(), Converter::fromFormData()),
            $uri instanceof UriInterface  => new self($uri->getQuery(), Converter::fromRFC3986()),
            $uri instanceof BackedEnum => new self($uri, Converter::fromRFC3986()),
            default => new self(UriString::parse($uri)['query'], Converter::fromRFC3986()),
        };
    }

    /**
     * Returns a new instance.
     *
     * @param non-empty-string $separator
     */
    public static function fromRFC3986(BackedEnum|Stringable|string|null $query = null, string $separator = '&'): self
    {
        return new self($query, Converter::fromRFC3986($separator));
    }

    /**
     * Returns a new instance.
     *
     * @param non-empty-string $separator
     */
    public static function fromRFC1738(BackedEnum|Stringable|string|null $query = null, string $separator = '&'): self
    {
        return new self($query, Converter::fromRFC1738($separator));
    }

    /**
     * Returns a new instance.
     *
     * @param non-empty-string $separator
     */
    public static function fromFormData(BackedEnum|Stringable|string|null $query = null, string $separator = '&'): self
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

    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
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

    /**
     * Returns the total number of distinct keys.
     */
    public function countDistinctKeys(): int
    {
        return count(array_flip(array_column($this->pairs, 0)));
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

    public function first(string $key): ?string
    {
        $offset = $this->indexOf($key);

        return null === $offset ? null : $this->valueAt($offset);
    }

    public function last(string $key): ?string
    {
        $offset = $this->indexOf($key, -1);

        return null === $offset ? null : $this->valueAt($offset);
    }

    public function get(string $key): ?string
    {
        return $this->first($key);
    }

    public function getAll(string $key): array
    {
        return array_column(array_filter($this->pairs, fn (array $pair): bool => $key === $pair[0]), 1);
    }

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

    public function indexOfValue(?string $value, int $nth = 0): ?int
    {
        if ([] === $this->pairs) {
            return null;
        }

        if ($nth < 0) {
            $matchCount = 0;
            for ($offset = count($this->pairs) - 1; $offset >= 0; --$offset) {
                if ($this->pairs[$offset][1] === $value) {
                    if (++$matchCount === -$nth) {
                        return $offset;
                    }
                }
            }

            return null;
        }

        $matchCount = 0;
        foreach ($this->pairs as $offset => $pair) {
            if ($pair[1] === $value) {
                if ($nth === $matchCount) {
                    return $offset;
                }
                ++$matchCount;
            }
        }

        return null;
    }

    /**
     * Returns the key/value pair at the given numeric offset.
     *
     * Negative offsets are supported (counting from the end).
     *
     * @throws OutOfBoundsException If the offset is invalid
     *
     * @return array{0:string, 1:?string}
     */
    public function pair(int $offset): array
    {
        if ($offset < 0) {
            $offset += count($this->pairs);
        }

        return $this->pairs[$offset] ?? throw new OffsetOutOfBounds("Offset $offset does not exist");
    }

    /**
     * @throws OutOfBoundsException If the offset is invalid
     */
    public function valueAt(int $offset): ?string
    {
        return $this->pair($offset)[1];
    }

    /**
     * @throws OutOfBoundsException If the offset is invalid
     */
    public function keyAt(int $offset): string
    {
        return $this->pair($offset)[0];
    }

    public function getList(string $name): array
    {
        return $this->list[$name] ?? [];
    }

    public function hasList(string ...$names): bool
    {
        foreach ($names as $name) {
            if ([] === $this->getList($name)) {
                return false;
            }
        }

        return [] !== $names;
    }

    public function equals(mixed $value): bool
    {
        if (!$value instanceof BackedEnum && !$value instanceof Stringable && !is_string($value) && null !== $value) {
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
        if (count($this->pairs) === $this->countDistinctKeys()) {
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
     * @param callable(array{0:array-key, 1:mixed}, array-key=): bool $callback
     */
    public function filter(callable $callback): QueryInterface
    {
        $pairs = array_filter($this->pairs, $callback, ARRAY_FILTER_USE_BOTH);

        return $pairs === $this->pairs ? $this : self::fromPairs($pairs, $this->separator);
    }

    /**
     * @template TReturn
     *
     * @param callable(array{0:array-key, 1:mixed}, array-key=): TReturn $callback
     *
     * @return Iterator<TReturn>
     */
    public function map(callable $callback): Iterator
    {
        foreach ($this->pairs as $offset => $pair) {
            yield $offset => $callback($pair, $offset);
        }
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

    public function withoutEmptyPairs(): QueryInterface
    {
        return $this->filter(fn (array $pair): bool => '' !== $pair[0] && null !== $pair[1] && '' !== $pair[1]);
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

    public function withPair(string $key, array|BackedEnum|Stringable|string|int|float|bool|null $value, StringCoercionMode $coercionMode = StringCoercionMode::Native): QueryInterface
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        [] !== $value || throw new ValueError('The value list can not be empty.');

        $found = false;
        $reducer = static function (array $pairs, array $srcPair) use ($key, $value, &$found): array {
            if ($key !== $srcPair[0]) {
                $pairs[] = $srcPair;

                return $pairs;
            }

            if ($found) {
                return $pairs;
            }

            foreach ($value as $val) {
                $val = is_array($val) ? $value : [$val];
                foreach ($val as $v) {
                    $pairs[] = [$key, $v];
                }
            }

            $found = true;

            return $pairs;
        };

        $pairs = array_reduce($this->pairs, $reducer, []);
        if (!$found) {
            foreach ($value as $val) {
                $pairs[] = [$key, $val];
            }
        }

        return match ($this->pairs) {
            $pairs => $this,
            default => self::fromPairs($pairs, $this->separator, coercionMode: $coercionMode),
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

    public function merge(BackedEnum|Stringable|string|null $query, StringCoercionMode $coercionMode = StringCoercionMode::Native): QueryInterface
    {
        $pairs = $this->pairs;
        foreach (QueryString::parseFromValue(self::filterComponent($query), Converter::fromRFC3986($this->separator)) as $pair) {
            $pairs = $this->addPair($pairs, $pair);
        }

        return match ($this->pairs) {
            $pairs => $this,
            default => self::fromPairs($pairs, $this->separator, coercionMode: $coercionMode),
        };
    }

    public function withoutPairByKey(string ...$keys): QueryInterface
    {
        if ([] === $keys) {
            return $this;
        }

        $keysToRemove = array_intersect($keys, array_column($this->pairs, 0));
        if ([] === $keysToRemove) {
            return $this;
        }

        return $this->filter(fn (array $pair): bool => !in_array($pair[0], $keysToRemove, true));
    }

    public function withoutPairByValue(array|BackedEnum|Stringable|string|int|float|bool|null $values, StringCoercionMode $coercionMode = StringCoercionMode::Native): QueryInterface
    {
        if (!is_array($values)) {
            $values = [$values];
        }

        if ([] === $values) {
            return $this;
        }

        $values = array_map($coercionMode->coerce(...), $values);

        return $this->filter(fn (array $pair) => !in_array($pair[1], $values, true));
    }

    public function withoutPairByKeyValue(string $key, BackedEnum|Stringable|string|int|float|bool|null $value, StringCoercionMode $coercionMode = StringCoercionMode::Native): QueryInterface
    {
        $pair = [$key, $coercionMode->coerce($value)];

        return $this->filter(fn (array $currentPair) => $currentPair !== $pair);
    }

    public function appendTo(string $key, array|BackedEnum|Stringable|string|int|float|bool|null $value, StringCoercionMode $coercionMode = StringCoercionMode::Native): QueryInterface
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        [] !== $value || throw new ValueError('Missing values to append');

        $converter = function (iterable $values) use ($key) {
            foreach ($values as $value) {
                yield [$key, $value];
            }
        };

        return self::fromPairs([...$this->pairs, ...$converter($value)], $this->separator, coercionMode: $coercionMode);
    }

    public function appendList(
        string $name,
        array $values,
        QueryComposeMode $composeMode = QueryComposeMode::Native
    ): QueryInterface {
        return $this->append(
            QueryString::composeFromValue(
                data: [$name => $values],
                converter: Converter::fromRFC3986($this->separator),
                composeMode: $composeMode,
            )
        );
    }

    public function append(BackedEnum|Stringable|string|null $query, StringCoercionMode $coercionMode = StringCoercionMode::Native): QueryInterface
    {
        return null === $query ? $this : self::fromPairs(
            array_filter(
                array_merge($this->pairs, QueryString::parseFromValue($query, Converter::fromRFC3986($this->separator))),
                static fn (array $pair): bool => '' !== $pair[0] || null !== $pair[1]
            ),
            $this->separator,
            coercionMode: $coercionMode,
        );
    }

    public function prepend(BackedEnum|Stringable|string|null $query, StringCoercionMode $coercionMode = StringCoercionMode::Native): QueryInterface
    {
        return Query::new($query)->append($this, $coercionMode);
    }

    /**
     * Replace a pair based on its offset.
     */
    public function replace(int $offset, string $key, BackedEnum|Stringable|string|int|float|bool|null $value, StringCoercionMode $coercionMode = StringCoercionMode::Native): QueryInterface
    {
        $index = $offset < 0 ? count($this->pairs) + $offset : $offset;
        $pair = $this->pairs[$index] ?? [];
        [] !== $pair || throw new ValueError('The given offset "'.$offset.'" does not exist');

        $newPair = [$key, $coercionMode->coerce($value)];
        if ($pair === $newPair) {
            return $this;
        }

        $newPairs = $this->pairs;
        $newPairs[$index] = $newPair;

        return self::fromPairs($newPairs, $this->separator);
    }

    public function withList(
        string $name,
        array $values,
        QueryComposeMode $composeMode = QueryComposeMode::Native
    ): QueryInterface {
        if ([] === $values) {
            return $this;
        }

        $data = QueryString::parseFromValue(
            QueryString::composeFromValue(
                data: [$name => $values],
                converter: Converter::fromRFC3986($this->separator),
                composeMode: $composeMode,
            ),
            Converter::fromRFC3986($this->separator),
        );
        $regexp = ','.preg_quote($name, ',').'(\[.*\].*),';
        $isRemoved = false;

        $pairs = array_reduce($this->pairs, function (array $pairs, array $pair) use ($data, $regexp, &$isRemoved): array {
            if (1 !== preg_match($regexp, $pair[0])) {
                $pairs[] = $pair;

                return $pairs;
            }

            if ($isRemoved) {
                return $pairs;
            }

            foreach ($data as $arr) {
                $pairs[] = $arr;
            }
            $isRemoved = true;

            return $pairs;
        }, []);

        if (!$isRemoved) {
            $pairs = array_merge($pairs, $data);
        }

        return $this->pairs === $pairs ? $this : self::fromPairs($pairs, $this->separator);
    }

    public function withoutList(string ...$names): QueryInterface
    {
        if ([] === $names) {
            return $this;
        }

        $mapper = static fn (string $offset): string => preg_quote($offset, ',').'(\[.*\].*)';
        $regexp = ',^('.implode('|', array_map($mapper, $names)).')?$,';

        return $this->filter(fn (array $pair): bool => 1 !== preg_match($regexp, $pair[0]));
    }

    public function onlyLists(): QueryInterface
    {
        return $this->filter(static fn (array $pair): bool => 1 === preg_match(self::REGXP_FILTER_LIST, $pair[0]));
    }

    public function withoutLists(): QueryInterface
    {
        return [] === $this->list ? $this : $this->filter(static fn (array $pair): bool => 1 !== preg_match(self::REGXP_FILTER_LIST, $pair[0]));
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    public function mergeParameters(object|array $parameter, string $prefix = '', QueryComposeMode $composeMode = QueryComposeMode::Native): self
    {
        $params = is_object($parameter) ? get_object_vars($parameter) : $parameter;
        $data = [];
        foreach ($params as $name => $value) {
            $data[$prefix.$name] = $value;
        }

        return in_array($data, [$this->parameters, []], true) ? $this : new self(
            QueryString::compose(data: array_merge($this->parameters, $data), separator: $this->separator, composeMode: $composeMode),
            Converter::fromRFC1738($this->separator)
        );
    }

    public function replaceParameter(string $name, mixed $parameter, QueryComposeMode $composeMode = QueryComposeMode::Native): self
    {
        $this->has($name) || $this->hasList($name) || throw new ValueError('The specified name does not exist');
        if ($parameter === $this->parameters[$name]) {
            return $this;
        }

        $parameters = $this->parameters;
        $parameters[$name] = $parameter;

        return new self(
            QueryString::compose(data: $parameters, separator: $this->separator, composeMode: $composeMode),
            Converter::fromRFC1738($this->separator)
        );
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

    public function withoutParameters(string ...$names): QueryInterface
    {
        if ([] === $names) {
            return $this;
        }

        $mapper = static fn (string $offset): string => preg_quote($offset, ',').'(\[.*\].*)?';
        $regexp = ',^('.implode('|', array_map($mapper, $names)).')?$,';

        return $this->filter(fn (array $pair): bool => 1 !== preg_match($regexp, $pair[0]));
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
        return null === $key ? $this->parameters : $this->parameters[$key] ?? null;
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
        return $this->withoutPairByKey(...$names)->withoutList(...$names);
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

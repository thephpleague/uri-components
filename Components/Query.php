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

use Iterator;
use League\Uri\Contracts\QueryInterface;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\QueryString;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use Traversable;
use function array_column;
use function array_count_values;
use function array_filter;
use function array_flip;
use function array_intersect;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function http_build_query;
use function implode;
use function is_bool;
use function iterator_to_array;
use function preg_match;
use function preg_quote;
use function preg_replace;
use const PHP_QUERY_RFC1738;
use const PHP_QUERY_RFC3986;

final class Query extends Component implements QueryInterface
{
    /** @var array<int, array{0:string, 1:string|null}> */
    private readonly array $pairs;
    /** @var non-empty-string */
    private readonly string $separator;
    private readonly ?array $params;

    /**
     * Returns a new instance.
     *
     * @param non-empty-string $separator
     */
    private function __construct(
        UriComponentInterface|Stringable|int|string|null $query,
        string $separator = '&',
        int $enc_type = PHP_QUERY_RFC3986
    ) {
        $this->pairs = QueryString::parse($query, $separator, $enc_type);
        $this->params = QueryString::convert($this->pairs);
        $this->separator = $separator;
    }

    public static function new(): self
    {
        return new self(null);
    }

    /**
     * Returns a new instance from the result of PHP's parse_str.
     *
     * @param non-empty-string $separator
     */
    public static function fromParams(array|object $params, string $separator = '&'): self
    {
        if ($params instanceof QueryInterface) {
            /** @var array $queryParams */
            $queryParams = $params->params();

            return new self(
                http_build_query($queryParams, '', $separator, PHP_QUERY_RFC3986),
                $separator,
                PHP_QUERY_RFC3986
            );
        }

        if ($params instanceof Traversable) {
            $params = iterator_to_array($params);
        }

        if ([] === $params) {
            return new self(null, $separator, PHP_QUERY_RFC3986);
        }

        return new self(
            http_build_query($params, '', $separator, PHP_QUERY_RFC3986),
            $separator,
            PHP_QUERY_RFC3986
        );
    }

    /**
     * Returns a new instance from the result of QueryString::parse.
     *
     * @param iterable<int, array{0:string, 1:string|null}> $pairs
     * @param non-empty-string                              $separator
     */
    public static function fromPairs(iterable $pairs, string $separator = '&'): self
    {
        return new self(QueryString::build($pairs, $separator), $separator, PHP_QUERY_RFC3986);
    }

    /**
     * Create a new instance from a URI object.
     */
    public static function fromUri(Psr7UriInterface|UriInterface $uri): self
    {
        $component = $uri->getQuery();

        return match (true) {
            '' === $component && $uri instanceof Psr7UriInterface => new self(null),
            default => new self($component),
        };
    }

    /**
     * Returns a new instance.
     *
     * @param non-empty-string $separator
     */
    public static function fromRFC3986(UriComponentInterface|Stringable|int|string|null $query, string $separator = '&'): self
    {
        return new self($query, $separator, PHP_QUERY_RFC3986);
    }

    /**
     * Returns a new instance.
     *
     * @param non-empty-string $separator
     */
    public static function fromRFC1738(UriComponentInterface|Stringable|int|string|null $query, string $separator = '&'): self
    {
        return new self($query, $separator, PHP_QUERY_RFC1738);
    }

    public function getSeparator(): string
    {
        return $this->separator;
    }

    public function value(): ?string
    {
        return $this->toRFC3986();
    }

    public function getUriComponent(): string
    {
        if ([] === $this->pairs) {
            return '';
        }

        return '?'.$this->value();
    }

    public function toRFC1738(): ?string
    {
        return QueryString::build($this->pairs, $this->separator, PHP_QUERY_RFC1738);
    }

    public function toRFC3986(): ?string
    {
        return QueryString::build($this->pairs, $this->separator);
    }

    public function jsonSerialize(): ?string
    {
        return $this->toRFC1738();
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

    public function has(string $key): bool
    {
        return isset(array_flip(array_column($this->pairs, 0))[$key]);
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

    public function getAll(string $key): array
    {
        return array_column(array_filter($this->pairs, fn (array $pair): bool => $key === $pair[0]), 1);
    }

    public function params(?string $key = null)
    {
        return match (true) {
            null === $key => $this->params,
            default => $this->params[$key] ?? null,
        };
    }

    public function withSeparator(string $separator): QueryInterface
    {
        return match (true) {
            $separator === $this->separator => $this,
            '' === $separator => throw new SyntaxError('The separator character can not be the empty string.'),
            default => self::fromPairs($this->pairs, $separator),
        };
    }

    public function sort(): QueryInterface
    {
        if (count($this->pairs) === count(array_count_values(array_column($this->pairs, 0)))) {
            return $this;
        }

        /** @var array<int, array{0:string, 1:string|null}> $pairs */
        $pairs = array_merge(...array_values(array_reduce($this->pairs, $this->reducePairs(...), [])));
        if ($pairs === $this->pairs) {
            return $this;
        }

        return self::fromPairs($pairs);
    }

    /**
     * Reduce pairs to help sorting them according to their keys.
     */
    private function reducePairs(array $pairs, array $pair): array
    {
        $pairs[$pair[0]] = $pairs[$pair[0]] ?? [];
        $pairs[$pair[0]][] = $pair;

        return $pairs;
    }

    public function withoutDuplicates(): QueryInterface
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
     * Adds a query pair only if it is not already present in a given array.
     */
    private function removeDuplicates(array $pairs, array $pair): array
    {
        if (!in_array($pair, $pairs, true)) {
            $pairs[] = $pair;
        }

        return $pairs;
    }

    public function withoutEmptyPairs(): QueryInterface
    {
        $pairs = array_filter($this->pairs, $this->filterEmptyPair(...));
        if ($pairs === $this->pairs) {
            return $this;
        }

        return self::fromPairs($pairs);
    }

    /**
     * Empty Pair filtering.
     */
    private function filterEmptyPair(array $pair): bool
    {
        return '' !== $pair[0] && null !== $pair[1] && '' !== $pair[1];
    }

    public function withoutNumericIndices(): QueryInterface
    {
        $pairs = array_map($this->encodeNumericIndices(...), $this->pairs);
        if ($pairs === $this->pairs) {
            return $this;
        }

        return self::fromPairs($pairs, $this->separator);
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

    public function withPair(string $key, UriComponentInterface|Stringable|int|string|bool|null $value): QueryInterface
    {
        $pairs = $this->addPair($this->pairs, [$key, $this->filterPair($value)]);
        if ($pairs === $this->pairs) {
            return $this;
        }

        return self::fromPairs($pairs, $this->separator);
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

    public function merge(UriComponentInterface|Stringable|int|string|bool|null $query): QueryInterface
    {
        $pairs = $this->pairs;
        foreach (QueryString::parse(self::filterComponent($query), $this->separator) as $pair) {
            $pairs = $this->addPair($pairs, $pair);
        }

        if ($pairs === $this->pairs) {
            return $this;
        }

        return self::fromPairs($pairs, $this->separator);
    }

    /**
     * Validate the given pair.
     *
     * To be valid the pair must be the null value, a scalar or a collection of scalar and null values.
     */
    private function filterPair(UriComponentInterface|Stringable|int|string|bool|null $value): ?string
    {
        if ($value instanceof UriComponentInterface) {
            return $value->value();
        }

        if (null === $value) {
            return null;
        }

        if (is_bool($value)) {
            return true === $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    public function withoutPair(string ...$keys): QueryInterface
    {
        if ([] === $keys) {
            return $this;
        }

        $keys_to_remove = array_intersect($keys, array_column($this->pairs, 0));
        if ([] === $keys_to_remove) {
            return $this;
        }

        return self::fromPairs(
            array_filter($this->pairs, static fn (array $pair): bool => !in_array($pair[0], $keys_to_remove, true)),
            $this->separator
        );
    }

    public function appendTo(string $key, Stringable|string|int|bool|null $value): QueryInterface
    {
        return self::fromPairs([...$this->pairs, [$key, $this->filterPair($value)]], $this->separator);
    }

    public function append(Stringable|string|int|null|bool $query): QueryInterface
    {
        if ($query instanceof UriComponentInterface) {
            $query = $query->value();
        }

        $pairs = array_merge($this->pairs, QueryString::parse($query, $this->separator));
        if ($pairs === $this->pairs) {
            return $this;
        }

        return self::fromPairs(array_filter($pairs, $this->filterEmptyValue(...)), $this->separator);
    }

    /**
     * Empty Pair filtering.
     */
    private function filterEmptyValue(array $pair): bool
    {
        return '' !== $pair[0] || null !== $pair[1];
    }

    public function withoutParam(string ...$keys): QueryInterface
    {
        if ([] === $keys) {
            return $this;
        }

        $mapper = static fn (string $offset): string => preg_quote($offset, ',').'(\[.*\].*)?';
        $regexp = ',^('.implode('|', array_map($mapper, $keys)).')?$,';
        $filter = fn (array $pair): bool => 1 !== preg_match($regexp, $pair[0]);

        $pairs = array_filter($this->pairs, $filter);
        if ($pairs === $this->pairs) {
            return $this;
        }

        return self::fromPairs($pairs, $this->separator);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Query::fromParams()
     *
     * @codeCoverageIgnore
     *
     * Returns a new instance from the result of PHP's parse_str.
     *
     * @param non-empty-string $separator
     */
    public static function createFromParams(array|object $params, string $separator = '&'): self
    {
        return self::fromParams($params, $separator);
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
     * @param non-empty-string                              $separator
     */
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
    public static function createFromRFC3986(UriComponentInterface|Stringable|int|string|null $query, string $separator = '&'): self
    {
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
    public static function createFromRFC1738(UriComponentInterface|Stringable|int|string|null $query, string $separator = '&'): self
    {
        return self::fromRFC1738($query, $separator);
    }
}

<?php

/**
 * League.Uri (http://uri.thephpleague.com/components)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.2
 * @link       https://github.com/thephpleague/uri-components
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
use Traversable;
use TypeError;
use function array_column;
use function array_count_values;
use function array_filter;
use function array_flip;
use function array_intersect;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function gettype;
use function http_build_query;
use function implode;
use function is_array;
use function is_object;
use function is_scalar;
use function iterator_to_array;
use function method_exists;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function sprintf;
use const PHP_QUERY_RFC1738;
use const PHP_QUERY_RFC3986;

final class Query extends Component implements QueryInterface
{
    /**
     * @var array<int, array{0:string, 1:string|null}>
     */
    private $pairs;

    /**
     * @var string
     */
    private $separator;

    /**
     * @var array|null
     */
    private $params;

    /**
     * Returns a new instance.
     *
     * @param mixed|null $query
     */
    private function __construct($query = null, string $separator = '&', int $enc_type = PHP_QUERY_RFC3986)
    {
        $this->pairs = QueryString::parse($query, $separator, $enc_type);
        $this->separator = $separator;
    }

    /**
     * {@inheritDoc}
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self();
        $instance->pairs = $properties['pairs'];
        $instance->separator = $properties['separator'];

        return $instance;
    }

    /**
     * Returns a new instance from the result of PHP's parse_str.
     *
     * @param mixed|iterable $params
     */
    public static function createFromParams($params = [], string $separator = '&'): self
    {
        if ($params instanceof self) {
            return new self(
                http_build_query($params->params(), '', $separator, PHP_QUERY_RFC3986),
                $separator,
                PHP_QUERY_RFC3986
            );
        }

        if ($params instanceof Traversable) {
            $params = iterator_to_array($params, true);
        }

        if ([] === $params) {
            return new self(null, $separator, PHP_QUERY_RFC3986);
        }

        if (!is_array($params) && !is_object($params)) {
            throw new TypeError(sprintf('The parameter is expected to be iterable or an object with public properties, `%s` given.', gettype($params)));
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
     */
    public static function createFromPairs(iterable $pairs = [], string $separator = '&'): self
    {
        $query = QueryString::build($pairs, $separator, PHP_QUERY_RFC3986);

        return new self($query, $separator, PHP_QUERY_RFC3986);
    }

    /**
     * Create a new instance from a URI object.
     *
     * @param mixed $uri an URI object
     *
     * @throws TypeError If the URI object is not supported
     */
    public static function createFromUri($uri): self
    {
        if ($uri instanceof UriInterface) {
            return new self($uri->getQuery());
        }

        if (!$uri instanceof Psr7UriInterface) {
            throw new TypeError(sprintf('The object must implement the `%s` or the `%s` interface.', Psr7UriInterface::class, UriInterface::class));
        }

        $component = $uri->getQuery();
        if ('' === $component) {
            return new self();
        }

        return new self($component);
    }

    /**
     * Returns a new instance.
     *
     * @param mixed|null $query a query in RFC3986 form
     */
    public static function createFromRFC3986($query = null, string $separator = '&'): self
    {
        return new self($query, $separator, PHP_QUERY_RFC3986);
    }

    /**
     * Returns a new instance.
     *
     * @param mixed|null $query a query in RFC1738 form
     */
    public static function createFromRFC1738($query = null, string $separator = '&'): self
    {
        return new self($query, $separator, PHP_QUERY_RFC1738);
    }

    /**
     * {@inheritDoc}
     */
    public function getSeparator(): string
    {
        return $this->separator;
    }

    /**
     * {@inheritDoc}
     */
    public function getContent(): ?string
    {
        return QueryString::build($this->pairs, $this->separator, PHP_QUERY_RFC3986);
    }

    /**
     * {@inheritDoc}
     */
    public function getUriComponent(): string
    {
        if ([] === $this->pairs) {
            return '';
        }

        return '?'.$this->getContent();
    }

    /**
     * {@inheritDoc}
     */
    public function toRFC1738(): ?string
    {
        return QueryString::build($this->pairs, $this->separator, PHP_QUERY_RFC1738);
    }

    /**
     * {@inheritDoc}
     */
    public function toRFC3986(): ?string
    {
        return $this->getContent();
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): ?string
    {
        return $this->toRFC1738();
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return count($this->pairs);
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): Iterator
    {
        foreach ($this->pairs as $pair) {
            yield $pair;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function pairs(): iterable
    {
        foreach ($this->pairs as $pair) {
            yield $pair[0] => $pair[1];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return isset(array_flip(array_column($this->pairs, 0))[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key): ?string
    {
        foreach ($this->pairs as $pair) {
            if ($key === $pair[0]) {
                return $pair[1];
            }
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getAll(string $key): array
    {
        $filter = static function (array $pair) use ($key): bool {
            return $key === $pair[0];
        };

        return array_column(array_filter($this->pairs, $filter), 1);
    }

    /**
     * {@inheritDoc}
     */
    public function params(?string $key = null)
    {
        $this->params = $this->params ?? QueryString::convert($this->pairs);
        if (null === $key) {
            return $this->params;
        }

        return $this->params[$key] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function withSeparator(string $separator): QueryInterface
    {
        if ($separator === $this->separator) {
            return $this;
        }

        if ('' === $separator) {
            throw new SyntaxError('The separator character can not be the empty string.');
        }

        $clone = clone $this;
        $clone->separator = $separator;

        return $clone;
    }

    /**
     * {@inheritDoc}
     */
    public function withContent($content): UriComponentInterface
    {
        $content = self::filterComponent($content);
        if ($content === $this->getContent()) {
            return $this;
        }

        return new self($content);
    }

    /**
     * {@inheritDoc}
     */
    public function sort(): QueryInterface
    {
        if (count($this->pairs) === count(array_count_values(array_column($this->pairs, 0)))) {
            return $this;
        }

        /** @var array<int, array{0:string, 1:string|null}> $pairs */
        $pairs = array_merge(...array_values(array_reduce($this->pairs, [$this, 'reducePairs'], [])));
        if ($pairs === $this->pairs) {
            return $this;
        }

        $clone = clone $this;
        $clone->pairs = $pairs;

        return $clone;
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

    /**
     * {@inheritDoc}
     */
    public function withoutDuplicates(): QueryInterface
    {
        if (count($this->pairs) === count(array_count_values(array_column($this->pairs, 0)))) {
            return $this;
        }

        $pairs = array_reduce($this->pairs, [$this, 'removeDuplicates'], []);
        if ($pairs === $this->pairs) {
            return $this;
        }

        $new = new self();
        $new->pairs = $pairs;
        $new->separator = $this->separator;

        return $new;
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

    /**
     * {@inheritDoc}
     */
    public function withoutEmptyPairs(): QueryInterface
    {
        $pairs = array_filter($this->pairs, [$this, 'filterEmptyPair']);
        if ($pairs === $this->pairs) {
            return $this;
        }

        $new = new self();
        $new->pairs = $pairs;
        $new->separator = $this->separator;

        return $new;
    }

    /**
     * Empty Pair filtering.
     */
    private function filterEmptyPair(array $pair): bool
    {
        return '' !== $pair[0] && null !== $pair[1] && '' !== $pair[1];
    }

    /**
     * {@inheritDoc}
     */
    public function withoutNumericIndices(): QueryInterface
    {
        $pairs = array_map([$this, 'encodeNumericIndices'], $this->pairs);
        if ($pairs === $this->pairs) {
            return $this;
        }

        $new = new self();
        $new->pairs = $pairs;
        $new->separator = $this->separator;

        return $new;
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
        static $regexp = ',\[\d+\],';

        $pair[0] = (string) preg_replace($regexp, '[]', $pair[0]);

        return $pair;
    }

    /**
     * @param mixed $value the pair value.
     */
    public function withPair(string $key, $value): QueryInterface
    {
        $pairs = $this->addPair($this->pairs, [$key, $this->filterPair($value)]);
        if ($pairs === $this->pairs) {
            return $this;
        }

        $new = new self();
        $new->pairs = $pairs;
        $new->separator = $this->separator;

        return $new;
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

    /**
     * @param mixed $query the query to be merge with.
     */
    public function merge($query): QueryInterface
    {
        $pairs = $this->pairs;
        foreach (QueryString::parse(self::filterComponent($query), $this->separator, PHP_QUERY_RFC3986) as $pair) {
            $pairs = $this->addPair($pairs, $pair);
        }

        if ($pairs === $this->pairs) {
            return $this;
        }

        $new = new self();
        $new->pairs = $pairs;
        $new->separator = $this->separator;

        return $new;
    }

    /**
     * Validate the given pair.
     *
     * To be valid the pair must be the null value, a scalar or a collection of scalar and null values.
     *
     * @param mixed|null $value
     *
     * @throws TypeError if the value type is invalid
     */
    private function filterPair($value): ?string
    {
        if ($value instanceof UriComponentInterface) {
            return $value->getContent();
        }

        if (null === $value) {
            return $value;
        }

        if (is_bool($value)) {
            return true === $value ? 'true' : 'false';
        }

        if (is_scalar($value) || method_exists($value, '__toString')) {
            return (string) $value;
        }

        throw new TypeError('The submitted value is invalid.');
    }

    /**
     * {@inheritDoc}
     */
    public function withoutPair(string ...$keys): QueryInterface
    {
        if ([] === $keys) {
            return $this;
        }

        $keys_to_remove = array_intersect($keys, array_column($this->pairs, 0));
        if ([] === $keys_to_remove) {
            return $this;
        }

        $filter = static function (array $pair) use ($keys_to_remove): bool {
            return !in_array($pair[0], $keys_to_remove, true);
        };

        $new = new self();
        $new->pairs = array_filter($this->pairs, $filter);
        $new->separator = $this->separator;

        return $new;
    }

    /**
     * {@inheritDoc}
     *
     * @param mixed|null $value
     */
    public function appendTo(string $key, $value): QueryInterface
    {
        $pair = [$key, $this->filterPair($value)];
        $new = new self();
        $new->pairs = $this->pairs;
        $new->pairs[] = $pair;
        $new->separator = $this->separator;

        return $new;
    }

    /**
     * @param mixed $query the query to append
     */
    public function append($query): QueryInterface
    {
        if ($query instanceof UriComponentInterface) {
            $query = $query->getContent();
        }

        $pairs = array_merge($this->pairs, QueryString::parse($query, $this->separator, PHP_QUERY_RFC3986));
        if ($pairs === $this->pairs) {
            return $this;
        }

        $new = new self();
        $new->separator = $this->separator;
        $new->pairs = array_filter($pairs, [$this, 'filterEmptyValue']);

        return $new;
    }

    /**
     * Empty Pair filtering.
     */
    private function filterEmptyValue(array $pair): bool
    {
        return '' !== $pair[0] || null !== $pair[1];
    }

    /**
     * {@inheritDoc}
     */
    public function withoutParam(string ...$keys): QueryInterface
    {
        if ([] === $keys) {
            return $this;
        }

        $mapper = static function (string $offset): string {
            return preg_quote($offset, ',').'(\[.*\].*)?';
        };

        $regexp = ',^('.implode('|', array_map($mapper, $keys)).')?$,';
        $filter = static function (array $pair) use ($regexp): bool {
            return 1 !== preg_match($regexp, $pair[0]);
        };

        $pairs = array_filter($this->pairs, $filter);
        if ($pairs === $this->pairs) {
            return $this;
        }

        $new = new self();
        $new->separator = $this->separator;
        $new->pairs = $pairs;

        return $new;
    }
}

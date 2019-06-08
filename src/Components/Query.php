<?php

/**
 * League.Uri (http://uri.thephpleague.com/components)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.0
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\Components;

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
     * @var array
     */
    private $pairs;

    /**
     * @var string
     */
    private $separator;

    /**
     * Returns a new instance.
     *
     * @param mixed|null $query
     */
    public function __construct($query = null, int $enc_type = PHP_QUERY_RFC3986, string $separator = '&')
    {
        $this->separator = $this->filterSeparator($separator);
        $this->pairs = QueryString::parse($query, $separator, $enc_type);
    }

    /**
     * Filter the incoming separator.
     *
     * @throws SyntaxError if the separator is invalid
     */
    private function filterSeparator(string $separator): string
    {
        if ('' === $separator) {
            throw new SyntaxError('The separator character can not be the empty string.');
        }

        return $separator;
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
    public static function createFromParams($params, string $separator = '&'): self
    {
        if ($params instanceof self) {
            return new self(
                http_build_query($params->toParams(), '', $separator, PHP_QUERY_RFC3986),
                PHP_QUERY_RFC3986,
                $separator
            );
        }

        if ($params instanceof Traversable) {
            $params = iterator_to_array($params, true);
        }

        if ([] === $params) {
            return new self(null, PHP_QUERY_RFC3986, $separator);
        }

        if (!is_array($params) && !is_object($params)) {
            throw new TypeError(sprintf('The parameter is expected to be iterable or an Object `%s` given', gettype($params)));
        }

        return new self(
            http_build_query($params, '', $separator, PHP_QUERY_RFC3986),
            PHP_QUERY_RFC3986,
            $separator
        );
    }

    /**
     * Returns a new instance from the result of QueryString::parse.
     */
    public static function createFromPairs(iterable $pairs, string $separator = '&'): self
    {
        return new self(QueryString::build($pairs, $separator, PHP_QUERY_RFC3986), PHP_QUERY_RFC3986, $separator);
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

        if ($uri instanceof Psr7UriInterface) {
            $component = $uri->getQuery();
            if ('' === $component) {
                $component = null;
            }

            return new self($component);
        }

        throw new TypeError(sprintf('The object must implement the `%s` or the `%s`', Psr7UriInterface::class, UriInterface::class));
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
    public function getIterator(): iterable
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
    public function toParams(): array
    {
        return QueryString::convert($this->pairs);
    }

    /**
     * {@inheritDoc}
     */
    public function withSeparator(string $separator): QueryInterface
    {
        if ($separator === $this->separator) {
            return $this;
        }

        $clone = clone $this;
        $clone->separator = $this->filterSeparator($separator);

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

        $clone = clone $this;
        $clone->pairs = $pairs;

        return $clone;
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

        $clone = clone $this;
        $clone->pairs = $pairs;

        return $clone;
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

        $clone = clone $this;
        $clone->pairs = $pairs;

        return $clone;
    }

    /**
     * Remove numeric indices from pairs.
     */
    private function encodeNumericIndices(array $pair): array
    {
        static $regexp = ',\[\d+\],';

        $pair[0] = preg_replace($regexp, '[]', $pair[0]);

        return $pair;
    }

    /**
     * @inheritDoc
     *
     * @param mixed|null $value
     */
    public function withPair(string $key, $value): QueryInterface
    {
        $pairs = $this->addPair($this->pairs, [$key, $this->filterPair($value)]);
        if ($pairs === $this->pairs) {
            return $this;
        }

        $clone = clone $this;
        $clone->pairs = $pairs;

        return $clone;
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
     * @inheritDoc
     *
     * @param mixed|null $query
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

        $clone = clone $this;
        $clone->pairs = $pairs;

        return $clone;
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
    public function withoutPair(string $key, string ...$keys): QueryInterface
    {
        $keys[] = $key;
        $keys_to_remove = array_intersect($keys, array_column($this->pairs, 0));
        if ([] === $keys_to_remove) {
            return $this;
        }

        $filter = static function (array $pair) use ($keys_to_remove): bool {
            return !in_array($pair[0], $keys_to_remove, true);
        };

        $clone = clone $this;
        $clone->pairs = array_filter($this->pairs, $filter);

        return $clone;
    }

    /**
     * {@inheritDoc}
     *
     * @param mixed|null $value
     */
    public function appendTo(string $key, $value): QueryInterface
    {
        $clone = clone $this;
        $clone->pairs[] = [$key, $this->filterPair($value)];

        return $clone;
    }

    /**
     * @inheritDoc
     *
     * @param mixed|null $query
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

        $clone = clone $this;
        $clone->pairs = array_filter($pairs, [$this, 'filterEmptyValue']);

        return $clone;
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
    public function withoutParam(string $offset, string ...$offsets): QueryInterface
    {
        $offsets[] = $offset;
        $mapper = static function (string $offset): string {
            return preg_quote($offset, ',').'(\[.*\].*)?';
        };

        $regexp = ',^('.implode('|', array_map($mapper, $offsets)).')?$,';
        $filter = static function (array $pair) use ($regexp): bool {
            return 1 !== preg_match($regexp, $pair[0]);
        };

        $pairs = array_filter($this->pairs, $filter);
        if ($pairs === $this->pairs) {
            return $this;
        }

        $clone = clone $this;
        $clone->pairs = $pairs;

        return $clone;
    }
}

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

namespace League\Uri\Component;

use Countable;
use Iterator;
use IteratorAggregate;
use League\Uri\ComponentInterface;
use League\Uri\Exception\MalformedUriComponent;
use League\Uri\Parser\QueryString;
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

final class Query extends Component implements Countable, IteratorAggregate
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
     * Returns a new instance from the result of PHP's parse_str.
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

        if (is_object($params)) {
            return new self(
                http_build_query($params, '', $separator, PHP_QUERY_RFC3986),
                PHP_QUERY_RFC3986,
                $separator
            );
        }

        if ([] === $params) {
            return new self(null, PHP_QUERY_RFC3986, $separator);
        }

        if (is_array($params)) {
            return new self(
                http_build_query($params, '', $separator, PHP_QUERY_RFC3986),
                PHP_QUERY_RFC3986,
                $separator
            );
        }

        throw new TypeError(sprintf('The parameter is expected to be iterable or an Object `%s` given', gettype($params)));
    }

    /**
     * Returns a new instance from the result of QueryString::parse.
     */
    public static function createFromPairs(iterable $pairs, string $separator = '&'): self
    {
        if ($pairs instanceof self) {
            return $pairs->withSeparator($separator);
        }

        return new self(QueryString::build($pairs, $separator, PHP_QUERY_RFC3986), PHP_QUERY_RFC3986, $separator);
    }

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self();
        $instance->pairs = $properties['pairs'];
        $instance->separator = $properties['separator'];

        return $instance;
    }

    /**
     * Returns a new instance.
     *
     * @param null|mixed $query
     */
    public function __construct($query = null, int $enc_type = PHP_QUERY_RFC3986, string $separator = '&')
    {
        $this->separator = $this->filterSeparator($separator);
        $this->pairs = QueryString::parse($query, $separator, $enc_type);
    }

    /**
     * Filter the incoming separator.
     *
     * @throws MalformedUriComponent if the separator is invalid
     */
    private function filterSeparator(string $separator): string
    {
        if ('=' !== $separator) {
            return $separator;
        }

        throw new MalformedUriComponent(sprintf('Invalid separator character `%s`', $separator));
    }

    /**
     * Returns the query separator.
     */
    public function getSeparator(): string
    {
        return $this->separator;
    }

    /**
     * Returns the RFC3986 encoded query.
     */
    public function getContent(): ?string
    {
        return QueryString::build($this->pairs, $this->separator, PHP_QUERY_RFC3986);
    }

    /**
     * {@inheritdoc}
     */
    public function getUriComponent(): string
    {
        return ([] === $this->pairs ? '' : '?').$this->getContent();
    }

    /**
     * Returns the RFC1738 encoded query.
     */
    public function toRFC1738(): ?string
    {
        return QueryString::build($this->pairs, $this->separator, PHP_QUERY_RFC1738);
    }

    /**
     * Returns the RFC3986 encoded query.
     *
     * @see ::getContent
     */
    public function toRFC3986(): ?string
    {
        return $this->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize(): ?string
    {
        return $this->toRFC1738();
    }

    /**
     * Returns the number of key/value pairs present in the object.
     */
    public function count(): int
    {
        return count($this->pairs);
    }

    /**
     * Returns an iterator allowing to go through all key/value pairs contained in this object.
     *
     * The pair is represented as an array where the first value is the pair key
     * and the second value the pair value.
     *
     * The key of each pair is a string
     * The value of each pair is a scalar or the null value
     *
     * @return Iterator
     */
    public function getIterator(): iterable
    {
        foreach ($this->pairs as $pair) {
            yield $pair;
        }
    }

    /**
     * Returns an iterator allowing to go through all key/value pairs contained in this object.
     *
     * The return type is as a Iterator where its offset is the pair key and its value the pair value.
     *
     * The key of each pair is a string
     * The value of each pair is a scalar or the null value
     *
     * @return Iterator
     */
    public function pairs()
    {
        foreach ($this->pairs as $pair) {
            yield $pair[0] => $pair[1];
        }
    }

    /**
     * Tell whether a parameter with a specific name exists.
     *
     * @see https://url.spec.whatwg.org/#dom-urlsearchparams-has
     */
    public function has(string $key): bool
    {
        return isset(array_flip(array_column($this->pairs, 0))[$key]);
    }

    /**
     * Returns the first value associated to the given parameter.
     *
     * If no value is found null is returned
     *
     * @see https://url.spec.whatwg.org/#dom-urlsearchparams-get
     */
    public function get(string $key)
    {
        foreach ($this->pairs as $pair) {
            if ($key === $pair[0]) {
                return $pair[1];
            }
        }

        return null;
    }

    /**
     * Returns all the values associated to the given parameter as an array or all
     * the instance pairs.
     *
     * If no value is found an empty array is returned
     *
     * @see https://url.spec.whatwg.org/#dom-urlsearchparams-getall
     */
    public function getAll(string $key): array
    {
        $filter = static function (array $pair) use ($key): bool {
            return $key === $pair[0];
        };

        return array_column(array_filter($this->pairs, $filter), 1);
    }

    /**
     * Returns the store PHP variables as elements of an array.
     *
     * The result is similar as PHP parse_str when used with its
     * second argument with the difference that variable names are
     * not mangled.
     *
     * @see http://php.net/parse_str
     * @see https://wiki.php.net/rfc/on_demand_name_mangling
     */
    public function toParams(): array
    {
        return QueryString::convert($this->pairs);
    }

    /**
     * Returns an instance with a different separator.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the query component with a different separator
     */
    public function withSeparator(string $separator): self
    {
        if ($separator === $this->separator) {
            return $this;
        }

        $clone = clone $this;
        $clone->separator = $this->filterSeparator($separator);

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($content): self
    {
        $content = $this->filterComponent($content);
        if ($content === $this->getContent()) {
            return $this;
        }

        return new self($content);
    }

    /**
     * Sort the query string by offset, maintaining offset to data correlations.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified query
     *
     * @see https://url.spec.whatwg.org/#dom-urlsearchparams-sort
     */
    public function sort(): self
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
     * Returns an instance without duplicate key/value pair.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the query component normalized by removing
     * duplicate pairs whose key/value are the same.
     */
    public function withoutDuplicates(): self
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
     * Returns an instance without empty key/value where the value is the null value.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the query component normalized by removing
     * empty pairs.
     *
     * A pair is considered empty if its value is equal to the null value
     */
    public function withoutEmptyPairs(): self
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
     * Returns an instance where numeric indices associated to PHP's array like key are removed.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the query component normalized so that numeric indexes
     * are removed from the pair key value.
     *
     * ie.: toto[3]=bar[3]&foo=bar becomes toto[]=bar[3]&foo=bar
     */
    public function withoutNumericIndices(): self
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
     * Returns an instance with the a new key/value pair added to it.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified query
     *
     * If the pair already exists the value will replace the existing value.
     *
     * @see https://url.spec.whatwg.org/#dom-urlsearchparams-set
     */
    public function withPair(string $key, $value): self
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
     * Returns an instance with the new pairs set to it.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified query
     *
     * @see ::withPair
     */
    public function merge($query): self
    {
        $pairs = $this->pairs;
        foreach (QueryString::parse($this->filterComponent($query), $this->separator, PHP_QUERY_RFC3986) as $pair) {
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
     * @throws TypeError if the value type is invalid
     */
    private function filterPair($value)
    {
        if (null === $value || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof ComponentInterface) {
            return $value->getContent();
        }

        if (method_exists($value, '__toString')) {
            return (string) $value;
        }

        throw new TypeError('The submitted value is invalid.');
    }

    /**
     * Returns an instance without the specified keys.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component
     *
     * @param string $key     the first key to remove
     * @param string ...$keys the list of remaining keys to remove
     */
    public function withoutPair(string $key, string ...$keys): self
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
     * Returns a new instance with a specified key/value pair appended as a new pair.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified query
     *
     * @param mixed $value must be a scalar or the null value
     */
    public function appendTo(string $key, $value): self
    {
        $clone = clone $this;
        $clone->pairs[] = [$key, $this->filterPair($value)];

        return $clone;
    }

    /**
     * Returns an instance with the new pairs appended to it.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified query
     *
     * If the pair already exists the value will be added to it.
     */
    public function append($query): self
    {
        if ($query instanceof ComponentInterface) {
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
     * Returns an instance without the specified params.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component without PHP's value.
     * PHP's mangled is not taken into account.
     *
     * @param string ...$offsets
     */
    public function withoutParam(string $offset, string ...$offsets): self
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

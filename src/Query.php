<?php
/**
 * League.Uri (http://uri.thephpleague.com).
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

use Countable;
use Iterator;
use IteratorAggregate;
use League\Uri;
use Traversable;
use TypeError;

/**
 * Value object representing a URI Query component.
 *
 * Instances of this interface are considered immutable; all methods that
 * might change state MUST be implemented such that they retain the internal
 * state of the current instance and return an instance that contains the
 * changed state.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.5.0
 * @see        https://tools.ietf.org/html/rfc3986#section-3.4
 */
final class Query implements ComponentInterface, Countable, IteratorAggregate
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
     * @var array
     */
    private $params;

    /**
     * Returns a new instance from the result of PHP's parse_str.
     *
     * @param Traversable|array $params
     * @param string            $separator
     *
     * @return self
     */
    public static function createFromParams($params, string $separator = '&'): self
    {
        if ($params instanceof self) {
            $params = $params->toParams();
        }

        if ($params instanceof Traversable) {
            $params = iterator_to_array($params, true);
        }

        if (!is_array($params)) {
            throw new TypeError('the parameters must be iterable');
        }

        if (empty($params)) {
            return new self(null, $separator);
        }

        return new self(http_build_query($params, '', $separator, self::RFC3986_ENCODING), $separator);
    }

    /**
     * Returns a new instance from the result of query_parse.
     *
     * @param Traversable|array $pairs
     * @param string            $separator
     *
     * @return self
     */
    public static function createFromPairs($pairs, string $separator = '&'): self
    {
        if ($pairs instanceof self) {
            return $pairs->withSeparator($separator);
        }

        if ($pairs instanceof Traversable) {
            $pairs = iterator_to_array($pairs);
        }

        if (!is_array($pairs)) {
            throw new TypeError('the parameters must be iterable');
        }

        return new self(Uri\query_build($pairs, $separator), $separator);
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
     * @param mixed  $query
     * @param string $separator
     * @param int    $enc_type
     *
     * @return self
     */
    public function __construct($query = null, string $separator = '&', int $enc_type = self::RFC3986_ENCODING)
    {
        $this->separator = $this->filterSeparator($separator);
        $this->pairs = Uri\query_parse($query, $separator, $enc_type);
    }

    /**
     * Filter the incoming separator.
     *
     * @param string $separator
     *
     * @throws Exception if the separator is invalid
     *
     * @return string
     */
    private function filterSeparator(string $separator): string
    {
        if ('=' !== $separator) {
            return $separator;
        }

        throw new Exception(sprintf('Invalid separator character `%s`', $separator));
    }

    /**
     * Returns the query separator.
     *
     * @return string
     */
    public function getSeparator(): string
    {
        return $this->separator;
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
    {
        return [
            'pairs' => $this->pairs,
            'separator' => $this->separator,
        ];
    }

    /**
     * Returns the encoded query.
     *
     * @param  int         $enc_type
     * @return null|string
     */
    public function getContent(int $enc_type = self::RFC3986_ENCODING)
    {
        return Uri\query_build($this->pairs, $this->separator, $enc_type);
    }

    /**
     * Returns the number of key/value pairs present in the object.
     *
     * @return int
     */
    public function count()
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
    public function getIterator()
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
     * @return \Iterator
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
     * @param string $key
     *
     * @return bool
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
     * @param string $key
     *
     * @return mixed
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
     * @param string $key
     *
     * @return array
     */
    public function getAll(string $key): array
    {
        $filter = function (array $pair) use ($key): bool {
            return $key === $pair[0];
        };

        return array_column(array_filter($this->pairs, $filter), 1);
    }

    /**
     * Returns the instance RFC3986 string representation.
     *
     * If the instance is defined, the value returned MUST be percent-encoded,
     * but MUST NOT double-encode any characters. To determine what characters
     * to encode, please refer to RFC 3986, Sections 2 and 3.
     *
     * If the instance is not defined an empty string is returned
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getContent();
    }

    /**
     * Returns the instance string representation with its optional URI delimiters.
     *
     * The value returned MUST be percent-encoded, but MUST NOT double-encode any
     * characters. To determine what characters to encode, please refer to RFC 3986,
     * Sections 2 and 3.
     *
     * If the instance is not defined an empty string is returned
     *
     * @return string
     */
    public function getUriComponent(): string
    {
        if ([] === $this->pairs) {
            return '';
        }

        return '?'.$this->getContent();
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
     *
     * @return array
     */
    public function toParams(): array
    {
        return Uri\query_extract($this->getContent(), $this->separator);
    }

    /**
     * Returns an instance with a different separator.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the query component with a different separator
     *
     * @param string $separator
     *
     * @return self
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
    public function withContent($query): self
    {
        $pairs = Uri\query_parse($query, $this->separator);
        if ($pairs === $this->pairs) {
            return $this;
        }

        $clone = clone $this;
        $clone->pairs = $pairs;

        return $clone;
    }

    /**
     * Sort the query string by offset, maintaining offset to data correlations.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified query
     *
     * @param callable|int $sort a PHP sort flag constant or a comparaison function
     *                           which must return an integer less than, equal to,
     *                           or greater than zero if the first argument is
     *                           considered to be respectively less than, equal to,
     *                           or greater than the second.
     *
     * @return self
     */
    public function ksort($sort = SORT_REGULAR): self
    {
        $func = is_callable($sort) ? 'uasort' : 'asort';

        $keys = array_column($this->pairs, 0);
        $func($keys, $sort);

        $pairs = [];
        foreach ($keys as $offset => $keys) {
            $pairs[] = $this->pairs[$offset];
        }

        if ($pairs === $this->pairs) {
            return $this;
        }

        $clone = clone $this;
        $clone->pairs = $pairs;

        return $clone;
    }

    /**
     * Returns an instance without duplicate key/value pair.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the query component normalized by removing
     * duplicate pairs whose key/value are the same.
     *
     * @return static
     */
    public function withoutDuplicates(): self
    {
        $pairs = [];
        foreach ($this->pairs as $pair) {
            if (!in_array($pair, $pairs, true)) {
                $pairs[] = $pair;
            }
        }

        if ($pairs === $this->pairs) {
            return $this;
        }

        $clone = clone $this;
        $clone->pairs = $pairs;

        return $clone;
    }

    /**
     * Returns an instance without empty key/value where the value is the null value.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the query component normalized by removing
     * empty pairs.
     *
     * A pair is considered empty if its value is equal to the empty string or the null value
     * or if its key is the empty string.
     *
     * @return self
     */
    public function withoutEmptyPairs(): self
    {
        $filter = function (array $pairs): bool {
            return '' !== $pairs[0]
                && null !== $pairs[1]
                && '' !== $pairs[1];
        };

        $pairs = array_filter($this->pairs, $filter);
        if ($pairs === $this->pairs) {
            return $this;
        }

        $clone = clone $this;
        $clone->pairs = $pairs;

        return $clone;
    }

    /**
     * Returns an instance where numeric indices associated to PHP's array like key are removed.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the query component normalized so that numeric indexes
     * are removed from the pair key value.
     *
     *
     * ie.: toto[3]=bar[3]&foo=bar becomes toto[]=bar[3]&foo=bar
     *
     * @return self
     */
    public function withoutNumericIndices(): self
    {
        $mapper = function (array $pair): array {
            $pair[0] = preg_replace(',\[\d+\],', '[]', $pair[0]);

            return $pair;
        };

        $pairs = array_map($mapper, $this->pairs);
        if ($pairs === $this->pairs) {
            return $this;
        }

        $clone = clone $this;
        $clone->pairs = $pairs;

        return $clone;
    }

    /**
     * Returns an instance with the a new pairs added to it.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified query
     *
     * If the pair already exists the value will replace the existing value.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return self
     */
    public function withPair(string $key, $value): self
    {
        $pair = [$key, $this->filterPair($value)];
        $filter = function (array $pair) use ($key): bool {
            return $key !== $pair[0];
        };

        $pairs = array_filter($this->pairs, $filter);
        $pairs[] = $pair;
        $clone = clone $this;
        $clone->pairs = $pairs;

        return $clone;
    }

    /**
     * Validate the given pair.
     *
     * To be valid the pair must be the null value, a scalar or a collection of scalar and null values.
     *
     * @param mixed $value
     *
     * @throws TypeError if the value type is invalid
     *
     * @return string|null
     */
    private static function filterPair($value)
    {
        if (null === $value) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || method_exists($value, '__toString')) {
            return (string) $value;
        }

        throw new TypeError('The submitted value is invalid.');
    }

    /**
     * Returns a new instance with a specified key/value pair appended as a new pair.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified query
     *
     * @param string $key
     * @param mixed  $value must be a scalar or the null value
     *
     * @return self
     */
    public function appendTo(string $key, $value): self
    {
        if (method_exists($value, '__toString')) {
            $value = (string) $value;
        }

        if (null !== $value && !is_scalar($value)) {
            throw new TypeError('The value must be a scalar or null %s given');
        }

        $clone = clone $this;
        $clone->pairs[] = [$key, $value];

        return $clone;
    }

    /**
     * Returns an instance without the specified keys.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component
     *
     * @param string ...$keys the list of keys to remove from the collection
     *
     * @return self
     */
    public function withoutPairs(string ...$keys): self
    {
        $pairs = [];
        foreach ($this->pairs as $pair) {
            foreach ($keys as $key) {
                if ($key === $pair[0]) {
                    continue 2;
                }
            }
            $pairs[] = $pair;
        }

        if ($pairs === $this->pairs) {
            return $this;
        }

        $clone = clone $this;
        $clone->pairs = $pairs;

        return $clone;
    }

    /**
     * Returns an instance merge with the specified query.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified query.
     *
     * The return query is normalized by removing empty pairs. A key/value pair is
     * considered to be empty if its value is equal to the empty string, the null value
     * or its key is equal to the empty string.
     *
     * @param null|string $query the data to be merged
     *
     * @return self
     */
    public function merge($query): self
    {
        $pairs = Uri\query_parse($query, $this->separator);
        if ($pairs === $this->pairs) {
            return $this;
        }

        $keys_to_remove = array_intersect(array_column($pairs, 0), array_column($this->pairs, 0));
        $base_pairs = $this->pairs;
        if (!empty($keys_to_remove)) {
            foreach ($base_pairs as &$pair) {
                if (in_array($pair[0], $keys_to_remove, true)) {
                    $pair = null;
                }
            }
            unset($pair);
            $base_pairs = array_filter($base_pairs);
        }

        $pairs = array_merge($base_pairs, $pairs);
        foreach ($pairs as &$pair) {
            if ($pair[0] === '' && $pair[1] === null) {
                $pair = null;
            }
        }
        unset($pair);

        $clone = clone $this;
        $clone->pairs = array_filter($pairs);

        return $clone;
    }

    /**
     * Returns an instance with the new pairs appended to it.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified query
     *
     * If the pair already exists the value will be added to it.
     *
     * @param string|null $query
     *
     * @return self
     */
    public function append($query): self
    {
        $pairs = array_merge($this->pairs, Uri\query_parse($query, $this->separator));
        if ($pairs === $this->pairs) {
            return $this;
        }

        foreach ($pairs as &$pair) {
            if ($pair[0] === '' && $pair[1] === null) {
                $pair = null;
            }
        }
        unset($pair);

        $clone = clone $this;
        $clone->pairs = array_filter($pairs);

        return $clone;
    }

    /**
     * Returns an instance without the specified params.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component without PHP's value.
     * PHP's mangled is not taken into account.
     *
     * @param string ...$offsets the list of params key to remove from the query
     *
     * @return static
     */
    public function withoutParams(string ...$offsets): self
    {
        $pairs = [];
        foreach ($this->pairs as $pair) {
            foreach ($offsets as $offset) {
                if (preg_match(',^'.preg_quote($offset, ',').'(\[.*\].*)?$,', $pair[0])) {
                    continue 2;
                }
            }
            $pairs[] = $pair;
        }

        if ($pairs === $this->pairs) {
            return $this;
        }

        $clone = clone $this;
        $clone->pairs = $pairs;

        return $clone;
    }
}

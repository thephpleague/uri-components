<?php
/**
 * League.Uri (http://uri.thephpleague.com)
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
use League\Uri\ComponentInterface;
use League\Uri\Exception;
use Traversable;

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
            return new self($params, $separator);
        }

        if ($params instanceof Traversable) {
            $params = iterator_to_array($params, true);
        }

        if (!is_array($params)) {
            throw new Exception('the parameters must be iterable');
        }

        if (empty($params)) {
            return new self(null, $separator);
        }

        return new self(http_build_query($params, '', $separator, self::RFC3986_ENCODING), $separator);
    }

    /**
     * Returns a new instance from the result of parse_query
     *
     * @param Traversable|array $pairs
     * @param string            $separator
     *
     * @throws Exception if the pairs are not iterable
     *
     * @return self
     */
    public static function createFromPairs($pairs, string $separator = '&'): self
    {
        if ($pairs instanceof self) {
            return new self($pairs, $separator);
        }

        if ($pairs instanceof Traversable) {
            $pairs = iterator_to_array($pairs, true);
        }

        if (!is_array($pairs)) {
            throw new Exception('the parameters must be iterable');
        }

        if (empty($pairs)) {
            return new self(null, $separator);
        }

        return new self(Uri\build_query($pairs, $separator), $separator);
    }

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        $newInstance = new self();
        $newInstance->pairs = $properties['pairs'];
        $newInstance->separator = $properties['separator'];

        return $newInstance;
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
        $this->pairs = Uri\parse_query($query, $separator, $enc_type);
    }

    /**
     * Filter the incoming separator
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
        return Uri\build_query($this->pairs, $this->separator, $enc_type);
    }

    /**
     * Returns the number of key/value pairs present in the object.
     *
     * @return int
     */
    public function count()
    {
        $reducer = function (int $carry, array $value): int {
            return $carry + count($value);
        };

        return array_reduce($this->pairs, $reducer, 0);
    }

    /**
     * Returns an iterator allowing to go through all key/value pairs contained in this object.
     *
     * The key of each pair are string
     * The value of each pair are scalar or the null value
     *
     * @return Iterator
     */
    public function getIterator()
    {
        foreach ($this->pairs as $offset => $value) {
            foreach ($value as $val) {
                yield $offset => $val;
            }
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
        return isset($this->pairs[$key]);
    }

    /**
     * Returns the first value associated to the given parameter.
     *
     * The value returned MUST be percent-encoded, but MUST NOT double-encode any
     * characters. To determine what characters to encode, please refer to RFC 3986,
     * Sections 2 and 3.
     *
     * If no value is found null is returned
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key)
    {
        return $this->pairs[$key][0] ?? null;
    }

    /**
     * Returns all the values associated to the given parameter as an array or all
     * the instance pairs.
     *
     * The value returned MUST be percent-encoded, but MUST NOT double-encode any
     * characters. To determine what characters to encode, please refer to RFC 3986,
     * Sections 2 and 3.
     *
     * If no value is found an empty array is returned
     *
     * @param string... $arg
     *
     * @return array
     */
    public function getAll(string ...$arg): array
    {
        if (empty($arg)) {
            return $this->pairs;
        }

        return $this->pairs[$arg[0]] ?? [];
    }

    /**
     * Returns an iterator allowing to go through all keys contained in this object.
     *
     * Each key is a string. if an argument is given. Only the key who contains this
     * value will be returned by the iterator
     *
     * @param mixed ...$arg
     *
     * @return array
     */
    public function keys(...$arg): array
    {
        if (empty($arg)) {
            return array_keys($this->pairs);
        }

        $key = $arg[0];
        $filter = function ($value) use ($key): bool {
            return in_array($key, $value, true);
        };

        return array_keys(array_filter($this->pairs, $filter));
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
     * Returns the instance string representation with its optional URI delimiters
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
        return Uri\pairs_to_params($this->pairs);
    }

    /**
     * Returns an instance with a different separator
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
     * Returns an instance with the specified content.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified content.
     *
     * Users can provide both encoded and decoded content characters.
     *
     * A null value is equivalent to removing the component content.
     *
     * @param null|string $query
     *
     * @throws InvalidArgumentException for invalid component or transformations
     *                                  that would result in a object in invalid state.
     *
     * @return static
     */
    public function withContent($query): self
    {
        $pairs = Uri\parse_query($query, $this->separator);
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
        $func = is_callable($sort) ? 'uksort' : 'ksort';
        $pairs = $this->pairs;
        $func($pairs, $sort);
        if ($pairs === $this->pairs) {
            return $this;
        }

        $clone = clone $this;
        $clone->pairs = $pairs;

        return $clone;
    }

    /**
     * Returns an instance without duplicate key/value pair
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the query component normalized by removing
     * duplicate pairs whose key/value are the same.
     *
     * @return static
     */
    public function withoutDuplicates(): self
    {
        $pairs = array_map('array_unique', $this->pairs);
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
        $base = $this->pairs;
        $pairs = [];
        foreach ($base as $key => $value) {
            if ('' === $key) {
                continue;
            }
            $value = array_filter($value, [$this, 'isValueSet']);
            if (!empty($value)) {
                $pairs[$key] = $value;
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
     * Tell whether a value is set.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function isValueSet($value): bool
    {
        return null !== $value && '' !== $value;
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
        $query = $this->getContent();
        static $pattern = ',\%5B\d+\%5D,';
        if (null === $query || !preg_match($pattern, $query)) {
            return $this;
        }

        $new_query = implode(
            $this->separator,
            array_map([$this, 'removeNumericIndex'], explode($this->separator, $query))
        );

        if ($new_query === $query) {
            return $this;
        }

        return new self($new_query, $this->separator);
    }

    /**
     * Remove the numeric index from the key pair
     *
     * @param string $pair
     *
     * @return string
     */
    private function removeNumericIndex(string $pair): string
    {
        static $pattern = ',\%5B\d+\%5D,';
        static $replace = '%5B%5D';
        list($key, $value) = explode('=', $pair, 2) + [1 => null];
        $new_key = preg_replace($pattern, $replace, $key);
        if ($new_key === $key) {
            return $pair;
        }

        if (null === $value) {
            return $new_key;
        }

        return $new_key.'='.$value;
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
        $pairs = array_merge($this->pairs, [$key => $this->filterPair($value)]);
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
     * @param mixed $value
     *
     * @throws Exception if the value is invalid
     *
     * @return array
     */
    private static function filterPair($value): array
    {
        if (null === $value || is_scalar($value)) {
            return [$value];
        }

        if ($value instanceof Traversable) {
            $value = iterator_to_array($value, false);
        }

        if (!is_array($value)) {
            throw new Exception('The submitted value is invalid.');
        }

        foreach ($value as $val) {
            if (null !== $val && !is_scalar($val)) {
                throw new Exception('The submitted value is invalid.');
            }
        }

        return array_values($value);
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
     * @throws Exception if the value is invalid
     *
     * @return self
     */
    public function appendTo(string $key, $value): self
    {
        if (null !== $value && !is_scalar($value)) {
            throw new Exception('The value must be a scalar or null %s given');
        }

        $clone = clone $this;
        $clone->pairs = array_merge($this->pairs, [$key => array_merge($this->getAll($key), [$value])]);

        return $clone;
    }

    /**
     * Returns an instance without the specified keys
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component
     *
     * @param string ...$args the list of keys to remove from the collection
     *
     * @return self
     */
    public function withoutPairs(string ...$args): self
    {
        $pairs = $this->pairs;
        foreach ($args as $key) {
            unset($pairs[$key]);
        }

        if ($pairs === $this->pairs) {
            return $this;
        }

        $clone = clone $this;
        $clone->pairs = $pairs;

        return $clone;
    }

    /**
     * Returns an instance merge with the specified query
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
        $pairs = Uri\parse_query($query, $this->separator);
        if ($pairs === $this->pairs) {
            return $this;
        }

        $clone = clone $this;
        $clone->pairs = $this->filterEmptyKeyPair(array_merge($this->pairs, $pairs));

        return $clone;
    }

    /**
     * Remove key/pair where the key is the empty string
     * and the value is null or the empty string
     *
     * @param array $pairs
     *
     * @return array
     */
    private function filterEmptyKeyPair(array $pairs): array
    {
        if (!isset($pairs[''])) {
            return $pairs;
        }

        $pairs[''] = array_filter($pairs[''], function ($value) {
            return null !== $value && '' !== $value;
        });
        if (empty($pairs[''])) {
            unset($pairs['']);
        }

        return $pairs;
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
        $pairs = $this->pairs;
        $new_pairs = Uri\parse_query($query, $this->separator);
        foreach ($new_pairs as $key => $value) {
            $pairs = array_merge($pairs, [$key => array_merge($pairs[$key] ?? [], $value)]);
        }

        if ($pairs === $this->pairs) {
            return $this;
        }

        $clone = clone $this;
        $clone->pairs = $this->filterEmptyKeyPair($pairs);

        return $clone;
    }

    /**
     * Returns an instance without the specified params
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
        $reducer = function (array $pairs, string $offset): array {
            $filter = function (string $key) use ($offset): bool {
                return !preg_match(',^'.preg_quote($offset, ',').'(\[.*\].*)?$,', $key);
            };

            return array_filter($pairs, $filter, ARRAY_FILTER_USE_KEY);
        };

        $pairs = array_reduce($offsets, $reducer, $this->pairs);
        if ($pairs === $this->pairs) {
            return $this;
        }

        $clone = clone $this;
        $clone->pairs = $pairs;

        return $clone;
    }
}

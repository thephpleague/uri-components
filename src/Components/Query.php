<?php

/**
 * League.Uri (https://uri.thephpleague.com/components/).
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.8.2
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\Components;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use League\Uri;
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
 * @since      1.0.0
 * @see        https://tools.ietf.org/html/rfc3986#section-3.4
 */
class Query implements ComponentInterface, Countable, IteratorAggregate
{
    use ComponentTrait;

    /**
     * pair separator character.
     *
     * @var string
     */
    protected $separator;

    /**
     * Preserve the delimiter.
     *
     * @var bool
     */
    protected $preserve_delimiter;

    /**
     * The deserialized query arguments.
     *
     * @var array
     */
    protected $params;

    /**
     * The query pairs.
     *
     * @var array
     */
    protected $pairs;

    /**
     * The query pairs keys.
     *
     * @var array
     */
    protected $keys;

    /**
     * Returns the store PHP variables as elements of an array.
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated 1.5.0 No longer used by internal code and not recommend
     * @see        \League\Uri\QueryParser::extract
     *
     * @param string $str       the query string
     * @param string $separator a the query string single character separator
     * @param int    $enc_type  the query encoding
     *
     */
    public static function extract(
        string $str,
        string $separator = '&',
        int $enc_type = self::RFC3986_ENCODING
    ): array {
        return Uri\extract_query($str, $separator, $enc_type);
    }

    /**
     * Parse a query string into an associative array.
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated 1.5.0 No longer used by internal code and not recommend
     * @see        \League\Uri\QueryParser::parse
     *
     * @param string $str       The query string to parse
     * @param string $separator The query string separator
     * @param int    $enc_type  The query encoding algorithm
     *
     */
    public static function parse(
        string $str,
        string $separator = '&',
        int $enc_type = self::RFC3986_ENCODING
    ): array {
        return Uri\parse_query($str, $separator, $enc_type);
    }

    /**
     * Build a query string from an associative array.
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated 1.5.0 No longer used by internal code and not recommend
     * @see        \League\Uri\QueryBuilder::build
     *
     * @param array|Traversable $pairs     Query pairs
     * @param string            $separator Query string separator
     * @param int               $enc_type  Query encoding type
     *
     */
    public static function build(
        $pairs,
        string $separator = '&',
        int $enc_type = self::RFC3986_ENCODING
    ): string {
        return Uri\build_query($pairs, $separator, $enc_type);
    }

    /**
     * Returns a new instance from a collection of iterable properties.
     *
     * @param Traversable|array $params
     *
     * @return static
     */
    public static function createFromParams($params, string $separator = '&'): self
    {
        $params = static::filterIterable($params);
        if (empty($params)) {
            return new static(null, $separator);
        }

        return new static(http_build_query($params, '', $separator, PHP_QUERY_RFC3986), $separator);
    }

    /**
     * Return a new instance from a collection of key pairs.
     *
     * @param Traversable|array $pairs
     *
     * @return static
     */
    public static function createFromPairs($pairs, string $separator = '&'): self
    {
        $pairs = static::filterIterable($pairs);
        if (empty($pairs)) {
            return new static(null, $separator);
        }

        return new static(Uri\build_query($pairs, $separator), $separator);
    }

    /**
     *  This static method is called for classes exported by var_export().
     *
     *
     * @return static
     */
    public static function __set_state(array $properties): self
    {
        $separator = $properties['separator'] ?? '&';

        return new static(Uri\build_query($properties['pairs'], $separator), $separator);
    }

    /**
     * a new instance.
     *
     * @param string $data
     */
    public function __construct(string $data = null, string $separator = '&')
    {
        $this->separator = $this->filterSeparator($separator);
        $this->pairs = $this->validate($data);
        $this->params = Uri\pairs_to_params($this->pairs);
        $this->preserve_delimiter = null !== $data;
        $this->keys = array_fill_keys(array_keys($this->pairs), 1);
    }

    /**
     * Filter the submitted query separator.
     *
     *
     * @throws Exception If the separator is invalid
     *
     */
    protected static function filterSeparator(string $separator): string
    {
        if ('=' === $separator) {
            throw new Exception(sprintf('Invalid separator character `%s`', $separator));
        }

        return $separator;
    }

    /**
     * sanitize the submitted data.
     *
     *
     */
    protected function validate(string $str = null): array
    {
        if (null === $str) {
            return [];
        }

        $str = $this->validateString($str);

        return Uri\parse_query($str, $this->separator);
    }

    /**
     * Tell whether a variable is set.
     *
     * Because isset is a language construct
     * it can not be used directly with array_filter.
     *
     *
     * @return bool
     */
    protected function isValueSet($value)
    {
        return null !== $value;
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
    {
        return [
            'component' => $this->getContent(),
            'pairs' => $this->pairs,
            'separator' => $this->separator,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function isNull(): bool
    {
        return null === $this->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        return '' == $this->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(int $enc_type = self::RFC3986_ENCODING)
    {
        $this->assertValidEncoding($enc_type);
        if (!$this->preserve_delimiter) {
            return null;
        }

        return Uri\build_query($this->pairs, $this->separator, $enc_type);
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return (string) $this->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function getUriComponent(): string
    {
        $query = $this->__toString();
        if ($this->preserve_delimiter) {
            return '?'.$query;
        }

        return $query;
    }

    /**
     * Returns the query string separator character.
     *
     */
    public function getSeparator(): string
    {
        return $this->separator;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->pairs);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new ArrayIterator($this->pairs);
    }

    /**
     * Returns the deserialized query string arguments, if any.
     *
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Returns a single deserialized query string argument, if any
     * otherwise return the provided default value.
     *
     * @param null|mixed $default
     *
     */
    public function getParam(string $offset, $default = null)
    {
        return $this->params[$offset] ?? $default;
    }

    /**
     * Returns an array representation of the query.
     *
     */
    public function getPairs(): array
    {
        return $this->pairs;
    }

    /**
     * Retrieves a single query parameter.
     *
     * Retrieves a single query parameter. If the parameter has not been set,
     * returns the default value provided.
     *
     * @param string $offset  the parameter name
     * @param mixed  $default Default value to return if the parameter does not exist.
     *
     */
    public function getPair(string $offset, $default = null)
    {
        $offset = $this->decodeComponent($this->validateString($offset));
        if (isset($this->keys[$offset])) {
            return $this->pairs[$offset];
        }

        return $default;
    }

    /**
     * Returns whether the given key exists in the current instance.
     *
     *
     */
    public function hasPair(string $offset): bool
    {
        $offset = $this->decodeComponent($this->validateString($offset));

        return isset($this->keys[$offset]);
    }

    /**
     * Returns the associated key for each pair.
     *
     * If a value is specified only the keys associated with
     * the given value will be returned. The specified value
     * must be decoded
     *
     * @param mixed ...$args the total number of argument given to the method
     *
     */
    public function keys(...$args): array
    {
        if (empty($args)) {
            return array_keys($this->pairs);
        }

        return array_keys($this->pairs, $args[0], true);
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($value): ComponentInterface
    {
        if ($value === $this->getContent()) {
            return $this;
        }

        return new static($value, $this->separator);
    }

    /**
     * Returns an instance with a different separator.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the query component with a different separator
     *
     *
     * @return static
     */
    public function withSeparator(string $separator): self
    {
        if ($separator === $this->separator) {
            return $this;
        }

        $separator = $this->filterSeparator($separator);
        $clone = clone $this;
        $clone->separator = $separator;

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
     * @return static
     */
    public function ksort($sort = SORT_REGULAR): self
    {
        $func = is_callable($sort) ? 'uksort' : 'ksort';
        $pairs = $this->pairs;
        $func($pairs, $sort);
        if ($pairs === $this->pairs) {
            return $this;
        }

        return static::createFromPairs($pairs, $this->separator);
    }

    /**
     * Returns an instance merge with the specified query.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified query
     *
     * @param string $query the data to be merged
     *
     * @return static
     */
    public function merge(string $query): self
    {
        $new_pairs = $this->validate($this->validateString($query));
        $new_pairs = $this->removeEmptyPairs($new_pairs);
        $base_pairs = $this->removeEmptyPairs($this->pairs);
        if ($base_pairs === $new_pairs) {
            return $this;
        }

        $pairs = array_merge($base_pairs, $new_pairs);

        return static::createFromPairs($pairs, $this->separator);
    }

    /**
     * Normalize a query string by removing empty pairs.
     *
     *
     */
    protected function removeEmptyPairs(array $pairs): array
    {
        $result = [];

        foreach ($pairs as $key => $value) {
            if ('' !== $key) {
                $result[$key] = $value;
                continue;
            }

            if (null === $value) {
                continue;
            }

            if (!is_array($value)) {
                $result[$key] = $value;
                continue;
            }

            $value = array_filter($value, [$this, 'isValueSet']);
            if (!empty($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Returns an instance with the new pair appended to it.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified query
     *
     * If the pair already exists the value will be added to it.
     *
     * @param string $query the pair value
     *
     * @return static
     */
    public function append(string $query): self
    {
        $new_pairs = $this->validate($this->validateString($query));
        $new_pairs = $this->removeEmptyPairs($new_pairs);
        $base_pairs = $this->removeEmptyPairs($this->pairs);
        $pairs = $base_pairs;
        foreach ($new_pairs as $key => $value) {
            $pairs = $this->appendToPair($pairs, $key, $value);
        }

        if ($base_pairs == $pairs) {
            return $this;
        }

        return static::createFromPairs($pairs, $this->separator);
    }

    /**
     * Append a key/pair to query pairs collection.
     *
     *
     */
    protected function appendToPair(array $pairs, string $key, $value): array
    {
        if (!array_key_exists($key, $pairs)) {
            $pairs[$key] = $value;

            return $pairs;
        }

        $pair = $pairs[$key];
        if (!is_array($pair)) {
            $pair = [$pair];
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        $pairs[$key] = array_merge($pair, $value);

        return $pairs;
    }

    /**
     * Returns an instance without the specified keys.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component
     *
     * @param string[] $offsets the list of keys to remove from the collection
     *
     * @return static
     */
    public function withoutPairs(array $offsets): self
    {
        $reducer = function (array $pairs, string $key): array {
            $offset = $this->decodeComponent($this->validateString($key));
            unset($pairs[$offset]);

            return $pairs;
        };

        $pairs = array_reduce($offsets, $reducer, $this->pairs);
        if ($pairs === $this->pairs) {
            return $this;
        }

        return static::createFromPairs($pairs, $this->separator);
    }

    /**
     * Returns an instance without the specified params.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component
     *
     * @param string[] $offsets the list of params key to remove from the query
     *
     * @return static
     */
    public function withoutParams(array $offsets): self
    {
        $reducer = function (array $pairs, string $name): array {
            $filter = function (string $key) use ($name): bool {
                $regexp = ',^'.preg_quote($name, ',').'(\[.*\].*)?$,';

                return !preg_match($regexp, $key, $matches);
            };

            return array_filter($pairs, $filter, ARRAY_FILTER_USE_KEY);
        };

        $pairs = array_reduce($offsets, $reducer, $this->pairs);
        if ($pairs === $this->pairs) {
            return $this;
        }

        return static::createFromPairs($pairs, $this->separator);
    }

    /**
     * Returns an instance without empty pairs.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the query component normalized by removing
     * empty pairs
     *
     * @return static
     */
    public function withoutEmptyPairs(): self
    {
        return self::createFromPairs($this->removeEmptyPairs($this->pairs), $this->separator);
    }

    /**
     * Returns an instance where numeric indices associated to PHP's array like key are removed.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the query component normalized so that numeric indexes
     * from PHP's parameters from the query string are removed from the query string representation
     *
     * @return static
     */
    public function withoutNumericIndices(): self
    {
        $str = (string) $this->getContent();
        if ('' === $str) {
            return $this;
        }

        $res = array_map([$this, 'removeNumericIndex'], explode($this->separator, $str));
        $query = implode($this->separator, $res);
        if ($query === $str) {
            return $this;
        }

        return new static($query, $this->separator);
    }

    /**
     * Remove the numeric index from the key pair.
     *
     *
     */
    protected function removeNumericIndex(string $pair): string
    {
        static $regexp = ',\%5B\d+\%5D,';
        static $replace = '%5B%5D';

        list($key, $value) = explode('=', $pair) + ['', null];
        $new_key = preg_replace($regexp, $replace, $key);
        if ($new_key === $key) {
            return $pair;
        }

        $pair = $new_key;
        if (null !== $value) {
            $pair .= '='.$value;
        }

        return $pair;
    }
}

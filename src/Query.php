<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @copyright  2016 Ignace Nyamagana Butera
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.0.0
 * @link       https://github.com/thephpleague/uri-components
 */
namespace League\Uri\Components;

use ArrayIterator;
use Countable;
use IteratorAggregate;
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
    use QueryParserTrait;

    /**
     * The component Data
     *
     * @var array
     */
    protected $data = [];

    /**
     * pair separator character
     *
     * @var string
     */
    protected static $separator = '&';

    /**
     * Preserve the delimiter
     *
     * @var bool
     */
    protected $preserveDelimiter = false;

    /**
     * The query keys
     *
     * @var array
     */
    protected $keys = [];

    /**
     * return a new Query instance from an Array or a traversable object
     *
     * @param Traversable|array $data
     *
     * @return static
     */
    public static function createFromPairs($data): self
    {
        $data = static::validateIterator($data);
        if (empty($data)) {
            return new static();
        }

        return new static(static::build($data, static::$separator));
    }

    /**
     *  This static method is called for classes exported by var_export()
     *
     * @param array $properties
     *
     * @return static
     */
    public static function __set_state(array $properties): self
    {
        return new static(static::build($properties['data'], static::$separator));
    }

    /**
     * a new instance
     *
     * @param string $data
     */
    public function __construct(string $data = null)
    {
        $this->data = $this->validate($data);
        $this->preserveDelimiter = null !== $data;
        $this->keys = array_fill_keys(array_keys($this->data), 1);
    }

    /**
     * sanitize the submitted data
     *
     * @param string|null $str
     *
     * @return array
     */
    protected function validate(string $str = null): array
    {
        if (null === $str) {
            return [];
        }

        $str = $this->validateString($str);
        if (false !== strpos($str, '#')) {
            throw new Exception(sprintf('The encoded query `%s` contains invalid characters', $str));
        }

        return static::parse($str, static::$separator);
    }

    /**
     * Returns the instance content encoded in RFC3986 or RFC3987.
     *
     * If the instance is defined, the value returned MUST be percent-encoded,
     * but MUST NOT double-encode any characters depending on the encoding type selected.
     *
     * To determine what characters to encode, please refer to RFC 3986, Sections 2 and 3.
     * or RFC 3987 Section 3.
     *
     * By default the content is encoded according to RFC3986
     *
     * If the instance is not defined null is returned
     *
     * @param int $enc_type
     *
     * @return string|null
     */
    public function getContent(int $enc_type = ComponentInterface::RFC3986_ENCODING)
    {
        $this->assertValidEncoding($enc_type);

        if (!$this->preserveDelimiter) {
            return null;
        }

        return static::build($this->data, static::$separator, $enc_type);
    }

    /**
     * Returns the instance string representation; If the
     * instance is not defined an empty string is returned
     *
     * @return string
     */
    public function __toString(): string
    {
        return (string) $this->getContent();
    }

    /**
     * Returns the instance string representation
     * with its optional URI delimiters
     *
     * @return string
     */
    public function getUriComponent(): string
    {
        $query = $this->__toString();
        if ($this->preserveDelimiter) {
            return '?'.$query;
        }

        return $query;
    }

    /**
     * Count elements of an object
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Returns an external iterator
     *
     * @return ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->data);
    }

    /**
     * Returns an array representation of the query
     *
     * @return array
     */
    public function getPairs(): array
    {
        return $this->data;
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
     * @return mixed
     */
    public function getPair(string $offset, $default = null)
    {
        $offset = $this->decodeComponent($this->validateString($offset));
        if (isset($this->keys[$offset])) {
            return $this->data[$offset];
        }

        return $default;
    }

    /**
     * Returns whether the given key exists in the current instance
     *
     * @param string $offset
     *
     * @return bool
     */
    public function haskey(string $offset): bool
    {
        $offset = $this->decodeComponent($this->validateString($offset));

        return isset($this->keys[$offset]);
    }

    /**
     * Returns the associated key for each pair.
     *
     * If a value is specified only the keys associated with
     * the given value will be returned
     *
     * @return array
     */
    public function keys(): array
    {
        if (0 === func_num_args()) {
            return array_keys($this->data);
        }

        return array_keys(
            $this->data,
            $this->decodeComponent($this->validateString(func_get_arg(0))),
            true
        );
    }

    /**
     * Returns an instance with the specified string
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified data
     *
     * @param string|null $value
     *
     * @return static
     */
    public function withContent($value): ComponentInterface
    {
        if ($value === $this->getContent()) {
            return $this;
        }

        return new static($value);
    }

    /**
     * Returns an instance merge with the specified query
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified query
     *
     * @param string $query the data to be merged
     *
     * @return static
     */
    public function merge($query): self
    {
        if ($query === null) {
            return $this;
        }

        $pairs = $this->validate($this->validateString($query));
        if ($pairs === $this->data) {
            return $this;
        }

        return static::createFromPairs(array_merge($this->data, $pairs));
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
        $data = $this->data;
        $func($data, $sort);
        if ($data === $this->data) {
            return $this;
        }

        return static::createFromPairs($data);
    }

    /**
     * Returns an instance without the specified keys
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component
     *
     * @param array $offsets the list of keys to remove from the collection
     *
     * @return static
     */
    public function without(array $offsets): self
    {
        $data = $this->data;
        foreach ($offsets as $offset) {
            unset($data[$this->decodeComponent($this->validateString($offset))]);
        }

        if ($data === $this->data) {
            return $this;
        }

        return static::createFromPairs($data);
    }

    /**
     * Returns an instance with only the specified value
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component
     *
     * @param callable $callable the list of keys to keep from the collection
     * @param int      $flag     flag to determine what argument are sent to callback
     *
     * @return static
     */
    public function filter(callable $callable, int $flag = 0): self
    {
        static $flags_list = [0 => 1, ARRAY_FILTER_USE_BOTH => 1, ARRAY_FILTER_USE_KEY => 1];

        if (!isset($flags_list[$flag])) {
            throw Exception::fromInvalidFlag($flag);
        }

        return static::createFromPairs(array_filter($this->data, $callable, $flag));
    }
}

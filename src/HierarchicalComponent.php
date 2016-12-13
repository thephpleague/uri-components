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

/**
 * An abstract class to ease collection like object manipulation
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
abstract class HierarchicalComponent implements ComponentInterface, Countable, IteratorAggregate
{
    use ComponentTrait;

    const IS_ABSOLUTE = 1;

    const IS_RELATIVE = 0;

    /**
     * Hierarchical component separator
     *
     * @var string
     */
    protected static $separator;

    /**
     * Is the object considered absolute
     *
     * @var int
     */
    protected $isAbsolute = self::IS_RELATIVE;
    /**
     * The component Data
     *
     * @var array
     */
    protected $data = [];

    /**
     * new instance
     *
     * @param string|null $data the component value
     */
    abstract public function __construct($data = null);

    /**
     * Count elements of an object
     *
     * @return int
     */
    public function count()
    {
        return count($this->data);
    }

    /**
     * Returns an external iterator
     *
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->data);
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
    public function filter(callable $callable, $flag = 0)
    {
        static $flags_list = [0 => 1, ARRAY_FILTER_USE_BOTH => 1, ARRAY_FILTER_USE_KEY => 1];

        if (!isset($flags_list[$flag])) {
            throw Exception::fromInvalidFlag($flag);
        }

        return $this->newHierarchicalInstance(array_filter($this->data, $callable, $flag), $this->isAbsolute);
    }

    /**
     * Return a new instance when needed
     *
     * @param array $data
     * @param int   $isAbsolute
     *
     * @return static
     */
    abstract protected function newHierarchicalInstance(array $data, $isAbsolute);

    /**
     * Returns whether or not the component is absolute or not
     *
     * @return bool
     */
    public function isAbsolute()
    {
        return $this->isAbsolute === self::IS_ABSOLUTE;
    }

    /**
     * Returns an instance with the specified string
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified data
     *
     * @param string $value
     *
     * @return static
     */
    public function withContent($value)
    {
        if ($value === $this->getContent()) {
            return $this;
        }

        return new static($value);
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
    abstract public function getContent($enc_type = ComponentInterface::RFC3986_ENCODING);

    /**
     * Returns the instance string representation; If the
     * instance is not defined an empty string is returned
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getContent();
    }

    /**
     * Returns the instance string representation
     * with its optional URI delimiters
     *
     * @return string
     */
    public function getUriComponent()
    {
        return $this->__toString();
    }

    /**
     * Returns an instance without the specified keys
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component
     *
     * @param int[] $offsets the list of keys to remove from the collection
     *
     * @return static
     */
    public function without(array $offsets)
    {
        $offsets = filter_var($offsets, FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY);
        if (in_array(false, $offsets, true)) {
            throw new Exception('The offset list must contains integers only');
        }

        $data = $this->data;
        foreach ($offsets as $offset) {
            unset($data[$offset]);
        }

        if ($data === $this->data) {
            return $this;
        }

        return $this->newHierarchicalInstance($data, $this->isAbsolute);
    }

    /**
     * Returns an instance with the modified segment
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component with the replaced data
     *
     * @param int    $offset    the label offset to remove and replace by the given component
     * @param string $component the component added
     *
     * @return static
     */
    public function replace($offset, $component)
    {
        $offset = $this->filterOffset($offset);
        if (false === $offset) {
            return $this;
        }

        $dest = iterator_to_array($this->withContent($component));
        if ('' === $dest[count($dest) - 1]) {
            array_pop($dest);
        }

        $source = iterator_to_array($this);
        $data = array_merge(array_slice($source, 0, $offset), $dest, array_slice($source, $offset + 1));
        if ($data === $this->data) {
            return $this;
        }

        return $this->newHierarchicalInstance($data, $this->isAbsolute);
    }

    /**
     * Filter Offset
     *
     * @param int $offset
     *
     * @return int|false
     */
    protected function filterOffset($offset)
    {
        $nb_elements = count($this->data);
        $offset = filter_var(
            $offset,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1 - $nb_elements, 'max_range' => $nb_elements - 1]]
        );

        if ($offset < 0) {
            return $nb_elements + $offset;
        }

        return $offset;
    }
}

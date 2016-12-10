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

use League\Uri\Interfaces\Component as UriComponent;

/**
 * An abstract class to ease collection like object manipulation
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
abstract class HierarchicalComponent implements UriComponent
{
    use CollectionTrait;
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
     * new instance
     *
     * @param string|null $data the component value
     */
    abstract public function __construct($data = null);

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
    abstract public function getContent($enc_type = UriComponent::RFC3986_ENCODING);

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
     * Returns whether the given key exists in the current instance
     *
     * @param int $offset
     *
     * @return bool
     */
    public function hasKey($offset)
    {
        return array_key_exists($offset, $this->data);
    }

    /**
     * Returns the component $keys.
     *
     * If a value is specified only the keys associated with
     * the given value will be returned
     *
     * @return array
     */
    public function keys()
    {
        if (0 === func_num_args()) {
            return array_keys($this->data);
        }

        return array_keys($this->data, func_get_arg(0), true);
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
    public function without(array $offsets)
    {
        $data = $this->data;
        foreach ($offsets as $offset) {
            unset($data[$offset]);
        }

        if ($data === $this->data) {
            return $this;
        }

        return $this->newCollectionInstance($data);
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
        if (!empty($this->data) && !$this->hasKey($offset)) {
            return $this;
        }

        $source = iterator_to_array($this);
        $dest = iterator_to_array($this->withContent($component));
        if ('' === $dest[count($dest) - 1]) {
            array_pop($dest);
        }

        $data = array_merge(array_slice($source, 0, $offset), $dest, array_slice($source, $offset + 1));
        if ($data === $this->data) {
            return $this;
        }

        return $this->newCollectionInstance($data);
    }
}

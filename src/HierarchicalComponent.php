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

use League\Uri\Components\Traits\ImmutableCollection;

/**
 * An abstract class to ease collection like object manipulation
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
abstract class HierarchicalComponent
{
    use ImmutableCollection;

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
     * @param null|string $str the component value
     */
    abstract public function __construct($str);

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
     * Returns the component literal value
     *
     * @return string|null
     */
    abstract public function getContent();

    /**
     * Returns whether or not the component is defined.
     *
     * @return bool
     */
    public function isDefined()
    {
        return null === $this->getContent();
    }

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

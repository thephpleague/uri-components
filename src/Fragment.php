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
 * Value object representing a URI Fragment component.
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
 * @see        https://tools.ietf.org/html/rfc3986#section-3.5
 */
class Fragment extends Component implements UriComponent
{
    /**
     * Returns the component literal value
     *
     * @return string|null
     */
    public function getContent()
    {
        if (null === $this->data) {
            return null;
        }

        $regexp = '/(?:[^'.self::$unreservedChars.self::$subdelimChars.'\:\/@\?]+
            |%(?!'.self::$encodedChars.'))/x';

        return $this->encode($this->data, $regexp);
    }

    /**
     * Return the decoded string representation of the component
     *
     * @return null|string
     */
    public function getDecoded()
    {
        return $this->data;
    }

    /**
     * Returns the instance string representation
     * with its optional URI delimiters
     *
     * @return string
     */
    public function getUriComponent()
    {
        $component = $this->__toString();
        if (null !== $this->data) {
            return '#'.$component;
        }

        return $component;
    }

    /**
     * @inheritdoc
     */
    public function __debugInfo()
    {
        return ['fragment' => $this->getContent()];
    }
}

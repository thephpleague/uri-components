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
     * @param string $enc_type
     *
     * @return string|null
     */
    public function getContent($enc_type = self::RFC3986)
    {
        if (!in_array($enc_type, [self::RFC3986, self::RFC3987])) {
            throw new Exception('Unsupported or Unknown Encoding');
        }

        if ('' == $this->data) {
            return $this->data;
        }

        if (self::RFC3987 == $enc_type) {
            $pattern = str_split(self::$invalidUriChars);

            return str_replace($pattern, array_map('rawurlencode', $pattern), $this->data);
        }

        $regexp = '/(?:[^'.self::$unreservedChars.self::$subdelimChars.'\:\/@\?]+|%(?!'.self::$encodedChars.'))/ux';

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

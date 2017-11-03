<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.1.1

 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace League\Uri\Components;

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
class Fragment extends AbstractComponent
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
     * @param int $enc_type
     *
     * @return string|null
     */
    public function getContent(int $enc_type = ComponentInterface::RFC3986_ENCODING)
    {
        $this->assertValidEncoding($enc_type);

        if ('' == $this->data || self::NO_ENCODING == $enc_type) {
            return $this->data;
        }

        if (ComponentInterface::RFC3987_ENCODING == $enc_type) {
            $pattern = str_split(self::$invalid_uri_chars);

            return str_replace($pattern, array_map('rawurlencode', $pattern), $this->data);
        }

        $regexp = '/(?:[^'.self::$unreserved_chars.self::$subdelim_chars.'\:\/@\?]+|%(?!'.self::$encoded_chars.'))/ux';

        $content = $this->encode($this->data, $regexp);
        if (ComponentInterface::RFC1738_ENCODING == $enc_type) {
            return $this->toRFC1738($content);
        }

        return $content;
    }

    /**
     * Returns the instance string representation
     * with its optional URI delimiters
     *
     * @return string
     */
    public function getUriComponent(): string
    {
        $component = $this->__toString();
        if (null !== $this->data) {
            return '#'.$component;
        }

        return $component;
    }
}

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
namespace League\Uri\Components\Traits;

use League\Uri\Components\Component;
use League\Uri\Components\Exception;
use League\Uri\Interfaces\Component as UriComponent;

/**
 * Common methods for a URI component Value Object
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
trait ImmutableComponent
{
    /**
     * Invalid Characters
     *
     * @see http://tools.ietf.org/html/rfc3986#section-2
     *
     * @var string
     */
    protected static $invalidUriChars = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F\x7F";

    /**
     * Encoded Characters regular expression pattern
     *
     * @see http://tools.ietf.org/html/rfc3986#section-2.1
     *
     * @var string
     */
    protected static $encodedChars = '[A-Fa-f0-9]{2}';

    /**
     * RFC3986 Sub delimiter characters regular expression pattern
     *
     * @see http://tools.ietf.org/html/rfc3986#section-2.2
     *
     * @var string
     */
    protected static $subdelimChars = "\!\$&'\(\)\*\+,;\=%";

    /**
     * RFC3986 unreserved characters regular expression pattern
     *
     * @see http://tools.ietf.org/html/rfc3986#section-2.3
     *
     * @var string
     */
    protected static $unreservedChars = 'A-Za-z0-9_\-\.~';

    /**
     * RFC3986 unreserved characters encoded regular expression pattern
     *
     * @see http://tools.ietf.org/html/rfc3986#section-2.3
     *
     * @var string
     */
    protected static $unreservedCharsEncoded = '2[D|E]|3[0-9]|4[1-9|A-F]|5[0-9|A|F]|6[1-9|A-F]|7[0-9|E]';

    /**
     * Encode a component string
     *
     * @param string $str    The string to encode
     * @param string $regexp a regular expression
     *
     * @return string
     */
    protected static function encode($str, $regexp)
    {
        $encoder = function (array $matches) {
            return rawurlencode($matches[0]);
        };

        $res = preg_replace_callback($regexp, $encoder, $str);
        if (null !== $res) {
            return $res;
        }

        return rawurlencode($str);
    }

    /**
     * Encode a path string according to RFC3986
     *
     * @param string $str can be a string or an array
     *
     * @return string The same type as the input parameter
     */
    protected static function encodePath($str)
    {
        $regexp = '/(?:[^'
            .self::$unreservedChars
            .self::$subdelimChars
            .'\:\/@]+|%(?!'
            .self::$encodedChars.'))/x';

        return self::encode($str, $regexp);
    }

    /**
     * Decode a component string
     *
     * @param string $str     The string to decode
     * @param string $pattern a regular expression pattern
     *
     * @return string
     */
    protected static function decode($str, $pattern)
    {
        $regexp = ',%'.$pattern.',i';
        $decoder = function (array $matches) use ($regexp) {
            if (strpos("!$&'()*+,;=%", rawurldecode($matches[0]))) {
                return $matches[0];
            }

            if (preg_match($regexp, $matches[0])) {
                return strtoupper($matches[0]);
            }

            return rawurldecode($matches[0]);
        };

        return preg_replace_callback(',%'.self::$encodedChars.',', $decoder, $str);
    }

    /**
     * Decode a component according to RFC3986
     *
     * @param string $str
     *
     * @return string
     */
    protected static function decodeComponent($str)
    {
        return self::decode($str, self::$unreservedCharsEncoded);
    }

    /**
     * Decode a path component according to RFC3986
     *
     * @param string $str
     *
     * @return string
     */
    protected static function decodePath($str)
    {
        return self::decode($str, self::$unreservedCharsEncoded.'|2F');
    }

    /**
     * validate a string
     *
     * @param mixed $str the value to evaluate as a string
     *
     * @throws InvalidArgumentException if the submitted data can not be converted to string
     *
     * @return string
     */
    protected static function validateString($str)
    {
        if (!is_string($str)) {
            throw Exception::fromInvalidString($str);
        }

        if (strlen($str) !== strcspn($str, self::$invalidUriChars)) {
            throw new Exception(sprintf('The submitted string `%s` contains invalid characters', $str));
        }

        return $str;
    }

    /**
     * Returns whether or not the component is defined.
     *
     * @return bool
     */
    public function isDefined()
    {
        return null !== $this->getContent();
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
     * Validate the encoding type value
     *
     * @param int $enc_type
     *
     * @throws Exception If the encoding type is invalid
     */
    protected static function assertValidEncoding($enc_type)
    {
        static $enc_type_list;
        if (null === $enc_type_list) {
            $enc_type_list = [
                UriComponent::RFC3986_ENCODING => 1,
                UriComponent::RFC3987_ENCODING => 1,
                UriComponent::NO_ENCODING => 1,
            ];
        }

        if (!isset($enc_type_list[$enc_type])) {
            throw new Exception(sprintf('Unsupported or Unknown Encoding: %s', $enc_type));
        }
    }

    /**
     * @inheritdoc
     */
    public function __set($property, $value)
    {
        throw Exception::fromInaccessibleProperty($property);
    }

    /**
     * @inheritdoc
     */
    public function __isset($property)
    {
        throw Exception::fromInaccessibleProperty($property);
    }

    /**
     * @inheritdoc
     */
    public function __unset($property)
    {
        throw Exception::fromInaccessibleProperty($property);
    }

    /**
     * @inheritdoc
     */
    public function __get($property)
    {
        throw Exception::fromInaccessibleProperty($property);
    }
}

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

use InvalidArgumentException;

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

        $str = preg_replace_callback($regexp, $encoder, $str);
        $formatter = function (array $matches) {
            return strtoupper($matches[0]);
        };

        return preg_replace_callback(',%'.self::$encodedChars.',', $formatter, $str);
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
        if (is_string($str)) {
            return $str;
        }

        throw new InvalidArgumentException(sprintf(
            'Expected data to be a string; received "%s"',
            (is_object($str) ? get_class($str) : gettype($str))
        ));
    }

    /**
     * @inheritdoc
     */
    public function __set($property, $value)
    {
        throw new InvalidArgumentException(sprintf('%s is an undefined property', $property));
    }

    /**
     * @inheritdoc
     */
    public function __isset($property)
    {
        throw new InvalidArgumentException(sprintf('%s is an undefined property', $property));
    }

    /**
     * @inheritdoc
     */
    public function __unset($property)
    {
        throw new InvalidArgumentException(sprintf('%s is an undefined property', $property));
    }

    /**
     * @inheritdoc
     */
    public function __get($property)
    {
        throw new InvalidArgumentException(sprintf('%s is an undefined property', $property));
    }
}

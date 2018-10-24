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

use Traversable;

/**
 * Common methods for a URI component Value Object.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 *
 * @internal used internally to ease component conversion
 */
trait ComponentTrait
{
    /**
     * Invalid Characters.
     *
     * @see http://tools.ietf.org/html/rfc3986#section-2
     *
     * @var string
     */
    protected static $invalid_uri_chars = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F\x7F";

    /**
     * Encoded Characters regular expression pattern.
     *
     * @see http://tools.ietf.org/html/rfc3986#section-2.1
     *
     * @var string
     */
    protected static $encoded_chars = '[A-Fa-f0-9]{2}';

    /**
     * RFC3986 Sub delimiter characters regular expression pattern.
     *
     * @see http://tools.ietf.org/html/rfc3986#section-2.2
     *
     * @var string
     */
    protected static $subdelim_chars = "\!\$&'\(\)\*\+,;\=%";

    /**
     * RFC3986 unreserved characters regular expression pattern.
     *
     * @see http://tools.ietf.org/html/rfc3986#section-2.3
     *
     * @var string
     */
    protected static $unreserved_chars = 'A-Za-z0-9_\-\.~';

    /**
     * RFC3986 unreserved characters encoded regular expression pattern.
     *
     * @see http://tools.ietf.org/html/rfc3986#section-2.3
     *
     * @var string
     */
    protected static $unreserved_chars_encoded = '2[D|E|5]|3[0-9]|4[1-9|A-F]|5[0-9|A|F]|6[1-9|A-F]|7[0-9|E]';

    /**
     * Encode a component string.
     *
     * @param string $str    The string to encode
     * @param string $regexp a regular expression
     *
     */
    protected static function encode(string $str, string $regexp): string
    {
        $encoder = function (array $matches) {
            if (preg_match('/^[A-Za-z0-9_\-\.~]$/', rawurldecode($matches[0]))) {
                return $matches[0];
            }

            return rawurlencode($matches[0]);
        };

        return preg_replace_callback($regexp, $encoder, $str) ?? rawurlencode($str);
    }

    /**
     * Encode a path string according to RFC3986.
     *
     * @param string $str can be a string or an array
     *
     * @return string The same type as the input parameter
     */
    protected static function encodePath(string $str): string
    {
        $regexp = '/(?:[^'
            .self::$unreserved_chars
            .self::$subdelim_chars
            .'\:\/@]+|%(?!'
            .self::$encoded_chars.'))/x';

        return self::encode($str, $regexp);
    }

    /**
     * Decode a component string.
     *
     * @param string $str     The string to decode
     * @param string $pattern a regular expression pattern
     *
     */
    protected static function decode(string $str, string $pattern): string
    {
        $regexp = ',%'.$pattern.',i';
        $decoder = function (array $matches) use ($regexp) {
            if (preg_match($regexp, $matches[0])) {
                return strtoupper($matches[0]);
            }

            return rawurldecode($matches[0]);
        };

        return preg_replace_callback(',%'.self::$encoded_chars.',', $decoder, $str);
    }

    /**
     * Decode a component according to RFC3986.
     *
     *
     */
    protected static function decodeComponent(string $str): string
    {
        return self::decode($str, self::$unreserved_chars_encoded);
    }

    /**
     * Decode a path component according to RFC3986.
     *
     *
     */
    protected static function decodePath(string $str): string
    {
        return self::decode($str, self::$unreserved_chars_encoded.'|2F');
    }

    /**
     * validate a string.
     *
     * @param string $str the value to evaluate as a string
     *
     * @throws InvalidArgumentException if the submitted data can not be converted to string
     *
     */
    protected static function validateString(string $str): string
    {
        if (strlen($str) !== strcspn($str, self::$invalid_uri_chars)) {
            throw new Exception(sprintf('The submitted string `%s` contains invalid characters', $str));
        }

        return $str;
    }

    /**
     * Validate an Iterator or an array.
     *
     * @param Traversable|array $data
     *
     * @throws InvalidArgumentException if the value can not be converted
     *
     */
    protected static function filterIterable($data): array
    {
        if ($data instanceof Traversable) {
            return iterator_to_array($data);
        }

        if (is_array($data)) {
            return $data;
        }

        throw Exception::fromInvalidIterable($data);
    }

    /**
     * Validate the encoding type value.
     *
     *
     * @throws Exception If the encoding type is invalid
     */
    protected static function assertValidEncoding(int $enc_type)
    {
        static $enc_type_list;
        if (null === $enc_type_list) {
            $enc_type_list = [
                EncodingInterface::RFC1738_ENCODING => 1,
                EncodingInterface::RFC3986_ENCODING => 1,
                EncodingInterface::RFC3987_ENCODING => 1,
                EncodingInterface::NO_ENCODING => 1,
            ];
        }

        if (!isset($enc_type_list[$enc_type])) {
            throw new Exception(sprintf('Unsupported or Unknown Encoding: %s', $enc_type));
        }
    }

    /**
     * Convert a RFC3986 encoded string into a RFC1738 string.
     *
     *
     */
    protected static function toRFC1738(string $str): string
    {
        return str_replace(['+', '~'], ['%2B', '%7E'], $str);
    }

    /**
     * {@inheritdoc}
     */
    public function __set(string $property, $value)
    {
        throw Exception::fromInaccessibleProperty($property);
    }

    /**
     * {@inheritdoc}
     */
    public function __isset(string $property)
    {
        throw Exception::fromInaccessibleProperty($property);
    }

    /**
     * {@inheritdoc}
     */
    public function __unset(string $property)
    {
        throw Exception::fromInaccessibleProperty($property);
    }

    /**
     * {@inheritdoc}
     */
    public function __get(string $property)
    {
        throw Exception::fromInaccessibleProperty($property);
    }
}

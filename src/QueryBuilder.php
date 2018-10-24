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

namespace League\Uri;

use League\Uri\Components\EncodingInterface;
use League\Uri\Components\Exception as UriComponentException;

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
 * @since      1.5.0
 * @see        https://tools.ietf.org/html/rfc3986#section-3.4
 */
class QueryBuilder implements EncodingInterface
{
    const CHARS_LIST = [
        'pattern' => [
            "\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07", "\x08", "\x09",
            "\x0A", "\x0B", "\x0C", "\x0D", "\x0E", "\x0F", "\x10", "\x11", "\x12", "\x13",
            "\x14", "\x15", "\x16", "\x17", "\x18", "\x19", "\x1A", "\x1B", "\x1C", "\x1D",
            "\x1E", "\x1F", "\x7F", '#',
        ],
        'replace' => [
            '%00', '%01', '%02', '%03', '%04', '%05', '%06', '%07', '%08', '%09',
            '%0A', '%0B', '%0C', '%0D', '%0E', '%0F', '%10', '%11', '%12', '%13',
            '%14', '%15', '%16', '%17', '%18', '%19', '%1A', '%1B', '%1C', '%1D',
            '%1E', '%1F', '%7F', '%23',
        ],
    ];

    protected $encoder;

    /**
     * Build a query string from an associative array.
     *
     * The method expects the return value from Query::parse to build
     * a valid query string. This method differs from PHP http_build_query as:
     *
     *    - it does not modify parameters keys
     *
     * @param array|Traversable $pairs     Query pairs
     * @param string            $separator Query string separator
     * @param int               $enc_type  Query encoding type
     *
     */
    public function build(
        $pairs,
        string $separator = '&',
        int $enc_type = self::RFC3986_ENCODING
    ): string {
        $this->encoder = $this->getEncoder($separator, $enc_type);
        $res = [];
        foreach ($pairs as $key => $value) {
            $res = array_merge($res, $this->buildPair($key, $value));
        }

        return implode($separator, $res);
    }

    /**
     * Returns the query string encoding mechanism.
     *
     *
     * @throws UriComponentException If the encoding type is invalid
     *
     */
    protected function getEncoder(string $separator, int $enc_type): callable
    {
        if (self::NO_ENCODING == $enc_type) {
            return 'sprintf';
        }

        if (self::RFC3987_ENCODING == $enc_type) {
            $pattern = self::CHARS_LIST['pattern'];
            $pattern[] = $separator;
            $replace = self::CHARS_LIST['replace'];
            $replace[] = rawurlencode($separator);
            return function ($str) use ($pattern, $replace) {
                return str_replace($pattern, $replace, $str);
            };
        }

        $subdelim = str_replace(html_entity_decode($separator, ENT_HTML5, 'UTF-8'), '', "!$'()*+,;=:@?/&%");
        $regexp = '/(%[A-Fa-f0-9]{2})|[^A-Za-z0-9_\-\.~'.preg_quote($subdelim, '/').']+/u';

        if (self::RFC3986_ENCODING == $enc_type) {
            return function ($str) use ($regexp) {
                return $this->encode((string) $str, $regexp);
            };
        }

        if (self::RFC1738_ENCODING == $enc_type) {
            return function ($str) use ($regexp) {
                return str_replace(
                    ['+', '~'],
                    ['%2B', '%7E'],
                    $this->encode((string) $str, $regexp)
                );
            };
        }

        throw new UriComponentException(sprintf('Unsupported or Unknown Encoding: %s', $enc_type));
    }

    /**
     * Encodes a component string.
     *
     * @param string $str    The string to encode
     * @param string $regexp a regular expression
     *
     */
    protected function encode(string $str, string $regexp): string
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
     * Build a query key/pair association.
     *
     * @param string|int $key   The pair key
     * @param mixed      $value The pair value
     *
     */
    protected function buildPair($key, $value): array
    {
        $normalized_value = $this->normalize($value);
        $key = ($this->encoder)($key);
        $reducer = function (array $carry, $data) use ($key) {
            $carry[] = null === $data ? $key : $key.'='.($this->encoder)($data);

            return $carry;
        };

        return array_reduce($normalized_value, $reducer, []);
    }

    /**
     * Normalize the pair value.
     *
     *
     */
    protected function normalize($content): array
    {
        if (!is_array($content)) {
            return [$this->normalizeValue($content)];
        }

        foreach ($content as &$value) {
            $value = $this->normalizeValue($value);
        }
        unset($value);

        return $content;
    }

    /**
     * Normalize a value.
     *
     *
     * @throws UriComponentException If the value content can not be normalized
     *
     */
    protected function normalizeValue($value)
    {
        if (null === $value || is_scalar($value)) {
            return $value;
        }

        throw new UriComponentException('Invalid value contained in the submitted pairs');
    }
}

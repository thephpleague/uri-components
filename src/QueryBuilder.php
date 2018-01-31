<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.7.0
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace League\Uri;

use League\Uri\Components\EncodingInterface;
use League\Uri\Components\Exception;

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
    /**
     * Invalid Characters
     *
     * @see http://tools.ietf.org/html/rfc3986#section-2
     *
     * @var string
     */
    const INVALID_URI_CHARS = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F\x7F";

    /**
     * Build a query string from an associative array
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
     * @return string
     */
    public function build(
        $pairs,
        string $separator = '&',
        int $enc_type = self::RFC3986_ENCODING
    ): string {
        $encoder = $this->getEncoder($separator, $enc_type);
        $res = [];
        foreach ($pairs as $key => $value) {
            if (!is_array($value)) {
                $value = [$value];
            }

            $res = array_merge($res, $this->buildPair($encoder, $value, $key));
        }

        return implode($separator, $res);
    }

    /**
     * Returns the query string encoding mechanism.
     *
     * @param string $separator
     * @param int    $enc_type
     *
     * @throws Exception If the encoding type is invalid
     *
     * @return callable
     */
    protected function getEncoder(string $separator, int $enc_type): callable
    {
        if (self::NO_ENCODING == $enc_type) {
            return 'sprintf';
        }

        if (self::RFC3987_ENCODING == $enc_type) {
            $pattern = str_split(self::INVALID_URI_CHARS);
            $pattern[] = '#';
            $pattern[] = $separator;
            $replace = array_map('rawurlencode', $pattern);
            return function ($str) use ($pattern, $replace) {
                return str_replace($pattern, $replace, $str);
            };
        }

        $separator = html_entity_decode($separator, ENT_HTML5, 'UTF-8');
        $subdelim = str_replace($separator, '', "!$'()*+,;=:@?/&%");
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

        throw new Exception(sprintf('Unsupported or Unknown Encoding: %s', $enc_type));
    }

    /**
     * Encodes a component string.
     *
     * @param string $str    The string to encode
     * @param string $regexp a regular expression
     *
     * @return string
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
     * @param callable   $encoder a callable to encode the key/pair association
     * @param array      $value   The query string value
     * @param string|int $key     The query string key
     *
     * @return array
     */
    protected function buildPair(callable $encoder, array $value, $key): array
    {
        $key = $encoder($key);
        $reducer = function (array $carry, $data) use ($key, $encoder) {
            $pair = $key;
            if (null !== $data) {
                $pair .= '='.$encoder($data);
            }
            $carry[] = $pair;

            return $carry;
        };

        foreach ($value as $val) {
            if ($val !== null && !is_scalar($val)) {
                throw new Exception('Invalid value contained in the submitted pairs');
            }
        }

        return array_reduce($value, $reducer, []);
    }
}

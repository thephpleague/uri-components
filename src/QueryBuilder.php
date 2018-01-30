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

use League\Uri\Components\ComponentTrait;
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
class QueryBuilder
{
    use ComponentTrait;

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
        int $enc_type = EncodingInterface::RFC3986_ENCODING
    ): string {
        if ($pairs instanceof Traversable) {
            $pairs = iterator_to_array($pairs, true);
        }
        $this->assertValidPairs($pairs);
        $this->assertValidEncoding($enc_type);
        $encoder = $this->getEncoder($separator, $enc_type);

        return $this->getQueryString($pairs, $separator, $encoder);
    }

    /**
     * Filter the submitted pair array.
     *
     * @param array $pairs
     *
     * @throws Exception If the array contains non valid data
     */
    protected function assertValidPairs(array $pairs)
    {
        $invalid = array_filter($pairs, function ($value) {
            if (!is_array($value)) {
                $value = [$value];
            }

            return array_filter($value, function ($val) {
                return $val !== null && !is_scalar($val);
            });
        });

        if (empty($invalid)) {
            return;
        }

        throw new Exception('Invalid value contained in the submitted pairs');
    }

    /**
     *subject Return the query string encoding mechanism
     *
     * @param string $separator
     * @param int    $enc_type
     *
     * @return callable
     */
    protected function getEncoder(string $separator, int $enc_type): callable
    {
        if (EncodingInterface::NO_ENCODING == $enc_type) {
            return 'sprintf';
        }

        if (EncodingInterface::RFC3987_ENCODING == $enc_type) {
            $pattern = str_split(self::$invalid_uri_chars);
            $pattern[] = '#';
            $pattern[] = $separator;
            $replace = array_map('rawurlencode', $pattern);
            return function ($str) use ($pattern, $replace) {
                return str_replace($pattern, $replace, $str);
            };
        }

        $separator = html_entity_decode($separator, ENT_HTML5, 'UTF-8');
        $subdelim = str_replace($separator, '', "!$'()*+,;=:@?/&%");
        $regexp = '/(%[A-Fa-f0-9]{2})|[^'.self::$unreserved_chars.preg_quote($subdelim, '/').']+/u';

        if (EncodingInterface::RFC3986_ENCODING == $enc_type) {
            return function ($str) use ($regexp) {
                return $this->encode((string) $str, $regexp);
            };
        }

        return function ($str) use ($regexp) {
            return $this->toRFC1738($this->encode((string) $str, $regexp));
        };
    }

    /**
     * Build a query string from an associative array
     *
     * The method expects the return value from Query::parse to build
     * a valid query string. This method differs from PHP http_build_query as:
     *
     *    - it does not modify parameters keys
     *
     * @param array    $pairs     Query pairs
     * @param string   $separator Query string separator
     * @param callable $encoder   Query encoder
     *
     * @return string
     */
    protected function getQueryString(array $pairs, string $separator, callable $encoder): string
    {
        $normalized_pairs = array_map(function ($value) {
            return !is_array($value) ? [$value] : $value;
        }, $pairs);

        $arr = [];
        foreach ($normalized_pairs as $key => $value) {
            $arr = array_merge($arr, $this->buildPair($encoder, $value, $key));
        }

        return implode($separator, $arr);
    }

    /**
     * Build a query key/pair association
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

        return array_reduce($value, $reducer, []);
    }
}

<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.2.0

 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace League\Uri\Components;

/**
 * a class to parse a URI query string according to RFC3986
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
trait QueryParserTrait
{
    use ComponentTrait;

    /**
     * Parse a query string into an associative array
     *
     * Multiple identical key will generate an array. This function
     * differ from PHP parse_str as:
     *    - it does not modify or remove parameters keys
     *    - it does not create nested array
     *
     * @param string $str       The query string to parse
     * @param string $separator The query string separator
     * @param int    $enc_type  The query encoding algorithm
     *
     * @return array
     */
    public static function parse(
        string $str,
        string $separator = '&',
        int $enc_type = ComponentInterface::RFC3986_ENCODING
    ): array {
        self::assertValidEncoding($enc_type);

        $res = [];
        if ('' === $str) {
            return $res;
        }

        $decoder = self::getDecoder($enc_type);
        foreach (explode($separator, $str) as $pair) {
            $res = self::parsePair($res, $pair, $separator, $decoder);
        }

        return $res;
    }

    protected static function getDecoder(int $enc_type): callable
    {
        if (ComponentInterface::RFC1738_ENCODING === $enc_type) {
            return function ($value) {
                return self::decodeComponent(str_replace('+', ' ', $value));
            };
        }

        return [Query::class, 'decodeComponent'];
    }

    /**
     * Parse a query string pair
     *
     * @param array    $res       The associative array to add the pair to
     * @param string   $pair      The query string pair
     * @param string   $separator The query string separator
     * @param callable $decoder   The query string decoder
     *
     * @return array
     */
    protected static function parsePair(
        array $res,
        string $pair,
        string $separator,
        callable $decoder
    ): array {
        $encoded_sep = rawurlencode($separator);
        $param = explode('=', $pair, 2);
        $key = $decoder(array_shift($param));

        if ($key === '') {
            return $res;
        }

        $value = array_shift($param);
        if (null !== $value) {
            $value = str_replace($encoded_sep, $separator, $decoder($value));
        }

        if (!array_key_exists($key, $res)) {
            $res[$key] = $value;
            return $res;
        }

        if (!is_array($res[$key])) {
            $res[$key] = [$res[$key]];
        }
        $res[$key][] = $value;

        return $res;
    }

    /**
     * Build a query string from an associative array
     *
     * The method expects the return value from Query::parse to build
     * a valid query string. This method differs from PHP http_build_query as:
     *
     *    - it does not modify parameters keys
     *
     * @param array  $pairs     Query pairs
     * @param string $separator Query string separator
     * @param int    $enc_type  Query encoding type
     *
     * @return string
     */
    public static function build(
        array $pairs,
        string $separator = '&',
        int $enc_type = Query::RFC3986_ENCODING
    ): string {
        self::assertValidPairs($pairs);
        self::assertValidEncoding($enc_type);
        $encoder = self::getEncoder($separator, $enc_type);

        return self::getQueryString($pairs, $separator, $encoder);
    }

    /**
     * Filter the submitted pair array.
     *
     * @param array $pairs
     *
     * @throws Exception If the array contains non valid data
     */
    protected static function assertValidPairs(array $pairs)
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
    protected static function getQueryString(array $pairs, string $separator, callable $encoder): string
    {
        $normalized_pairs = array_map(function ($value) {
            return !is_array($value) ? [$value] : $value;
        }, $pairs);

        $arr = [];
        foreach ($normalized_pairs as $key => $value) {
            $arr = array_merge($arr, self::buildPair($encoder, $value, $key));
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
    protected static function buildPair(callable $encoder, array $value, $key): array
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

    /**
     *subject Return the query string encoding mechanism
     *
     * @param string $separator
     * @param int    $enc_type
     *
     * @return callable
     */
    protected static function getEncoder(string $separator, int $enc_type): callable
    {
        if (Query::NO_ENCODING == $enc_type) {
            return 'sprintf';
        }

        if (Query::RFC3987_ENCODING == $enc_type) {
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

        if (Query::RFC3986_ENCODING == $enc_type) {
            return function ($str) use ($regexp) {
                return self::encode((string) $str, $regexp);
            };
        }

        return function ($str) use ($regexp) {
            return self::toRFC1738(self::encode((string) $str, $regexp));
        };
    }

    /**
     * Returns the store PHP variables as elements of an array.
     *
     * The result is similar as PHP parse_str when used with its
     * second argument with the difference that variable names are
     * not mangled.
     *
     * @see http://php.net/parse_str
     * @see https://wiki.php.net/rfc/on_demand_name_mangling
     *
     * @param string $str       the query string
     * @param string $separator a the query string single character separator
     * @param string $enc_type  the query encoding
     *
     * @return array
     */
    public static function extract(
        string $str,
        string $separator = '&',
        int $enc_type = ComponentInterface::RFC3986_ENCODING
    ): array {
        return self::extractFromPairs(self::parse($str, $separator, $enc_type));
    }

    /**
     * Returns the store PHP variables as elements of an array.
     *
     * The result is similar as PHP parse_str when used with its
     * second argument with the difference that variable names are
     * not mangled.
     *
     * @see http://php.net/parse_str
     * @see https://wiki.php.net/rfc/on_demand_name_mangling
     *
     * @param array $pairs the query string pairs
     *
     * @return array
     */
    protected static function extractFromPairs(array $pairs): array
    {
        $data = [];
        $normalized_pairs = array_map(function ($value) {
            return !is_array($value) ? [$value] : $value;
        }, $pairs);

        foreach ($normalized_pairs as $name => $value) {
            foreach ($value as $val) {
                self::extractPhpVariable(trim((string) $name), self::formatParsedValue($val), $data);
            }
        }

        return $data;
    }

    /**
     * Format the value of the parse query array
     *
     * @param mixed $value
     *
     * @return string
     */
    protected static function formatParsedValue($value): string
    {
        if (null === $value) {
            return '';
        }

        return rawurldecode($value);
    }

    /**
     * Parse a query pairs like parse_str but wihout mangling the results array keys.
     *
     * <ul>
     * <li>empty name are not saved</li>
     * <li>If the value from name is duplicated its corresponding value will
     * be overwritten</li>
     * <li>if no "[" is detected the value is added to the return array with the name as index</li>
     * <li>if no "]" is detected after detecting a "[" the value is added to the return array with the name as index</li>
     * <li>if there's a mismatch in bracket usage the remaining part is dropped</li>
     * <li>“.” and “ ” are not converted to “_”</li>
     * <li>If there is no “]”, then the first “[” is not converted to becomes an “_”</li>
     * </ul>
     *
     * @see https://php.net/parse_str
     * @see https://wiki.php.net/rfc/on_demand_name_mangling
     * @see https://github.com/php/php-src/blob/master/ext/standard/tests/strings/parse_str_basic1.phpt
     * @see https://github.com/php/php-src/blob/master/ext/standard/tests/strings/parse_str_basic2.phpt
     * @see https://github.com/php/php-src/blob/master/ext/standard/tests/strings/parse_str_basic3.phpt
     * @see https://github.com/php/php-src/blob/master/ext/standard/tests/strings/parse_str_basic4.phpt
     *
     * @param string $name  the query pair key
     * @param string $value the formatted value
     * @param array  &$data the result array passed by reference
     */
    protected static function extractPhpVariable(string $name, string $value, array &$data)
    {
        if ('' === $name) {
            return;
        }

        if (false === ($left_bracket_pos = strpos($name, '['))) {
            $data[$name] = $value;
            return;
        }

        if (false === ($right_bracket_pos = strpos($name, ']', $left_bracket_pos))) {
            $data[$name] = $value;
            return;
        }

        $key = substr($name, 0, $left_bracket_pos);
        if (!array_key_exists($key, $data) || !is_array($data[$key])) {
            $data[$key] = [];
        }

        $index = substr($name, $left_bracket_pos + 1, $right_bracket_pos - $left_bracket_pos - 1);
        if ('' === $index) {
            $data[$key][] = $value;
            return;
        }

        $remaining = substr($name, $right_bracket_pos + 1);
        if ('[' !== substr($remaining, 0, 1) || false === strpos($remaining, ']', 1)) {
            $remaining = '';
        }

        self::extractPhpVariable($index.$remaining, $value, $data[$key]);
    }
}

<?php

/**
 * League.Uri (http://uri.thephpleague.com/components)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.2
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri;

use League\Uri\Exceptions\SyntaxError;
use TypeError;
use function array_key_exists;
use function array_keys;
use function explode;
use function html_entity_decode;
use function implode;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_scalar;
use function is_string;
use function method_exists;
use function preg_match;
use function preg_quote;
use function preg_replace_callback;
use function rawurldecode;
use function rawurlencode;
use function sprintf;
use function str_replace;
use function strpos;
use function substr;
use const PHP_QUERY_RFC1738;
use const PHP_QUERY_RFC3986;

/**
 * A class to parse the URI query string.
 *
 * @see https://tools.ietf.org/html/rfc3986#section-3.4
 */
final class QueryString
{
    private const REGEXP_INVALID_CHARS = '/[\x00-\x1f\x7f]/';

    private const REGEXP_ENCODED_PATTERN = ',%[A-Fa-f0-9]{2},';

    private const REGEXP_UNRESERVED_CHAR = '/[^A-Za-z0-9_\-\.~]/';

    private const ENCODING_LIST = [
        PHP_QUERY_RFC1738 => [
            'suffixKey' => '*',
            'suffixValue' => '*=&',
        ],
        PHP_QUERY_RFC3986 => [
            'suffixKey' => "!$'()*+,;:@?/%",
            'suffixValue' => "!$'()*+,;=:@?/&%",
        ],
    ];

    private const DECODE_PAIR_VALUE = 1;

    private const PRESERVE_PAIR_VALUE = 2;

    /**
     * @var string
     */
    private static $regexpKey;

    /**
     * @var string
     */
    private static $regexpValue;

    /**
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    /**
     * Parses a query string into a collection of key/value pairs.
     *
     * @param mixed|null $query
     *
     * @throws SyntaxError
     *
     * @return array<int, array{0:string, 1:string|null}>
     */
    public static function parse($query, string $separator = '&', int $enc_type = PHP_QUERY_RFC3986): array
    {
        if ('' === $separator) {
            throw new SyntaxError('The separator character can not be the empty string.');
        }

        $query = self::prepareQuery($query, $enc_type);
        if (null === $query) {
            return [];
        }

        if ('' === $query) {
            return [['', null]];
        }

        $retval = [];
        foreach (self::getPairs($query, $separator) as $pairString) {
            /** @var array{0:string, 1:string|null} $pair */
            $pair = self::parsePair((string) $pairString, self::DECODE_PAIR_VALUE);
            $retval[] = $pair;
        }

        return $retval;
    }

    /**
     * Prepare and normalize query before processing.
     *
     * @param mixed|null $query
     *
     * @throws SyntaxError If the encoding type is invalid
     * @throws SyntaxError If the query string is invalid
     * @throws TypeError   If the query is not stringable or the null value
     */
    private static function prepareQuery($query, int $enc_type): ?string
    {
        if (!isset(self::ENCODING_LIST[$enc_type])) {
            throw new SyntaxError('Unknown or Unsupported encoding');
        }

        if (null === $query) {
            return $query;
        }

        if (is_bool($query)) {
            return true === $query ? '1' : '0';
        }

        if (!is_scalar($query) && !method_exists($query, '__toString')) {
            throw new TypeError(sprintf('The query must be a scalar, a stringable object or the `null` value, `%s` given', gettype($query)));
        }

        $query = (string) $query;
        if ('' === $query) {
            return $query;
        }

        if (1 === preg_match(self::REGEXP_INVALID_CHARS, $query)) {
            throw new SyntaxError(sprintf('Invalid query string: %s', $query));
        }

        if (PHP_QUERY_RFC1738 === $enc_type) {
            return str_replace('+', ' ', $query);
        }

        return $query;
    }

    private static function getPairs(string $query, string $separator): array
    {
        if (false === strpos($query, $separator)) {
            return [$query];
        }

        /** @var array $pairs */
        $pairs = explode($separator, $query);

        return $pairs;
    }

    /**
     * Returns the key/value pair from a query string pair.
     *
     * array{0:string, 1:string|null}
     */
    private static function parsePair(string $pair, int $parseValue): array
    {
        [$key, $value] = explode('=', $pair, 2) + [1 => null];
        $key = (string) $key;

        if (1 === preg_match(self::REGEXP_ENCODED_PATTERN, $key)) {
            $key = preg_replace_callback(self::REGEXP_ENCODED_PATTERN, [self::class, 'decodeMatch'], $key);
        }

        if (null === $value) {
            return [$key, $value];
        }

        if ($parseValue === self::DECODE_PAIR_VALUE && 1 === preg_match(self::REGEXP_ENCODED_PATTERN, $value)) {
            $value = preg_replace_callback(self::REGEXP_ENCODED_PATTERN, [self::class, 'decodeMatch'], $value);
        }

        return [$key, $value];
    }

    /**
     * Decodes a match string.
     */
    private static function decodeMatch(array $matches): string
    {
        return rawurldecode($matches[0]);
    }

    /**
     * Build a query string from an associative array.
     *
     * The method expects the return value from Query::parse to build
     * a valid query string. This method differs from PHP http_build_query as
     * it does not modify parameters keys.
     *
     * @param array<int, array{0:string, 1:string|null}> $pairs
     *
     * @throws SyntaxError If the encoding type is invalid
     * @throws SyntaxError If a pair is invalid
     */
    public static function build(iterable $pairs, string $separator = '&', int $enc_type = PHP_QUERY_RFC3986): ?string
    {
        if ('' === $separator) {
            throw new SyntaxError('The separator character can not be the empty string.');
        }

        if (!isset(self::ENCODING_LIST[$enc_type])) {
            throw new SyntaxError('Unknown or Unsupported encoding');
        }

        self::$regexpValue = '/(%[A-Fa-f0-9]{2})|[^A-Za-z0-9_\-\.~'.preg_quote(
            str_replace(
                html_entity_decode($separator, ENT_HTML5, 'UTF-8'),
                '',
                self::ENCODING_LIST[$enc_type]['suffixValue']
            ),
            '/'
        ).']+/ux';

        self::$regexpKey = '/(%[A-Fa-f0-9]{2})|[^A-Za-z0-9_\-\.~'.preg_quote(
            str_replace(
                html_entity_decode($separator, ENT_HTML5, 'UTF-8'),
                '',
                self::ENCODING_LIST[$enc_type]['suffixKey']
            ),
            '/'
        ).']+/ux';

        $res = [];
        foreach ($pairs as $pair) {
            $res[] = self::buildPair($pair);
        }

        if ([] === $res) {
            return null;
        }

        $query = implode($separator, $res);
        if (PHP_QUERY_RFC1738 === $enc_type) {
            return str_replace(['+', '%20'], ['%2B', '+'], $query);
        }

        return $query;
    }

    /**
     * Build a RFC3986 query key/value pair association.
     *
     *
     * @throws SyntaxError If the pair is invalid
     */
    private static function buildPair(array $pair): string
    {
        if ([0, 1] !== array_keys($pair)) {
            throw new SyntaxError('A pair must be a sequential array starting at `0` and containing two elements.');
        }

        [$name, $value] = $pair;
        if (!is_scalar($name)) {
            throw new SyntaxError(sprintf('A pair key must be a scalar value `%s` given.', gettype($name)));
        }

        if (is_bool($name)) {
            $name = (int) $name;
        }

        if (is_string($name) && 1 === preg_match(self::$regexpKey, $name)) {
            $name = preg_replace_callback(self::$regexpKey, [self::class, 'encodeMatches'], $name);
        }

        if (is_string($value)) {
            if (1 !== preg_match(self::$regexpValue, $value)) {
                return $name.'='.$value;
            }

            return $name.'='.preg_replace_callback(self::$regexpValue, [self::class, 'encodeMatches'], $value);
        }

        if (is_numeric($value)) {
            return $name.'='.$value;
        }

        if (is_bool($value)) {
            return $name.'='.(int) $value;
        }

        if (null === $value) {
            return (string) $name;
        }

        throw new SyntaxError(sprintf('A pair value must be a scalar value or the null value, `%s` given.', gettype($value)));
    }

    /**
     * Encodes matched sequences.
     */
    private static function encodeMatches(array $matches): string
    {
        if (1 === preg_match(self::REGEXP_UNRESERVED_CHAR, rawurldecode($matches[0]))) {
            return rawurlencode($matches[0]);
        }

        return $matches[0];
    }

    /**
     * Parses the query string like parse_str without mangling the results.
     *
     * The result is similar as PHP parse_str when used with its
     * second argument with the difference that variable names are
     * not mangled.
     *
     * @see http://php.net/parse_str
     * @see https://wiki.php.net/rfc/on_demand_name_mangling
     *
     * @param mixed|null $query
     */
    public static function extract($query, string $separator = '&', int $enc_type = PHP_QUERY_RFC3986): array
    {
        $query = self::prepareQuery($query, $enc_type);
        if (null === $query || '' === $query) {
            return [];
        }

        $retval = [];
        foreach (self::getPairs($query, $separator) as $pair) {
            $retval[] = self::parsePair((string) $pair, self::PRESERVE_PAIR_VALUE);
        }

        return self::convert($retval);
    }

    /**
     * Converts a collection of key/value pairs and returns
     * the store PHP variables as elements of an array.
     */
    public static function convert(iterable $pairs): array
    {
        $retval = [];
        foreach ($pairs as $pair) {
            $retval = self::extractPhpVariable($retval, $pair);
        }

        return $retval;
    }

    /**
     * Parses a query pair like parse_str without mangling the results array keys.
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
     * <li>no whitespace trimming is done on the key value</li>
     * </ul>
     *
     * @see https://php.net/parse_str
     * @see https://wiki.php.net/rfc/on_demand_name_mangling
     * @see https://github.com/php/php-src/blob/master/ext/standard/tests/strings/parse_str_basic1.phpt
     * @see https://github.com/php/php-src/blob/master/ext/standard/tests/strings/parse_str_basic2.phpt
     * @see https://github.com/php/php-src/blob/master/ext/standard/tests/strings/parse_str_basic3.phpt
     * @see https://github.com/php/php-src/blob/master/ext/standard/tests/strings/parse_str_basic4.phpt
     *
     * @param array        $data  the submitted array
     * @param array|string $name  the pair key
     * @param string       $value the pair value
     */
    private static function extractPhpVariable(array $data, $name, string $value = ''): array
    {
        if (is_array($name)) {
            [$name, $value] = $name;
            $value = rawurldecode((string) $value);
        }

        if ('' === $name) {
            return $data;
        }

        $left_bracket_pos = strpos($name, '[');
        if (false === $left_bracket_pos) {
            $data[$name] = $value;

            return $data;
        }

        $right_bracket_pos = strpos($name, ']', $left_bracket_pos);
        if (false === $right_bracket_pos) {
            $data[$name] = $value;

            return $data;
        }

        $key = substr($name, 0, $left_bracket_pos);
        if (!array_key_exists($key, $data) || !is_array($data[$key])) {
            $data[$key] = [];
        }

        $index = substr($name, $left_bracket_pos + 1, $right_bracket_pos - $left_bracket_pos - 1);
        if ('' === $index) {
            $data[$key][] = $value;

            return $data;
        }

        $remaining = substr($name, $right_bracket_pos + 1);
        if ('[' !== substr($remaining, 0, 1) || false === strpos($remaining, ']', 1)) {
            $remaining = '';
        }

        $data[$key] = self::extractPhpVariable($data[$key], $index.$remaining, $value);

        return $data;
    }
}

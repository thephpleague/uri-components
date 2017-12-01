<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.5.0
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace League\Uri;

use League\Uri\Components\ComponentTrait;
use League\Uri\Components\EncodingInterface;
use Traversable;
use TypeError;

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
class QueryParser
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
    public function parse(
        string $str,
        string $separator = '&',
        int $enc_type = EncodingInterface::RFC3986_ENCODING
    ): array {
        $this->assertValidEncoding($enc_type);

        $res = [];
        if ('' === $str) {
            return $res;
        }

        $decoder = $this->getDecoder($enc_type);
        foreach (explode($separator, $str) as $pair) {
            $res = $this->parsePair($res, $pair, $separator, $decoder);
        }

        return $res;
    }

    protected function getDecoder(int $enc_type): callable
    {
        if (EncodingInterface::RFC1738_ENCODING === $enc_type) {
            return function ($value) {
                return $this->decodeComponent(str_replace('+', ' ', $value));
            };
        }

        return [$this, 'decodeComponent'];
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
    protected function parsePair(
        array $res,
        string $pair,
        string $separator,
        callable $decoder
    ): array {
        $encoded_sep = rawurlencode($separator);
        $param = explode('=', $pair, 2);
        $key = $decoder(array_shift($param));
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
     * @param int    $enc_type  the query encoding
     *
     * @return array
     */
    public function extract(
        string $str,
        string $separator = '&',
        int $enc_type = EncodingInterface::RFC3986_ENCODING
    ): array {
        $pairs = $this->parse($str, $separator, $enc_type);

        return $this->convert($pairs);
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
     * @param Traversable|array $pairs the query string pairs
     *
     * @return array
     */
    public function convert($pairs): array
    {
        if ($pairs instanceof Traversable) {
            $pairs = iterator_to_array($pairs);
        }

        if (!is_array($pairs)) {
            throw new TypeError(sprintf('%s() expects argument passed to be iterable, %s given', __METHOD__, gettype($pairs)));
        }

        $data = [];
        $normalized_pairs = array_map(function ($value) {
            return !is_array($value) ? [$value] : $value;
        }, $pairs);

        foreach ($normalized_pairs as $name => $value) {
            foreach ($value as $val) {
                $val = $this->formatParsedValue($val);
                $this->extractPhpVariable(trim((string) $name), $val, $data);
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
    protected function formatParsedValue($value): string
    {
        if (null === $value) {
            return '';
        }

        if (!is_scalar($value) || (is_object($value) && !method_exists($value, '__toString'))) {
            throw new TypeError(sprintf('QueryParser::convert() expects pairs value to contains null or scalar values, %s given', gettype($value)));
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return rawurldecode((string) $value);
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
     * @param array  $data  the result array passed by reference
     */
    protected function extractPhpVariable(string $name, string $value, array &$data)
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

        $this->extractPhpVariable($index.$remaining, $value, $data[$key]);
    }
}

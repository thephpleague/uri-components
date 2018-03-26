<?php
/**
 * League.Uri (http://uri.thephpleague.com).
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.0
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace League\Uri;

use League\Uri\Components\Query;
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
final class QueryParser implements EncodingInterface
{
    /**
     * @internal
     */
    const ENCODING_LIST = [
        self::RFC1738_ENCODING => 1,
        self::RFC3986_ENCODING => 1,
        self::RFC3987_ENCODING => 1,
        self::NO_ENCODING => 1,
    ];

    /**
     * @var int
     */
    private $enc_type;

    /**
     * @var string
     */
    private $separator;

    /**
     * @var string
     */
    private $encoded_separator;

    /**
     * Parse a query string into an associative array.
     *
     * Multiple identical key will generate an array. This function
     * differ from PHP parse_str as:
     *    - it does not modify or remove parameters keys
     *    - it does not create nested array
     *
     * @param mixed  $query     The query string to parse
     * @param string $separator The query string separator
     * @param int    $enc_type  The query encoding algorithm
     *
     * @return array
     */
    public function parse($query, string $separator = '&', int $enc_type = self::RFC3986_ENCODING): array
    {
        if (!isset(self::ENCODING_LIST[$enc_type])) {
            throw new Exception(sprintf('Unsupported or Unknown Encoding: %s', $enc_type));
        }

        if ($query instanceof ComponentInterface) {
            $query = $query->getContent();
        }

        if (null === $query) {
            return [];
        }

        if (!is_scalar($query) && !method_exists($query, '__toString')) {
            throw new TypeError(sprintf('The query must be a scalar or a stringable object `%s` given', gettype($query)));
        }

        if (!is_string($query)) {
            $query = (string) $query;
        }

        if ('' === $query) {
            return [['', null]];
        }

        static $pattern = '/[\x00-\x1f\x7f]/';
        if (preg_match($pattern, $query)) {
            throw new Exception(sprintf('Invalid query string: %s', $query));
        }

        $this->separator = $separator;
        $this->encoded_separator = rawurlencode($separator);
        $this->enc_type = $enc_type;

        return array_map([$this, 'parsePair'], explode($separator, $query));
    }

    /**
     * Decode a component according to RFC3986.
     *
     * @param string $str
     *
     * @return string
     */
    private function decode(string $str): string
    {
        static $encoded_pattern = ',%[A-Fa-f0-9]{2},';
        static $decoded_pattern = ',%2[D|E]|3[0-9]|4[1-9|A-F]|5[0-9|A|F]|6[1-9|A-F]|7[0-9|E],i';
        $decoder = function (array $matches) use ($decoded_pattern) {
            if (preg_match($decoded_pattern, $matches[0])) {
                return strtoupper($matches[0]);
            }

            return rawurldecode($matches[0]);
        };

        $str = preg_replace_callback($encoded_pattern, $decoder, $str);
        if (self::RFC1738_ENCODING !== $this->enc_type || false === strpos($str, '+')) {
            return $str;
        }

        return str_replace('+', ' ', $str);
    }

    /**
     * Parse a query string pair.
     *
     * @param string $pair The query string pair
     *
     * @return array
     */
    private function parsePair(string $pair): array
    {
        static $encoded_pattern = ',%[A-Fa-f0-9]{2},';
        list($key, $value) = explode('=', $pair, 2) + [1 => null];
        if (null !== $value) {
            if (preg_match($encoded_pattern, $value)) {
                $value = $this->replace($this->decode($value), $this->encoded_separator, $this->separator);
            } elseif (self::RFC1738_ENCODING === $this->enc_type) {
                $value = $this->replace($value, '+', ' ');
            }
        }

        if (preg_match($encoded_pattern, $key)) {
            $key = $this->decode($key);
        }

        return [$key, $value];
    }

    private function replace(string $value, string $pattern, string $replace)
    {
        if (false === strpos($value, $pattern)) {
            return $value;
        }

        return str_replace($pattern, $replace, $value);
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
     * @param null|string $str       the query string
     * @param string      $separator a the query string single character separator
     * @param int         $enc_type  the query encoding
     *
     * @return array
     */
    public function extract($str, string $separator = '&', int $enc_type = self::RFC3986_ENCODING): array
    {
        $data = [];
        foreach ($this->parse($str, $separator, $enc_type) as $value) {
            if (null === ($value[1] ?? null)) {
                $value[1] = '';
            }

            $this->extractPhpVariable(trim((string) $value[0]), rawurldecode((string) $value[1]), $data);
        }

        return $data;
    }

    /**
     * Parse a query pairs like parse_str but without mangling the results array keys.
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
    private function extractPhpVariable(string $name, string $value, array &$data)
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

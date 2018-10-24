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
class QueryParser implements EncodingInterface
{
    const ENCODING_LIST = [
        self::RFC1738_ENCODING => 1,
        self::RFC3986_ENCODING => 1,
        self::RFC3987_ENCODING => 1,
        self::NO_ENCODING => 1,
    ];

    /**
     * @var callable
     */
    private $decoder;

    /**
     * @var string
     */
    protected $separator;

    /**
     * @var string
     */
    protected $encoded_sep;

    /**
     * Parse a query string into an associative array.
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
     */
    public function parse(
        string $str,
        string $separator = '&',
        int $enc_type = self::RFC3986_ENCODING
    ): array {
        $this->decoder = $this->getDecoder($enc_type);

        if ('' === $str) {
            return [];
        }

        $this->separator = $separator;
        $this->encoded_sep = rawurlencode($separator);

        return array_reduce(explode($separator, $str), [$this, 'parsePair'], []);
    }

    /**
     * Returns the query string decoding mechanism.
     *
     *
     * @throws UriComponentException
     *
     */
    protected function getDecoder(int $enc_type): callable
    {
        if (!isset(self::ENCODING_LIST[$enc_type])) {
            throw new UriComponentException(sprintf('Unsupported or Unknown Encoding: %s', $enc_type));
        }

        if (self::RFC1738_ENCODING === $enc_type) {
            return function ($value) {
                return $this->decode(str_replace('+', ' ', $value));
            };
        }

        return [$this, 'decode'];
    }

    /**
     * Decode a component according to RFC3986.
     *
     *
     */
    protected function decode(string $str): string
    {
        $decoder = function (array $matches) {
            if (preg_match(',%2[D|E]|3[0-9]|4[1-9|A-F]|5[0-9|A|F]|6[1-9|A-F]|7[0-9|E],i', $matches[0])) {
                return strtoupper($matches[0]);
            }

            return rawurldecode($matches[0]);
        };

        return preg_replace_callback(',%[A-Fa-f0-9]{2},', $decoder, $str);
    }

    /**
     * Parse a query string pair.
     *
     * @param array  $res  The associative array to add the pair to
     * @param string $pair The query string pair
     *
     */
    protected function parsePair(array $res, string $pair): array
    {
        $param = explode('=', $pair, 2);
        $key = ($this->decoder)(array_shift($param));
        $value = array_shift($param);
        if (null !== $value) {
            $value = str_replace($this->encoded_sep, $this->separator, ($this->decoder)($value));
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
     */
    public function extract(string $str, string $separator = '&', int $enc_type = self::RFC3986_ENCODING): array
    {
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
     */
    public function convert($pairs): array
    {
        if (!$pairs instanceof Traversable && !is_array($pairs)) {
            throw new TypeError(sprintf('%s() expects argument passed to be iterable, %s given', __METHOD__, gettype($pairs)));
        }

        $data = [];
        foreach ($pairs as $name => $value) {
            if (!is_array($value)) {
                $value = [$value];
            }

            foreach ($value as $val) {
                $this->extractPhpVariable(trim((string) $name), $this->normalize($val), $data);
            }
        }

        return $data;
    }

    /**
     * Format the value of the parse query array.
     *
     *
     */
    protected function normalize($value): string
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

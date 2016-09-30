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

/**
 * a class to parse a URI query string according to RFC3986
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
trait QueryParser
{
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
     *
     * @return array
     */
    public static function parse($str, $separator = '&')
    {
        $res = [];
        if ('' === $str) {
            return $res;
        }

        foreach (explode($separator, $str) as $pair) {
            $res = self::parsePair($res, $pair);
        }

        return $res;
    }

    /**
     * Parse a query string pair
     *
     * @param array  $res  The associative array to add the pair to
     * @param string $pair The query string pair
     *
     * @return array
     */
    protected static function parsePair(array $res, $pair)
    {
        $param = explode('=', $pair, 2);
        $key = self::decodeComponent(array_shift($param));
        $value = array_shift($param);
        if (null !== $value) {
            $value = self::decodeComponent($value);
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
     * Decode a component according to RFC3986
     *
     * @param string $str
     *
     * @return string
     */
    abstract protected static function decodeComponent($str);

    /**
     * Build a query string from an associative array
     *
     * The method expects the return value from Query::parse to build
     * a valid query string. This method differs from PHP http_build_query as:
     *
     *    - it does not modify parameters keys
     *
     * @param array  $arr       Query string parameters
     * @param string $separator Query string separator
     *
     * @return string
     */
    public static function build(array $arr, $separator = '&')
    {
        $encoder = self::getEncoder($separator);
        $arr = array_map(function ($value) {
            return !is_array($value) ? [$value] : $value;
        }, $arr);

        $pairs = [];
        foreach ($arr as $key => $value) {
            $pairs = array_merge($pairs, self::buildPair($encoder, $value, $key));
        }

        return implode($separator, $pairs);
    }

    /**
     * Build a query key/pair association
     *
     * @param callable $encoder a callable to encode the key/pair association
     * @param array    $value   The query string value
     * @param string   $key     The query string key
     *
     * @return string
     */
    protected static function buildPair(callable $encoder, array $value, $key)
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
     * @param int|bool $encodingType
     *
     * @return callable
     */
    protected static function getEncoder($separator)
    {
        $separator = html_entity_decode($separator, ENT_HTML5, 'UTF-8');
        $subdelimChars = str_replace($separator, '', "!$'()*+,;=%:@?/&");
        $regexp = '/(?:[^'
            .self::$unreservedChars
            .preg_quote($subdelimChars, '/')
            .']+|%(?!'.self::$encodedChars
            .'))/x';

        return function ($str) use ($regexp) {
            return self::encode($str, $regexp);
        };
    }

    /**
     * Encode a component string
     *
     * @param string $str    The string to encode
     * @param string $regexp a regular expression
     *
     * @return string
     */
    abstract protected static function encode($str, $regexp);
}

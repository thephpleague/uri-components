<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package   League.uri
 * @author    Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @copyright 2017 Ignace Nyamagana Butera
 * @license   https://github.com/thephpleague/uri/blob/master/LICENSE (MIT License)
 * @version   1.1.1
 * @link      https://github.com/thephpleague/uri/
 */
declare(strict_types=1);

namespace League\Uri;

use League\Uri\Components\Query;

/**
 * Build a query string from an associative array
 *
 * @see Query::build
 *
 * @param array  $pairs     The query pairs
 * @param string $separator The query string separator
 * @param int    $enc_type  The query encoding type
 *
 * @return string
 */
function build_query(array $pairs, string $separator = '&', int $enc_type = PHP_QUERY_RFC3986): string
{
    return Query::build($pairs, $separator, $enc_type);
}

/**
 * Parse a query string into an associative array
 *
 * @see Query::parse
 *
 * @param string $query     The query string to parse
 * @param string $separator The query string separator
 * @param int    $enc_type  The query encoding algorithm
 *
 * @return array
 */
function parse_query(string $query, string $separator = '&', int $enc_type = PHP_QUERY_RFC3986): array
{
    return Query::parse($query, $separator, $enc_type);
}

/**
 * Parse the query string like parse_str without mangling the results
 *
 * @see Query::extract
 *
 * @param string $query     The query string to parse
 * @param string $separator The query string separator
 * @param int    $enc_type  The query encoding algorithm
 *
 * @return array
 */
function extract_query(string $query, string $separator = '&', int $enc_type = PHP_QUERY_RFC3986): array
{
    return Query::extract($query, $separator, $enc_type);
}

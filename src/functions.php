<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package   League.uri
 * @author    Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @copyright 2016 Ignace Nyamagana Butera
 * @license   https://github.com/thephpleague/uri/blob/master/LICENSE (MIT License)
 * @version   4.2.0
 * @link      https://github.com/thephpleague/uri/
 */
declare(strict_types=1);

namespace League\Uri;

use League\Uri\Components\Query;

/**
 * Parse the query string like parse_str without mangling the results
 *
 * @see Query::extract
 *
 * @param string $query
 * @param string $separator
 * @param int    $enc_type
 *
 * @return array
 */
function extract_params(string $query, string $separator = '&', int $enc_type = PHP_QUERY_RFC3986): array
{
    return Query::extract($query, $separator, $enc_type);
}

/**
 * Parse a query string into an associative array
 *
 * @see Query::parse
 *
 * @param string $query
 * @param string $separator
 * @param int    $enc_type
 *
 * @return array
 */
function parse_query(string $query, string $separator = '&', int $enc_type = PHP_QUERY_RFC3986): array
{
    return Query::parse($query, $separator, $enc_type);
}

/**
 * Build a query string from an associative array
 *
 * @see Query::build
 *
 * @param array  $pairs     Query pairs
 * @param string $separator Query string separator
 * @param int    $enc_type  Query encoding type
 *
 * @return string
 */
function build_query(array $pairs, string $separator = '&', int $enc_type = Query::RFC3986_ENCODING): string
{
    return Query::build($pairs, $separator, $enc_type);
}

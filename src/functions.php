<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package   League.uri
 * @author    Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @copyright 2017 Ignace Nyamagana Butera
 * @license   https://github.com/thephpleague/uri/blob/master/LICENSE (MIT License)
 * @version   1.4.0
 * @link      https://github.com/thephpleague/uri/
 */
declare(strict_types=1);

namespace League\Uri;

use Traversable;

/**
 * Build a query string from an associative array
 *
 * @see QueryBuilder::build
 *
 * @param array|Traversable $pairs     The query pairs
 * @param string            $separator The query string separator
 * @param int               $enc_type  The query encoding type
 *
 * @return string
 */
function build_query($pairs, string $separator = '&', int $enc_type = PHP_QUERY_RFC3986): string
{
    static $builder;

    $builder = $builder ?? new QueryBuilder();

    return $builder->build($pairs, $separator, $enc_type);
}

/**
 * Parse a query string into an associative array
 *
 * @see QueryParser::parse
 *
 * @param string $query     The query string to parse
 * @param string $separator The query string separator
 * @param int    $enc_type  The query encoding algorithm
 *
 * @return array
 */
function parse_query(string $query, string $separator = '&', int $enc_type = PHP_QUERY_RFC3986): array
{
    static $parser;

    $parser = $parser ?? new QueryParser();

    return $parser->parse($query, $separator, $enc_type);
}

/**
 * Parse the query string like parse_str without mangling the results
 *
 * @see QueryParser::extract
 *
 * @param string $query     The query string to parse
 * @param string $separator The query string separator
 * @param int    $enc_type  The query encoding algorithm
 *
 * @return array
 */
function extract_query(string $query, string $separator = '&', int $enc_type = PHP_QUERY_RFC3986): array
{
    static $parser;

    $parser = $parser ?? new QueryParser();

    return $parser->extract($query, $separator, $enc_type);
}

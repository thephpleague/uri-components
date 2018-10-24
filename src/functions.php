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

use Traversable;

/**
 * Build a query string from an associative array.
 *
 * @see QueryBuilder::build
 *
 * @param array|Traversable $pairs     The query pairs
 * @param string            $separator The query string separator
 * @param int               $enc_type  The query encoding type
 *
 */
function build_query($pairs, string $separator = '&', int $enc_type = PHP_QUERY_RFC3986): string
{
    static $builder;

    $builder = $builder ?? new QueryBuilder();

    return $builder->build($pairs, $separator, $enc_type);
}

/**
 * Parse a query string into an associative array of key/value pairs.
 *
 * @see QueryParser::parse
 *
 * @param string $query     The query string to parse
 * @param string $separator The query string separator
 * @param int    $enc_type  The query encoding algorithm
 *
 */
function parse_query(string $query, string $separator = '&', int $enc_type = PHP_QUERY_RFC3986): array
{
    static $parser;

    $parser = $parser ?? new QueryParser();

    return $parser->parse($query, $separator, $enc_type);
}

/**
 * Parse the query string like parse_str without mangling the results.
 *
 * @see QueryParser::extract
 *
 * @param string $query     The query string to parse
 * @param string $separator The query string separator
 * @param int    $enc_type  The query encoding algorithm
 *
 */
function extract_query(string $query, string $separator = '&', int $enc_type = PHP_QUERY_RFC3986): array
{
    static $parser;

    $parser = $parser ?? new QueryParser();

    return $parser->extract($query, $separator, $enc_type);
}

/**
 * Convert a Collection of key/value pairs into PHP variables.
 *
 * @see QueryParser::convert
 *
 * @param Traversable|array $pairs The collection of key/value pairs
 *
 */
function pairs_to_params($pairs): array
{
    static $parser;

    $parser = $parser ?? new QueryParser();

    return $parser->convert($pairs);
}

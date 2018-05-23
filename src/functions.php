<?php

/**
 * League.Uri (http://uri.thephpleague.com).
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.0
 * @link       https://github.com/thephpleague/uri-schemes
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri;

use League\Uri\Components\DataPath;
use League\Uri\Components\HierarchicalPath;
use League\Uri\Components\Host;
use League\Uri\Components\Path;
use League\Uri\Components\Query;
use League\Uri\Converter\StringConverter;
use League\Uri\Interfaces\Uri as DeprecatedLeagueUriInterface;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use TypeError;

/**
 * Filter the URI object.
 *
 * To be valid an URI MUST implement at least one of the following interface:
 *     - Psr\Http\Message\UriInterface
 *     - League\Uri\Interfaces\Uri
 *
 * @param mixed $uri
 *
 * @throws TypeError if the URI object does not implements the supported interfaces.
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function filter_uri($uri)
{
    if ($uri instanceof DeprecatedLeagueUriInterface
        || $uri instanceof Psr7UriInterface
        || $uri instanceof UriInterface
    ) {
        return $uri;
    }

    throw new TypeError(sprintf('The uri must be a valid URI object received `%s`', gettype($uri)));
}

/**
 * Add a new basepath to the URI path.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 * @param mixed                                                      $path
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function add_basepath($uri, $path)
{
    filter_uri($uri);

    $currentpath = $uri->getPath();
    if ('' !== $currentpath && '/' !== $currentpath[0]) {
        $currentpath = '/'.$currentpath;
    }

    $path = (new HierarchicalPath($path))->withLeadingSlash();
    if (0 === strpos($currentpath, (string) $path)) {
        return $uri->withPath($currentpath);
    }

    return $uri->withPath((string) $path->append($currentpath));
}

/**
 * Add a leading slash to the URI path.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function add_leading_slash($uri)
{
    $path = filter_uri($uri)->getPath();
    if ('/' !== ($path[0] ?? '')) {
        $path = '/'.$path;
    }

    return normalize_path($uri, $path);
}

/**
 * Add the root label to the URI.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function add_root_label($uri)
{
    return $uri->withHost((string) (new Host(filter_uri($uri)->getHost()))->withRootLabel());
}

/**
 * Add a trailing slash to the URI path.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function add_trailing_slash($uri)
{
    $path = filter_uri($uri)->getPath();
    if ('/' !== substr($path, -1)) {
        $path .= '/';
    }

    return normalize_path($uri, $path);
}

/**
 * Append a label or a host to the current URI host.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 * @param mixed                                                      $host
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function append_host($uri, $host)
{
    return $uri->withHost((string) (new Host(filter_uri($uri)->getHost()))->append($host));
}

/**
 * Append an new segment or a new path to the URI path.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 * @param string                                                     $path
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function append_path($uri, string $path)
{
    return normalize_path($uri, (string) (new HierarchicalPath(filter_uri($uri)->getPath()))->append($path));
}

/**
 * Add the new query data to the existing URI query.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 * @param mixed                                                      $query
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function append_query($uri, $query)
{
    return $uri->withQuery((string) (new Query(filter_uri($uri)->getQuery()))->append($query));
}

/**
 * Convert the URI host part to its ascii value.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function host_to_ascii($uri)
{
    return $uri->withHost((string) (new Host(filter_uri($uri)->getHost())));
}

/**
 * Convert the URI host part to its unicode value.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function host_to_unicode($uri)
{
    return $uri->withHost((string) (new Host(filter_uri($uri)->getHost()))->getContent(Host::RFC3987_ENCODING));
}

/**
 * Merge a new query with the existing URI query.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 * @param mixed                                                      $query
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function merge_query($uri, $query)
{
    return $uri->withQuery((string) (new Query(filter_uri($uri)->getQuery()))->merge($query));
}

/**
 * Convert the Data URI path to its ascii form.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function datapath_to_ascii($uri)
{
    return $uri->withPath((string) (new DataPath(filter_uri($uri)->getPath()))->toAscii());
}

/**
 * Convert the Data URI path to its binary (base64encoded) form.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function datapath_to_binary($uri)
{
    return $uri->withPath((string) (new DataPath(filter_uri($uri)->getPath()))->toBinary());
}

/**
 * Prepend a label or a host to the current URI host.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 * @param mixed                                                      $host
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function prepend_host($uri, $host)
{
    return $uri->withHost((string) (new Host(filter_uri($uri)->getHost()))->prepend($host));
}

/**
 * Prepend an new segment or a new path to the URI path.
 *
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 * @param mixed                                                      $path
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function prepend_path($uri, $path)
{
    return normalize_path($uri, (string) (new HierarchicalPath(filter_uri($uri)->getPath()))->prepend($path));
}

/**
 * Remove a basepath from the URI path.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 * @param mixed                                                      $path
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function remove_basepath($uri, $path)
{
    $uri = normalize_path($uri);
    $basepath = (new HierarchicalPath($path))->withLeadingSlash();
    if ('/' === (string) $basepath) {
        return $uri;
    }

    $currentpath = $uri->getPath();
    if (0 !== strpos($currentpath, (string) $basepath)) {
        return $uri;
    }

    return $uri->withPath(
        (string) (new HierarchicalPath($currentpath))->withoutSegments(...range(0, count($basepath) - 1))
    );
}

/**
 * Remove dot segments from the URI path.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function remove_dot_segments($uri)
{
    return normalize_path($uri, (string) (new Path(filter_uri($uri)->getPath()))->withoutDotSegments());
}

/**
 * Normalize a URI path.
 *
 * Make sure the path always has a leading slash if an authority is present
 * and the path is not the empty string.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 * @param string|null                                                $path
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function normalize_path($uri, string $path = null)
{
    filter_uri($uri);

    $path = $path ?? $uri->getPath();
    if ('' != $uri->getAuthority() && '' != $path && '/' != $path[0]) {
        return $uri->withPath('/'.$path);
    }

    return $uri->withPath($path);
}

/**
 * Remove empty segments from the URI path.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function remove_empty_segments($uri)
{
    return normalize_path($uri, (string) (new Path(filter_uri($uri)->getPath()))->withoutEmptySegments());
}

/**
 * Remove host labels according to their offset.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 * @param int[]                                                      $keys
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function remove_labels($uri, array $keys)
{
    return $uri->withHost((string) (new Host(filter_uri($uri)->getHost()))->withoutLabels(...$keys));
}

/**
 * Remove the leading slash from the URI path.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function remove_leading_slash($uri)
{
    $path = filter_uri($uri)->getPath();
    if ('' !== $path && '/' === $path[0]) {
        $path = substr($path, 1);
    }

    return normalize_path($uri, $path);
}

/**
 * Remove query data according to their key name.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 * @param string[]                                                   $keys
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function remove_params($uri, array $keys)
{
    return $uri->withQuery((string) (new Query(filter_uri($uri)->getQuery()))->withoutParams(...$keys));
}

/**
 * Remove query data according to their key name.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 * @param string[]                                                   $keys
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function remove_pairs($uri, array $keys)
{
    return $uri->withQuery((string) (new Query(filter_uri($uri)->getQuery()))->withoutPairs(...$keys));
}

/**
 * Remove the root label to the URI.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function remove_root_label($uri)
{
    return $uri->withHost((string) (new Host(filter_uri($uri)->getHost()))->withoutRootLabel());
}

/**
 * Remove the trailing slash from the URI path.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function remove_trailing_slash($uri)
{
    $path = filter_uri($uri)->getPath();
    if ('' !== $path && '/' === substr($path, -1)) {
        $path = substr($path, 0, -1);
    }

    return normalize_path($uri, $path);
}

/**
 * Remove path segments from the URI path according to their offsets.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 * @param int[]                                                      $keys
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function remove_segments($uri, array $keys)
{
    return normalize_path($uri, (string) (new HierarchicalPath(filter_uri($uri)->getPath()))->withoutSegments(...$keys));
}

/**
 * Remove the host zone identifier.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function remove_zone_id($uri)
{
    return $uri->withHost((string) (new Host(filter_uri($uri)->getHost()))->withoutZoneIdentifier());
}

/**
 * Replace the URI path basename.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 * @param mixed                                                      $path
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function replace_basename($uri, $path)
{
    return normalize_path($uri, (string) (new HierarchicalPath(filter_uri($uri)->getPath()))->withBasename($path));
}

/**
 * Replace the data URI path parameters.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 * @param string                                                     $parameters
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function replace_data_uri_parameters($uri, string $parameters)
{
    return normalize_path($uri, (string) (new DataPath(filter_uri($uri)->getPath()))->withParameters($parameters));
}

/**
 * Replace the URI path dirname.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 * @param mixed                                                      $path
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function replace_dirname($uri, $path)
{
    return normalize_path($uri, (string) (new HierarchicalPath(filter_uri($uri)->getPath()))->withDirname($path));
}

/**
 * Replace the URI path basename extension.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 * @param mixed                                                      $extension
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function replace_extension($uri, $extension)
{
    return normalize_path($uri, (string) (new HierarchicalPath(filter_uri($uri)->getPath()))->withExtension($extension));
}

/**
 * Replace a label of the current URI host.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 * @param int                                                        $offset
 * @param mixed                                                      $host
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function replace_label($uri, int $offset, $host)
{
    return $uri->withHost((string) (new Host(filter_uri($uri)->getHost()))->withLabel($offset, $host));
}

/**
 * Replace a segment from the URI path according its offset.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 * @param int                                                        $offset
 * @param mixed                                                      $path
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function replace_segment($uri, int $offset, $path)
{
    return normalize_path($uri, (string) (new HierarchicalPath(filter_uri($uri)->getPath()))->withSegment($offset, $path));
}

/**
 * Sort the URI query by keys.
 *
 * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
 *
 * @return DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface
 */
function sort_query($uri)
{
    return $uri->withQuery((string) (new Query(filter_uri($uri)->getQuery()))->sort());
}

/**
 * Returns the RFC3986 string representation of the given URI object or URI Component object.
 *
 * @param mixed  $payload
 * @param string $separator
 *
 * @return string
 */
function to_ascii($payload, string $separator = '&')
{
    static $converter;

    $converter = $converter ?? new StringConverter();

    return $converter->convert($payload, StringConverter::RFC3986_ENCODING, $separator);
}

/**
 * Returns the RFC3987 string representation of the given URI object or URI Component object.
 *
 * @param mixed  $payload
 * @param string $separator
 *
 * @return string
 */
function to_unicode($payload, string $separator = '&'): string
{
    static $converter;

    $converter = $converter ?? new StringConverter();

    return $converter->convert($payload, StringConverter::RFC3987_ENCODING, $separator);
}

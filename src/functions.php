<?php
/**
 * League.Uri (http://uri.thephpleague.com).
 *
 * @package    League.uri
 * @subpackage League\Uri\Modifiers
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @copyright  2017 Ignace Nyamagana Butera
 * @license    https://github.com/thephpleague/uri-manipulations/blob/master/LICENSE (MIT License)
 * @version    1.5.0
 * @link       https://github.com/thephpleague/uri-manipulations
 */
declare(strict_types=1);

namespace League\Uri;

use League\Uri\Components\ComponentInterface;
use League\Uri\Components\DataPath;
use League\Uri\Components\Fragment;
use League\Uri\Components\HierarchicalPath;
use League\Uri\Components\Host;
use League\Uri\Components\Path;
use League\Uri\Components\Query;
use League\Uri\Interfaces\Uri as LeagueUriInterface;
use Psr\Http\Message\UriInterface;
use TypeError;

/**
 * Tells whether the URI object is valid.
 *
 * To be valid an URI MUST implement at least one of the following interface:
 *     - Psr\Http\Message\UriInterface
 *     - League\Uri\Interfaces\Uri
 *
 * @param mixed $uri
 *
 * @return bool
 */
function is_uri($uri): bool
{
    return $uri instanceof LeagueUriInterface || $uri instanceof UriInterface;
}

/**
 * Filter the URI object.
 *
 * @see \League\Uri\is_uri
 *
 * @param mixed $uri
 *
 * @throws TypeError if the URI object does not implements the supported interfaces.
 *
 * @return LeagueUriInterface|UriInterface
 */
function filter_uri($uri)
{
    if (is_uri($uri)) {
        return $uri;
    }

    throw new TypeError(sprintf('The uri must be a valid URI object received `%s`', gettype($uri)));
}

/**
 * Add a new basepath to the URI path.
 *
 * @param LeagueUriInterface|UriInterface $uri
 * @param mixed                           $path
 *
 * @return LeagueUriInterface|UriInterface
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
 * @param LeagueUriInterface|UriInterface $uri
 *
 * @return LeagueUriInterface|UriInterface
 */
function add_leading_slash($uri)
{
    filter_uri($uri);

    $path = $uri->getPath();
    if ('/' !== $path) {
        $path = '/'.$path;
    }

    return $uri->withPath($path);
}

/**
 * Add the root label to the URI.
 *
 * @param LeagueUriInterface|UriInterface $uri
 *
 * @return LeagueUriInterface|UriInterface
 */
function add_root_label($uri)
{
    filter_uri($uri);

    return $uri->withHost((string) (new Host($uri->getHost()))->withRootLabel());
}

/**
 * Add a trailing slash to the URI path.
 *
 * @param LeagueUriInterface|UriInterface $uri
 *
 * @return LeagueUriInterface|UriInterface
 */
function add_trailing_slash($uri)
{
    filter_uri($uri);
    $path = $uri->getPath();
    if ('/' !== substr($path, -1)) {
        $path .= '/';
    }

    return normalize_path($uri, $path);
}

/**
 * Append a label or a host to the current URI host.
 *
 * @param LeagueUriInterface|UriInterface $uri
 * @param mixed                           $host
 *
 * @return LeagueUriInterface|UriInterface
 */
function append_host($uri, $host)
{
    filter_uri($uri);

    return $uri->withHost((string) (new Host($uri->getHost()))->append($host));
}

/**
 * Append an new segment or a new path to the URI path.
 *
 * @param LeagueUriInterface|UriInterface $uri
 * @param string                          $path
 *
 * @return LeagueUriInterface|UriInterface
 */
function append_path($uri, string $path)
{
    filter_uri($uri);

    return normalize_path($uri, (string) (new HierarchicalPath($uri->getPath()))->append($path));
}

/**
 * Add the new query data to the existing URI query.
 *
 * @param LeagueUriInterface|UriInterface $uri
 * @param mixed                           $query
 *
 * @return LeagueUriInterface|UriInterface
 */
function append_query($uri, $query)
{
    filter_uri($uri);

    return $uri->withQuery((string) (new Query($uri->getQuery()))->append($query));
}

/**
 * Convert the URI host part to its ascii value.
 *
 * @param LeagueUriInterface|UriInterface $uri
 *
 * @return LeagueUriInterface|UriInterface
 */
function host_to_ascii($uri)
{
    filter_uri($uri);

    return $uri->withHost((string) (new Host($uri->getHost())));
}

/**
 * Convert the URI host part to its unicode value.
 *
 * @param LeagueUriInterface|UriInterface $uri
 *
 * @return LeagueUriInterface|UriInterface
 */
function host_to_unicode($uri)
{
    filter_uri($uri);

    return $uri->withHost((string) (new Host($uri->getHost()))->getContent(Host::RFC3987_ENCODING));
}

/**
 * Tell whether the URI represents an absolute URI.
 *
 * @param LeagueUriInterface|UriInterface $uri
 *
 * @return bool
 */
function is_absolute($uri): bool
{
    filter_uri($uri);

    return '' !== $uri->getScheme();
}

/**
 * Tell whether the URI represents an absolute path.
 *
 * @param LeagueUriInterface|UriInterface $uri
 *
 * @return bool
 */
function is_absolute_path($uri): bool
{
    filter_uri($uri);

    return '' === $uri->getScheme()
        && '' === $uri->getAuthority()
        && '/' === substr($uri->getPath(), 0, 1);
}

/**
 * Tell whether the URI represents a network path.
 *
 * @param LeagueUriInterface|UriInterface $uri
 *
 * @return bool
 */
function is_network_path($uri): bool
{
    filter_uri($uri);

    return '' === $uri->getScheme() && '' !== $uri->getAuthority();
}

/**
 * Tell whether the URI represents a relative path.
 *
 * @param LeagueUriInterface|UriInterface $uri
 *
 * @return bool
 */
function is_relative_path($uri): bool
{
    filter_uri($uri);

    return '' === $uri->getScheme() && '' === $uri->getAuthority() && '/' !== substr($uri->getPath(), 0, 1);
}

/**
 * Tell whether both URI refers to the same document.
 *
 * @param LeagueUriInterface|UriInterface $uri
 * @param LeagueUriInterface|UriInterface $base_uri
 *
 * @return bool
 */
function is_same_document($uri, $base_uri): bool
{
    filter_uri($uri);

    return (string) normalize($uri)->withFragment('') === (string) normalize($base_uri)->withFragment('');
}

/**
 * Merge a new query with the existing URI query.
 *
 * @param LeagueUriInterface|UriInterface $uri
 * @param mixed                           $query
 *
 * @return LeagueUriInterface|UriInterface
 */
function merge_query($uri, $query)
{
    filter_uri($uri);

    return $uri->withQuery((string) (new Query($uri->getQuery()))->merge($query));
}

/**
 * Normalize an URI for comparison.
 *
 * @param LeagueUriInterface|UriInterface $uri
 *
 * @return LeagueUriInterface|UriInterface
 */
function normalize($uri)
{
    filter_uri($uri);

    $path = $uri->getPath();
    if ('/' === ($path[0] ?? '') || '' !== $uri->getScheme().$uri->getAuthority()) {
        $path = (string) (new Path($path))->withoutDotSegments();
    }
    $query = (string) (new Query($uri->getQuery()))->sort();
    $fragment = (string) (new Fragment($uri->getFragment()));

    $replace = function (array $matches): string {
        return rawurldecode($matches[0]);
    };

    static $regexp = ',%(2[D|E]|3[0-9]|4[1-9|A-F]|5[0-9|A|F]|6[1-9|A-F]|7[0-9|E]),i';
    list($path, $query, $fragment) = preg_replace_callback($regexp, $replace, [$path, $query, $fragment]);

    return $uri
        ->withHost((string) (new Host($uri->getHost())))
        ->withPath($path)
        ->withQuery($query)
        ->withFragment($fragment)
    ;
}

/**
 * Convert the Data URI path to its ascii form.
 *
 * @param LeagueUriInterface|UriInterface $uri
 *
 * @return LeagueUriInterface|UriInterface
 */
function path_to_ascii($uri)
{
    filter_uri($uri);

    return $uri->withPath((string) (new DataPath($uri->getPath()))->toAscii());
}

/**
 * Convert the Data URI path to its binary (base64encoded) form.
 *
 * @param LeagueUriInterface|UriInterface $uri
 *
 * @return LeagueUriInterface|UriInterface
 */
function path_to_binary($uri)
{
    filter_uri($uri);

    return $uri->withPath((string) (new DataPath($uri->getPath()))->toBinary());
}

/**
 * Prepend a label or a host to the current URI host.
 *
 * @param LeagueUriInterface|UriInterface $uri
 * @param mixed                           $host
 *
 * @return LeagueUriInterface|UriInterface
 */
function prepend_host($uri, $host)
{
    filter_uri($uri);

    return $uri->withHost((string) (new Host($uri->getHost()))->prepend($host));
}

/**
 * Prepend an new segment or a new path to the URI path.
 *
 *
 * @param LeagueUriInterface|UriInterface $uri
 * @param mixed                           $path
 *
 * @return LeagueUriInterface|UriInterface
 */
function prepend_path($uri, $path)
{
    filter_uri($uri);

    return normalize_path($uri, (string) (new HierarchicalPath($uri->getPath()))->prepend($path));
}

/**
 * Relativize an URI against a base URI.
 *
 * @see Relativizer::relativize()
 *
 * @param LeagueUriInterface|UriInterface $uri
 * @param LeagueUriInterface|UriInterface $base_uri
 *
 * @return LeagueUriInterface|UriInterface
 */
function relativize($uri, $base_uri)
{
    static $relativizer;

    $relativizer = $relativizer ?? new Relativizer();

    return $relativizer->relativize($uri, $base_uri);
}

/**
 * Remove a basepath from the URI path.
 *
 * @param LeagueUriInterface|UriInterface $uri
 * @param mixed                           $path
 *
 * @return LeagueUriInterface|UriInterface
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
 * @param LeagueUriInterface|UriInterface $uri
 *
 * @return LeagueUriInterface|UriInterface
 */
function remove_dot_segments($uri)
{
    filter_uri($uri);

    return normalize_path($uri, (string) (new Path($uri->getPath()))->withoutDotSegments());
}

/**
 * Normalize a URI path.
 *
 * Make sure the path always has a leading slash if an authority is present
 * and the path is not the empty string.
 *
 * @param LeagueUriInterface|UriInterface $uri
 * @param string|null                     $path
 *
 * @return LeagueUriInterface|UriInterface
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
 * @param LeagueUriInterface|UriInterface $uri
 *
 * @return LeagueUriInterface|UriInterface
 */
function remove_empty_segments($uri)
{
    filter_uri($uri);

    return normalize_path($uri, (string) (new Path($uri->getPath()))->withoutEmptySegments());
}

/**
 * Remove host labels according to their offset.
 *
 * @param LeagueUriInterface|UriInterface $uri
 * @param int[]                           $keys
 *
 * @return LeagueUriInterface|UriInterface
 */
function remove_labels($uri, array $keys)
{
    filter_uri($uri);

    return $uri->withHost((string) (new Host($uri->getHost()))->withoutLabels(...$keys));
}

/**
 * Remove the leading slash from the URI path.
 *
 * @param LeagueUriInterface|UriInterface $uri
 *
 * @return LeagueUriInterface|UriInterface
 */
function remove_leading_slash($uri)
{
    filter_uri($uri);

    $path = $uri->getPath();
    if ('' !== $path && '/' === $path[0]) {
        $path = substr($path, 1);
    }

    return normalize_path($uri, $path);
}

/**
 * Remove query data according to their key name.
 *
 * @param LeagueUriInterface|UriInterface $uri
 * @param string[]                        $keys
 *
 * @return LeagueUriInterface|UriInterface
 */
function remove_params($uri, array $keys)
{
    filter_uri($uri);

    return $uri->withQuery((string) (new Query($uri->getQuery()))->withoutParams(...$keys));
}

/**
 * Remove query data according to their key name.
 *
 * @param LeagueUriInterface|UriInterface $uri
 * @param string[]                        $keys
 *
 * @return LeagueUriInterface|UriInterface
 */
function remove_pairs($uri, array $keys)
{
    filter_uri($uri);

    return $uri->withQuery((string) (new Query($uri->getQuery()))->withoutPairs(...$keys));
}

/**
 * Remove the root label to the URI.
 *
 * @param LeagueUriInterface|UriInterface $uri
 *
 * @return LeagueUriInterface|UriInterface
 */
function remove_root_label($uri)
{
    filter_uri($uri);

    return $uri->withHost((string) (new Host($uri->getHost()))->withoutRootLabel());
}

/**
 * Remove the trailing slash from the URI path.
 *
 * @param LeagueUriInterface|UriInterface $uri
 *
 * @return LeagueUriInterface|UriInterface
 */
function remove_trailing_slash($uri)
{
    filter_uri($uri);
    $path = $uri->getPath();
    if ('' !== $path && '/' === substr($path, -1)) {
        $path = substr($path, 0, -1);
    }

    return normalize_path($uri, $path);
}

/**
 * Remove path segments from the URI path according to their offsets.
 *
 * @param LeagueUriInterface|UriInterface $uri
 * @param int[]                           $keys
 *
 * @return LeagueUriInterface|UriInterface
 */
function remove_segments($uri, array $keys)
{
    filter_uri($uri);

    return normalize_path($uri, (string) (new HierarchicalPath($uri->getPath()))->withoutSegments(...$keys));
}

/**
 * Remove the host zone identifier.
 *
 * @param LeagueUriInterface|UriInterface $uri
 *
 * @return LeagueUriInterface|UriInterface
 */
function remove_zone_id($uri)
{
    filter_uri($uri);

    return $uri->withHost((string) (new Host($uri->getHost()))->withoutZoneIdentifier());
}

/**
 * Replace the URI path basename.
 *
 * @param mixed $uri
 * @param mixed $path
 *
 * @return LeagueUriInterface|UriInterface
 */
function replace_basename($uri, $path)
{
    filter_uri($uri);

    return normalize_path($uri, (string) (new HierarchicalPath($uri->getPath()))->withBasename($path));
}

/**
 * Replace the data URI path parameters.
 *
 * @param mixed  $uri
 * @param string $parameters
 *
 * @return LeagueUriInterface|UriInterface
 */
function replace_data_uri_parameters($uri, string $parameters)
{
    filter_uri($uri);

    return normalize_path($uri, (string) (new DataPath($uri->getPath()))->withParameters($parameters));
}

/**
 * Replace the URI path dirname.
 *
 * @param mixed $uri
 * @param mixed $path
 *
 * @return LeagueUriInterface|UriInterface
 */
function replace_dirname($uri, $path)
{
    filter_uri($uri);

    return normalize_path($uri, (string) (new HierarchicalPath($uri->getPath()))->withDirname($path));
}

/**
 * Replace the URI path basename extension.
 *
 * @param mixed $uri
 * @param mixed $extension
 *
 * @return LeagueUriInterface|UriInterface
 */
function replace_extension($uri, $extension)
{
    filter_uri($uri);

    return normalize_path($uri, (string) (new HierarchicalPath($uri->getPath()))->withExtension($extension));
}

/**
 * Replace a label of the current URI host.
 *
 * @param mixed $uri
 * @param int   $offset
 * @param mixed $host
 *
 * @return LeagueUriInterface|UriInterface
 */
function replace_label($uri, int $offset, $host)
{
    filter_uri($uri);

    return $uri->withHost((string) (new Host($uri->getHost()))->withLabel($offset, $host));
}

/**
 * Replace a segment from the URI path according its offset.
 *
 * @param mixed $uri
 * @param int   $offset
 * @param mixed $path
 *
 * @return LeagueUriInterface|UriInterface
 */
function replace_segment($uri, int $offset, $path)
{
    filter_uri($uri);

    return normalize_path($uri, (string) (new HierarchicalPath($uri->getPath()))->withSegment($offset, $path));
}

/**
 * Resolve an URI against a base URI.
 *
 * @see Resolver::resolve()
 *
 * @param LeagueUriInterface|UriInterface $uri
 * @param LeagueUriInterface|UriInterface $base_uri
 *
 * @return LeagueUriInterface|UriInterface
 */
function resolve($uri, $base_uri)
{
    static $resolver;

    $resolver = $resolver ?? new Resolver();

    return $resolver->resolve($uri, $base_uri);
}

/**
 * Sort the URI query by keys.
 *
 * @param LeagueUriInterface|UriInterface $uri
 *
 * @return LeagueUriInterface|UriInterface
 */
function sort_query($uri)
{
    filter_uri($uri);

    return $uri->withQuery((string) (new Query($uri->getQuery()))->sort());
}

/**
 * Returns the RFC3986 string representation of the given URI object or URI Component object.
 *
 * @param mixed  $payload
 * @param string $separator
 *
 * @return string
 */
function uri_to_ascii($payload, string $separator = '&')
{
    static $formatter;
    if (null === $formatter) {
        $formatter = $formatter ?? new Formatter();
        $formatter->setEncoding(Formatter::RFC3986_ENCODING);
    }

    $formatter->setQuerySeparator($separator);
    if ($payload instanceof ComponentInterface) {
        return $formatter->format($payload);
    }

    filter_uri($payload);
    list($remaining_uri, $fragment) = explode('#', (string) $payload, 2) + ['', null];
    list(, $query) = explode('?', $remaining_uri, 2) + ['', null];

    $formatter->preserveFragment(null !== $fragment);
    $formatter->preserveQuery(null !== $query);

    return $formatter->format($payload);
}

/**
 * Returns the RFC3987 string representation of the given URI object or URI Component object.
 *
 * @param mixed  $payload
 * @param string $separator
 *
 * @return string
 */
function uri_to_unicode($payload, string $separator = '&'): string
{
    static $formatter;
    if (null === $formatter) {
        $formatter = $formatter ?? new Formatter();
        $formatter->setEncoding(Formatter::RFC3987_ENCODING);
    }

    $formatter->setQuerySeparator($separator);
    if ($payload instanceof ComponentInterface) {
        return $formatter->format($payload);
    }

    filter_uri($payload);
    list($remaining_uri, $fragment) = explode('#', (string) $payload, 2) + [1 => null];
    list(, $query) = explode('?', $remaining_uri, 2) + [1 => null];

    $formatter->preserveFragment(null !== $fragment);
    $formatter->preserveQuery(null !== $query);

    return $formatter->format($payload);
}

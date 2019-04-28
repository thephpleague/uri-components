<?php

/**
 * League.Uri (http://uri.thephpleague.com/components)
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

use League\Uri\Component\DataPath;
use League\Uri\Component\Domain;
use League\Uri\Component\HierarchicalPath;
use League\Uri\Component\Host;
use League\Uri\Component\Path;
use League\Uri\Component\Query;
use League\Uri\Contract\PathInterface;
use League\Uri\Contract\UriInterface;
use League\Uri\Exception\SyntaxError;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use TypeError;
use function count;
use function get_class;
use function gettype;
use function ltrim;
use function range;
use function rtrim;
use function sprintf;
use function strpos;
use function substr;

final class UriModifier
{
    /**
     * Filter the URI object.
     *
     * To be valid an URI MUST implement at least one of the following interface:
     *     - League\Uri\UriInterface
     *     - Psr\Http\Message\UriInterface
     *
     *
     * @throws TypeError if the URI object does not implements the supported interfaces.
     *
     * @return Psr7UriInterface|UriInterface
     */
    private static function filterUri($uri)
    {
        if ($uri instanceof Psr7UriInterface || $uri instanceof UriInterface) {
            return $uri;
        }

        throw new TypeError(sprintf('The uri must be a valid URI object received `%s`', is_object($uri) ? get_class($uri) : gettype($uri)));
    }

    /*********************************
     * Query resolution methods
     *********************************/

    /**
     * Add the new query data to the existing URI query.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function appendQuery($uri, $query)
    {
        return $uri->withQuery((string) (new Query(self::filterUri($uri)->getQuery()))->append($query));
    }

    /**
     * Merge a new query with the existing URI query.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function mergeQuery($uri, $query)
    {
        return $uri->withQuery((string) (new Query(self::filterUri($uri)->getQuery()))->merge($query));
    }

    /**
     * Remove query data according to their key name.
     *
     * @param string... $keys
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removePairs($uri, string $key, string ... $keys)
    {
        return $uri->withQuery((string) (new Query(self::filterUri($uri)->getQuery()))->withoutPair($key, ...$keys));
    }

    /**
     * Remove query data according to their key name.
     *
     * @param string... $keys
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeParams($uri, string $key, string ... $keys)
    {
        return $uri->withQuery((string) (new Query(self::filterUri($uri)->getQuery()))->withoutParam($key, ...$keys));
    }

    /**
     * Sort the URI query by keys.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function sortQuery($uri)
    {
        return $uri->withQuery((string) (new Query(self::filterUri($uri)->getQuery()))->sort());
    }

    /*********************************
     * Host resolution methods
     *********************************/

    /**
     * Add the root label to the URI.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function addRootLabel($uri)
    {
        return $uri->withHost((string) (new Domain(self::filterUri($uri)->getHost()))->withRootLabel());
    }

    /**
     * Append a label or a host to the current URI host.
     *
     * @throws SyntaxError If the host can not be appended
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function appendLabel($uri, $label)
    {
        $host = new Host(self::filterUri($uri)->getHost());
        if ($host->isDomain()) {
            return $uri->withHost((string) (new Domain($host))->append($label));
        }

        if ($host->isIpv4()) {
            $label = ltrim((string) new Host($label), '.');

            return $uri->withHost((string) $host->withContent($host->getContent().'.'.$label));
        }

        throw new SyntaxError(sprintf('The URI host %s can not be appended', (string) $host));
    }

    /**
     * Convert the URI host part to its ascii value.
     *
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function hostToAscii($uri)
    {
        return $uri->withHost((string) (new Host(self::filterUri($uri)->getHost())));
    }

    /**
     * Convert the URI host part to its unicode value.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function hostToUnicode($uri)
    {
        return $uri->withHost((string) (new Host(self::filterUri($uri)->getHost()))->toUnicode());
    }

    /**
     * Prepend a label or a host to the current URI host.
     *
     * @throws SyntaxError If the host can not be prepended
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function prependLabel($uri, $label)
    {
        $host = new Host(self::filterUri($uri)->getHost());
        if ($host->isDomain()) {
            return $uri->withHost((string) (new Domain($host))->prepend($label));
        }

        if ($host->isIpv4()) {
            $label = rtrim((string) new Host($label), '.');

            return $uri->withHost((string) $host->withContent($label.'.'.$host->getContent()));
        }

        throw new SyntaxError(sprintf('The URI host %s can not be prepended', (string) $host));
    }

    /**
     * Remove host labels according to their offset.
     *
     * @param int... $keys
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeLabels($uri, int $key, int ... $keys)
    {
        return $uri->withHost((string) (new Domain(self::filterUri($uri)->getHost()))->withoutLabel($key, ...$keys));
    }

    /**
     * Remove the root label to the URI.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeRootLabel($uri)
    {
        return $uri->withHost((string) (new Domain(self::filterUri($uri)->getHost()))->withoutRootLabel());
    }

    /**
     * Remove the host zone identifier.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeZoneId($uri)
    {
        return $uri->withHost((string) (new Host(self::filterUri($uri)->getHost()))->withoutZoneIdentifier());
    }

    /**
     * Replace a label of the current URI host.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function replaceLabel($uri, int $offset, $host)
    {
        return $uri->withHost((string) (new Domain(self::filterUri($uri)->getHost()))->withLabel($offset, $host));
    }

    /*********************************
     * Path resolution methods
     *********************************/

    /**
     * Add a new basepath to the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function addBasepath($uri, $path)
    {
        self::filterUri($uri);
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
     * @return Psr7UriInterface|UriInterface
     */
    public static function addLeadingSlash($uri)
    {
        $uri = self::filterUri($uri);
        $path = $uri->getPath();
        if ('/' !== ($path[0] ?? '')) {
            $path = '/'.$path;
        }

        return self::normalizePath($uri, $path);
    }

    /**
     * Normalize a URI path.
     *
     * Make sure the path always has a leading slash if an authority is present
     * and the path is not the empty string.
     *
     * @param null|string|PathInterface $path
     *
     * @return Psr7UriInterface|UriInterface
     */
    private static function normalizePath($uri, $path = null)
    {
        if ($path instanceof PathInterface) {
            $path = $path->getContent();
        }

        $path = $path ?? $uri->getPath();
        $path = (string) $path;
        if (null !== $uri->getAuthority()
            && '' !== $uri->getAuthority()
            && '' !== $path
            && '/' != ($path[0] ?? '')
        ) {
            return $uri->withPath('/'.$path);
        }

        return $uri->withPath($path);
    }

    /**
     * Add a trailing slash to the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function addTrailingSlash($uri)
    {
        $uri = self::filterUri($uri);
        $path = $uri->getPath();
        if ('/' !== substr($path, -1)) {
            $path .= '/';
        }

        return self::normalizePath($uri, $path);
    }

    /**
     * Append an new segment or a new path to the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function appendSegment($uri, string $segment)
    {
        $uri = self::filterUri($uri);

        return self::normalizePath($uri, (new HierarchicalPath($uri->getPath()))->append($segment));
    }

    /**
     * Convert the Data URI path to its ascii form.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function datapathToAscii($uri)
    {
        return $uri->withPath((string) (new DataPath(self::filterUri($uri)->getPath()))->toAscii());
    }

    /**
     * Convert the Data URI path to its binary (base64encoded) form.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function datapathToBinary($uri)
    {
        return $uri->withPath((string) (new DataPath(self::filterUri($uri)->getPath()))->toBinary());
    }

    /**
     * Prepend an new segment or a new path to the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function prependSegment($uri, $segment)
    {
        $uri = self::filterUri($uri);

        return self::normalizePath($uri, (new HierarchicalPath($uri->getPath()))->prepend($segment));
    }

    /**
     * Remove a basepath from the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeBasepath($uri, $path)
    {
        $uri = self::normalizePath(self::filterUri($uri));
        $basepath = (new HierarchicalPath($path))->withLeadingSlash();
        if ('/' === (string) $basepath) {
            return $uri;
        }

        $currentpath = $uri->getPath();
        if (0 !== strpos($currentpath, (string) $basepath)) {
            return $uri;
        }

        return $uri->withPath(
            (string) (new HierarchicalPath($currentpath))->withoutSegment(...range(0, count($basepath) - 1))
        );
    }

    /**
     * Remove dot segments from the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeDotSegments($uri)
    {
        $uri = self::filterUri($uri);

        return self::normalizePath($uri, (new Path($uri->getPath()))->withoutDotSegments());
    }

    /**
     * Remove empty segments from the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeEmptySegments($uri)
    {
        $uri = self::filterUri($uri);

        return self::normalizePath($uri, (new HierarchicalPath($uri->getPath()))->withoutEmptySegments());
    }

    /**
     * Remove the leading slash from the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeLeadingSlash($uri)
    {
        $uri = self::filterUri($uri);
        $path = $uri->getPath();
        if ('' !== $path && '/' === $path[0]) {
            $path = substr($path, 1);
        }

        return self::normalizePath($uri, $path);
    }

    /**
     * Remove the trailing slash from the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeTrailingSlash($uri)
    {
        $uri = self::filterUri($uri);
        $path = $uri->getPath();
        if ('' !== $path && '/' === substr($path, -1)) {
            $path = substr($path, 0, -1);
        }

        return self::normalizePath($uri, $path);
    }

    /**
     * Remove path segments from the URI path according to their offsets.
     *
     * @param int... $keys
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeSegments($uri, int $key, int ... $keys)
    {
        $uri = self::filterUri($uri);

        return self::normalizePath($uri, (new HierarchicalPath($uri->getPath()))->withoutSegment($key, ...$keys));
    }

    /**
     * Replace the URI path basename.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function replaceBasename($uri, $path)
    {
        $uri = self::filterUri($uri);

        return self::normalizePath($uri, (new HierarchicalPath($uri->getPath()))->withBasename($path));
    }

    /**
     * Replace the data URI path parameters.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function replaceDataUriParameters($uri, string $parameters)
    {
        $uri = self::filterUri($uri);

        return self::normalizePath($uri, (new DataPath($uri->getPath()))->withParameters($parameters));
    }

    /**
     * Replace the URI path dirname.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function replaceDirname($uri, $path)
    {
        $uri = self::filterUri($uri);

        return self::normalizePath($uri, (new HierarchicalPath($uri->getPath()))->withDirname($path));
    }

    /**
     * Replace the URI path basename extension.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function replaceExtension($uri, $extension)
    {
        $uri = self::filterUri($uri);

        return self::normalizePath($uri, (new HierarchicalPath($uri->getPath()))->withExtension($extension));
    }

    /**
     * Replace a segment from the URI path according its offset.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function replaceSegment($uri, int $offset, $segment)
    {
        $uri = self::filterUri($uri);

        return self::normalizePath($uri, (new HierarchicalPath($uri->getPath()))->withSegment($offset, $segment));
    }
}

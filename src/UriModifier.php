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
use function is_object;
use function range;
use function rtrim;
use function sprintf;
use function strpos;
use function substr;

final class UriModifier
{
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
        $newQuery = Query::createFromUri($uri)->append($query);

        return self::filterUri($uri)->withQuery($newQuery->__toString());
    }

    /**
     * Merge a new query with the existing URI query.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function mergeQuery($uri, $query)
    {
        $newQuery = Query::createFromUri($uri)->merge($query);

        return self::filterUri($uri)->withQuery($newQuery->__toString());
    }

    /**
     * Remove query data according to their key name.
     *
     * @param string... $keys
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removePairs($uri, string $key, string ...$keys)
    {
        $newQuery = Query::createFromUri($uri)->withoutPair($key, ...$keys);

        return self::filterUri($uri)->withQuery($newQuery->__toString());
    }

    /**
     * Remove query data according to their key name.
     *
     * @param string... $keys
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeParams($uri, string $key, string ...$keys)
    {
        $newQuery = Query::createFromUri($uri)->withoutParam($key, ...$keys);

        return self::filterUri($uri)->withQuery($newQuery->__toString());
    }

    /**
     * Sort the URI query by keys.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function sortQuery($uri)
    {
        $newQuery = Query::createFromUri($uri)->sort();

        return self::filterUri($uri)->withQuery($newQuery->__toString());
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
        $newHost = Domain::createFromUri($uri)->withRootLabel();

        return self::filterUri($uri)->withHost($newHost->__toString());
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
        $host = Host::createFromUri($uri);
        if ($host->isDomain()) {
            return self::filterUri($uri)->withHost((new Domain($host))->append($label)->__toString());
        }

        if ($host->isIpv4()) {
            $label = ltrim((string) new Host($label), '.');
            $newHost = $host->withContent($host->getContent().'.'.$label);

            return self::filterUri($uri)->withHost($newHost->__toString());
        }

        throw new SyntaxError(sprintf('The URI host %s can not be appended', $host->__toString()));
    }

    /**
     * Convert the URI host part to its ascii value.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function hostToAscii($uri)
    {
        return self::filterUri($uri)->withHost(Host::createFromUri($uri)->__toString());
    }

    /**
     * Convert the URI host part to its unicode value.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function hostToUnicode($uri)
    {
        return self::filterUri($uri)->withHost((string) Host::createFromUri($uri)->toUnicode());
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
        $host = Host::createFromUri($uri);
        if ($host->isDomain()) {
            return self::filterUri($uri)->withHost((new Domain($host))->prepend($label)->__toString());
        }

        if ($host->isIpv4()) {
            $label = rtrim((string) new Host($label), '.');
            $newHost = $host->withContent($label.'.'.$host->getContent());

            return self::filterUri($uri)->withHost($newHost->__toString());
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
        $host = Domain::createFromUri($uri)->withoutLabel($key, ...$keys);

        return self::filterUri($uri)->withHost($host->__toString());
    }

    /**
     * Remove the root label to the URI.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeRootLabel($uri)
    {
        $host = Domain::createFromUri($uri)->withoutRootLabel();

        return self::filterUri($uri)->withHost($host->__toString());
    }

    /**
     * Remove the host zone identifier.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeZoneId($uri)
    {
        $host = Host::createFromUri($uri)->withoutZoneIdentifier();

        return self::filterUri($uri)->withHost($host->__toString());
    }

    /**
     * Replace a label of the current URI host.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function replaceLabel($uri, int $offset, $label)
    {
        $host = Domain::createFromUri($uri)->withLabel($offset, $label);

        return self::filterUri($uri)->withHost($host->__toString());
    }

    /*********************************
     * Path resolution methods
     *********************************/

    /**
     * Add a new basepath to the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function addBasePath($uri, $path)
    {
        $uri = self::filterUri($uri);
        $currentPath = $uri->getPath();
        if ('' !== $currentPath && '/' !== $currentPath[0]) {
            $currentPath = '/'.$currentPath;
        }

        $path = (new Path($path))->withLeadingSlash();
        if (0 === strpos($currentPath, (string) $path)) {
            return $uri->withPath($currentPath);
        }

        return $uri->withPath((new HierarchicalPath($path))->append($currentPath)->__toString());
    }

    /**
     * Add a leading slash to the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function addLeadingSlash($uri)
    {
        $path = self::filterUri($uri)->getPath();
        if ('/' !== ($path[0] ?? '')) {
            $path = '/'.$path;
        }

        return self::normalizePath($uri, $path);
    }

    /**
     * Add a trailing slash to the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function addTrailingSlash($uri)
    {
        $path = self::filterUri($uri)->getPath();
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
        return self::normalizePath($uri, HierarchicalPath::createFromUri($uri)->append($segment));
    }

    /**
     * Convert the Data URI path to its ascii form.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function dataPathToAscii($uri)
    {
        return self::filterUri($uri)->withPath(DataPath::createFromUri($uri)->toAscii()->__toString());
    }

    /**
     * Convert the Data URI path to its binary (base64encoded) form.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function dataPathToBinary($uri)
    {
        return self::filterUri($uri)->withPath(DataPath::createFromUri($uri)->toBinary()->__toString());
    }

    /**
     * Prepend an new segment or a new path to the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function prependSegment($uri, $segment)
    {
        return self::normalizePath($uri, HierarchicalPath::createFromUri($uri)->prepend($segment)->__toString());
    }

    /**
     * Remove a basepath from the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeBasePath($uri, $path)
    {
        $uri = self::normalizePath($uri);

        /** @var  HierarchicalPath $basePath */
        $basePath = (new HierarchicalPath($path))->withLeadingSlash();
        if ('/' === (string) $basePath) {
            return $uri;
        }

        $currentPath = HierarchicalPath::createFromUri($uri);
        if (0 !== strpos((string) $currentPath, (string) $basePath)) {
            return $uri;
        }

        return $uri->withPath(
            $currentPath->withoutSegment(...range(0, count($basePath) - 1))->__toString()
        );
    }

    /**
     * Remove dot segments from the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeDotSegments($uri)
    {
        return self::normalizePath($uri, Path::createFromUri($uri)->withoutDotSegments());
    }

    /**
     * Remove empty segments from the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeEmptySegments($uri)
    {
        return self::normalizePath($uri, HierarchicalPath::createFromUri($uri)->withoutEmptySegments());
    }

    /**
     * Remove the leading slash from the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeLeadingSlash($uri)
    {
        return self::normalizePath($uri, Path::createFromUri($uri)->withoutLeadingSlash());
    }

    /**
     * Remove the trailing slash from the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeTrailingSlash($uri)
    {
        return self::normalizePath($uri, Path::createFromUri($uri)->withoutTrailingSlash());
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
        return self::normalizePath($uri, HierarchicalPath::createFromUri($uri)->withoutSegment($key, ...$keys));
    }

    /**
     * Replace the URI path basename.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function replaceBasename($uri, $path)
    {
        return self::normalizePath($uri, HierarchicalPath::createFromUri($uri)->withBasename($path));
    }

    /**
     * Replace the data URI path parameters.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function replaceDataUriParameters($uri, string $parameters)
    {
        return self::normalizePath($uri, DataPath::createFromUri($uri)->withParameters($parameters));
    }

    /**
     * Replace the URI path dirname.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function replaceDirname($uri, $path)
    {
        return self::normalizePath($uri, HierarchicalPath::createFromUri($uri)->withDirname($path));
    }

    /**
     * Replace the URI path basename extension.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function replaceExtension($uri, $extension)
    {
        return self::normalizePath($uri, HierarchicalPath::createFromUri($uri)->withExtension($extension));
    }

    /**
     * Replace a segment from the URI path according its offset.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function replaceSegment($uri, int $offset, $segment)
    {
        return self::normalizePath($uri, HierarchicalPath::createFromUri($uri)->withSegment($offset, $segment));
    }

    /**
     * Filter the URI object.
     *
     * To be valid an URI MUST implement at least one of the following interface:
     *     - League\Uri\UriInterface
     *     - Psr\Http\Message\UriInterface
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
        $uri = self::filterUri($uri);

        if ($path instanceof PathInterface) {
            $path = $path->getContent();
        }
        $path = $path ?? $uri->getPath();

        if (null !== $uri->getAuthority()
            && '' !== $uri->getAuthority()
            && '' !== $path
            && '/' != ($path[0] ?? '')
        ) {
            return $uri->withPath('/'.$path);
        }

        return $uri->withPath($path);
    }
}

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

use League\Uri\Components\DataPath;
use League\Uri\Components\Domain;
use League\Uri\Components\HierarchicalPath;
use League\Uri\Components\Host;
use League\Uri\Components\Path;
use League\Uri\Components\Query;
use League\Uri\Contracts\PathInterface;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use function count;
use function range;
use function rtrim;
use function sprintf;
use function strpos;

final class UriModifier
{
    /*********************************
     * Query resolution methods
     *********************************/

    /**
     * Add the new query data to the existing URI query.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function appendQuery($uri, $query)
    {
        return $uri->withQuery(Query::createFromUri($uri)->append($query)->__toString());
    }

    /**
     * Merge a new query with the existing URI query.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function mergeQuery($uri, $query)
    {
        return $uri->withQuery(Query::createFromUri($uri)->merge($query)->__toString());
    }

    /**
     * Remove query data according to their key name.
     *
     * @param Psr7UriInterface|UriInterface $uri
     * @param string...                     $keys
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removePairs($uri, string $key, string ...$keys)
    {
        return $uri->withQuery(Query::createFromUri($uri)->withoutPair($key, ...$keys)->__toString());
    }

    /**
     * Remove query data according to their key name.
     *
     * @param Psr7UriInterface|UriInterface $uri
     * @param string...                     $keys
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeParams($uri, string $key, string ...$keys)
    {
        return $uri->withQuery(Query::createFromUri($uri)->withoutParam($key, ...$keys)->__toString());
    }

    /**
     * Sort the URI query by keys.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function sortQuery($uri)
    {
        return $uri->withQuery(Query::createFromUri($uri)->sort()->__toString());
    }

    /*********************************
     * Host resolution methods
     *********************************/

    /**
     * Add the root label to the URI.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function addRootLabel($uri)
    {
        return $uri->withHost(Domain::createFromUri($uri)->withRootLabel()->__toString());
    }

    /**
     * Append a label or a host to the current URI host.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @throws SyntaxError If the host can not be appended
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function appendLabel($uri, $label)
    {
        $host = Host::createFromUri($uri);
        if ($host->isDomain()) {
            return $uri->withHost((new Domain($host))->append($label)->__toString());
        }

        if ($host->isIpv4()) {
            $label = ltrim((string) new Host($label), '.');

            return $uri->withHost((new Host($host->getContent().'.'.$label))->__toString());
        }

        throw new SyntaxError(sprintf('The URI host %s can not be appended', $host->__toString()));
    }

    /**
     * Convert the URI host part to its ascii value.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function hostToAscii($uri)
    {
        return $uri->withHost(Host::createFromUri($uri)->__toString());
    }

    /**
     * Convert the URI host part to its unicode value.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function hostToUnicode($uri)
    {
        return $uri->withHost((string) Host::createFromUri($uri)->toUnicode());
    }

    /**
     * Prepend a label or a host to the current URI host.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @throws SyntaxError If the host can not be prepended
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function prependLabel($uri, $label)
    {
        $host = Host::createFromUri($uri);
        if ($host->isDomain()) {
            return $uri->withHost((new Domain($host))->prepend($label)->__toString());
        }

        if ($host->isIpv4()) {
            $label = rtrim((string) new Host($label), '.');
            $newHost = $host->withContent($label.'.'.$host->getContent());

            return $uri->withHost($newHost->__toString());
        }

        throw new SyntaxError(sprintf('The URI host %s can not be prepended', (string) $host));
    }

    /**
     * Remove host labels according to their offset.
     *
     * @param Psr7UriInterface|UriInterface $uri
     * @param int...                        $keys
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeLabels($uri, int $key, int ...$keys)
    {
        return $uri->withHost(Domain::createFromUri($uri)->withoutLabel($key, ...$keys)->__toString());
    }

    /**
     * Remove the root label to the URI.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeRootLabel($uri)
    {
        return $uri->withHost(Domain::createFromUri($uri)->withoutRootLabel()->__toString());
    }

    /**
     * Remove the host zone identifier.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeZoneId($uri)
    {
        return $uri->withHost(Host::createFromUri($uri)->withoutZoneIdentifier()->__toString());
    }

    /**
     * Replace a label of the current URI host.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function replaceLabel($uri, int $offset, $label)
    {
        return $uri->withHost(Domain::createFromUri($uri)->withLabel($offset, $label)->__toString());
    }

    /*********************************
     * Path resolution methods
     *********************************/

    /**
     * Add a new basepath to the URI path.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function addBasePath($uri, $path)
    {
        /** @var HierarchicalPath $path */
        $path = (new HierarchicalPath($path))->withLeadingSlash();
        $currentPath = Path::createFromUri($uri)->withLeadingSlash()->__toString();

        if (0 === strpos($currentPath, (string) $path)) {
            return $uri->withPath($currentPath);
        }

        return $uri->withPath($path->append($currentPath)->__toString());
    }

    /**
     * Add a leading slash to the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function addLeadingSlash($uri)
    {
        return self::normalizePath($uri, Path::createFromUri($uri)->withLeadingSlash());
    }

    /**
     * Add a trailing slash to the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function addTrailingSlash($uri)
    {
        return self::normalizePath($uri, Path::createFromUri($uri)->withTrailingSlash());
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
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function dataPathToAscii($uri)
    {
        return $uri->withPath(DataPath::createFromUri($uri)->toAscii()->__toString());
    }

    /**
     * Convert the Data URI path to its binary (base64encoded) form.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function dataPathToBinary($uri)
    {
        return $uri->withPath(DataPath::createFromUri($uri)->toBinary()->__toString());
    }

    /**
     * Prepend an new segment or a new path to the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function prependSegment($uri, $segment)
    {
        return self::normalizePath($uri, HierarchicalPath::createFromUri($uri)->prepend($segment));
    }

    /**
     * Remove a basepath from the URI path.
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeBasePath($uri, $path)
    {
        $currentPath = HierarchicalPath::createFromUri($uri);
        $uri = self::normalizePath($uri);

        /** @var  HierarchicalPath $basePath */
        $basePath = (new HierarchicalPath($path))->withLeadingSlash();
        if ('/' === (string) $basePath) {
            return $uri;
        }

        if (0 !== strpos((string) $currentPath, (string) $basePath)) {
            return $uri;
        }

        return $uri->withPath($currentPath->withoutSegment(...range(0, count($basePath) - 1))->__toString());
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
    public static function removeSegments($uri, int $key, int ...$keys)
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
     * Normalize a URI path.
     *
     * Make sure the path always has a leading slash if an authority is present
     * and the path is not the empty string.
     *
     * @param Psr7UriInterface|UriInterface $uri
     * @param ?PathInterface                $path
     *
     * @return Psr7UriInterface|UriInterface
     */
    private static function normalizePath($uri, ?PathInterface $path = null)
    {
        if (!$path instanceof PathInterface) {
            $path = $uri->getPath();
        }

        $path = (string) $path;
        if (!in_array($uri->getAuthority(), [null, ''], true) && '' !== $path && '/' != ($path[0] ?? '')) {
            return $uri->withPath('/'.$path);
        }

        return $uri->withPath($path);
    }
}

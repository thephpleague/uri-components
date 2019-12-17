<?php

/**
 * League.Uri (http://uri.thephpleague.com/components)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.2
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
use function ltrim;
use function rtrim;
use function sprintf;

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
        $query = Query::createFromUri($uri)->append($query)->getContent();

        return $uri->withQuery(self::normalizeComponent($query, $uri));
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
        $query = Query::createFromUri($uri)->merge($query)->getContent();

        return $uri->withQuery(self::normalizeComponent($query, $uri));
    }

    /**
     * Remove query data according to their key name.
     *
     * @param Psr7UriInterface|UriInterface $uri
     * @param string...                     $keys
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removePairs($uri, string ...$keys)
    {
        $query = Query::createFromUri($uri)->withoutPair(...$keys)->getContent();

        return $uri->withQuery(self::normalizeComponent($query, $uri));
    }

    /**
     * Remove empty pairs from the URL query component.
     *
     * A pair is considered empty if it's name is the empty string
     * and its value is either the empty string or the null value
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeEmptyPairs($uri)
    {
        $query = Query::createFromUri($uri)->withoutEmptyPairs()->getContent();

        return $uri->withQuery(self::normalizeComponent($query, $uri));
    }

    /**
     * Remove query data according to their key name.
     *
     * @param Psr7UriInterface|UriInterface $uri
     * @param string...                     $keys
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeParams($uri, string ...$keys)
    {
        $query = Query::createFromUri($uri)->withoutParam(...$keys)->getContent();

        return $uri->withQuery(self::normalizeComponent($query, $uri));
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
        $query = Query::createFromUri($uri)->sort()->getContent();

        return $uri->withQuery(self::normalizeComponent($query, $uri));
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
        $component = Domain::createFromUri($uri)->withRootLabel()->getContent();

        return $uri->withHost(self::normalizeComponent($component, $uri));
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
        $label = new Host($label);
        if (null === $label->getContent()) {
            return $uri;
        }

        if ($host->isDomain()) {
            $component = (new Domain($host))->append($label)->getContent();

            return $uri->withHost(self::normalizeComponent($component, $uri));
        }

        if ($host->isIpv4()) {
            return $uri->withHost($host->getContent().'.'.ltrim($label->getContent(), '.'));
        }

        throw new SyntaxError(sprintf('The URI host %s can not be appended.', $host->__toString()));
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
        $host = Host::createFromUri($uri)->getContent();

        return $uri->withHost(self::normalizeComponent($host, $uri));
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
        $host = Host::createFromUri($uri)->toUnicode();

        return $uri->withHost(self::normalizeComponent($host, $uri));
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
        $label = new Host($label);
        if (null === $label->getContent()) {
            return $uri;
        }

        if ($host->isDomain()) {
            $component = (new Domain($host))->prepend($label)->getContent();

            return $uri->withHost($component);
        }

        if ($host->isIpv4()) {
            return $uri->withHost(rtrim($label->getContent(), '.').'.'.$host->getContent());
        }

        throw new SyntaxError(sprintf('The URI host %s can not be prepended.', (string) $host));
    }

    /**
     * Remove host labels according to their offset.
     *
     * @param Psr7UriInterface|UriInterface $uri
     * @param int...                        $keys
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeLabels($uri, int ...$keys)
    {
        $host = Domain::createFromUri($uri)->withoutLabel(...$keys)->getContent();

        return $uri->withHost(self::normalizeComponent($host, $uri));
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
        $host = Domain::createFromUri($uri)->withoutRootLabel()->getContent();

        return $uri->withHost(self::normalizeComponent($host, $uri));
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
        $host = Host::createFromUri($uri)->withoutZoneIdentifier()->getContent();

        return $uri->withHost(self::normalizeComponent($host, $uri));
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
        $host = Domain::createFromUri($uri)->withLabel($offset, $label)->getContent();

        return $uri->withHost(self::normalizeComponent($host, $uri));
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

        /** @var HierarchicalPath $currentPath */
        $currentPath = HierarchicalPath::createFromUri($uri)->withLeadingSlash();

        foreach ($path as $offset => $segment) {
            if ($currentPath->get($offset) !== $segment) {
                return $uri->withPath($path->append($currentPath)->__toString());
            }
        }

        return $uri->withPath($currentPath->__toString());
    }

    /**
     * Add a leading slash to the URI path.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function addLeadingSlash($uri)
    {
        return $uri->withPath(Path::createFromUri($uri)->withLeadingSlash()->__toString());
    }

    /**
     * Add a trailing slash to the URI path.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function addTrailingSlash($uri)
    {
        return $uri->withPath(Path::createFromUri($uri)->withTrailingSlash()->__toString());
    }

    /**
     * Append an new segment or a new path to the URI path.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function appendSegment($uri, $segment)
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
     * @param Psr7UriInterface|UriInterface $uri
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
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeBasePath($uri, $path)
    {
        /** @var HierarchicalPath $basePath */
        $basePath = (new HierarchicalPath($path))->withLeadingSlash();
        $currentPath = HierarchicalPath::createFromUri($uri);
        if ('/' === (string) $basePath) {
            return $uri;
        }

        foreach ($basePath as $offset => $segment) {
            if ($segment !== $currentPath->get($offset)) {
                return $uri;
            }
        }

        if (!$currentPath->isAbsolute()) {
            return $uri;
        }

        return $uri->withPath($currentPath->withoutSegment(...$basePath->keys())->__toString());
    }

    /**
     * Remove dot segments from the URI path.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeDotSegments($uri)
    {
        return $uri->withPath(Path::createFromUri($uri)->withoutDotSegments()->__toString());
    }

    /**
     * Remove empty segments from the URI path.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeEmptySegments($uri)
    {
        return $uri->withPath(HierarchicalPath::createFromUri($uri)->withoutEmptySegments()->__toString());
    }

    /**
     * Remove the leading slash from the URI path.
     *
     * @param Psr7UriInterface|UriInterface $uri
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
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeTrailingSlash($uri)
    {
        return $uri->withPath(Path::createFromUri($uri)->withoutTrailingSlash()->__toString());
    }

    /**
     * Remove path segments from the URI path according to their offsets.
     *
     * @param Psr7UriInterface|UriInterface $uri
     * @param int...                        $keys
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function removeSegments($uri, int ...$keys)
    {
        $path = HierarchicalPath::createFromUri($uri)->withoutSegment(...$keys)->__toString();

        return $uri->withPath($path);
    }

    /**
     * Replace the URI path basename.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function replaceBasename($uri, $basename)
    {
        $path = HierarchicalPath::createFromUri($uri)->withBasename($basename)->__toString();

        return $uri->withPath($path);
    }

    /**
     * Replace the data URI path parameters.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function replaceDataUriParameters($uri, $parameters)
    {
        $path = DataPath::createFromUri($uri)->withParameters($parameters)->__toString();

        return $uri->withPath($path);
    }

    /**
     * Replace the URI path dirname.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function replaceDirname($uri, $dirname)
    {
        $path = HierarchicalPath::createFromUri($uri)->withDirname($dirname)->__toString();

        return $uri->withPath($path);
    }

    /**
     * Replace the URI path basename extension.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function replaceExtension($uri, $extension)
    {
        $path = HierarchicalPath::createFromUri($uri)->withExtension($extension)->__toString();

        return $uri->withPath($path);
    }

    /**
     * Replace a segment from the URI path according its offset.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    public static function replaceSegment($uri, int $offset, $segment)
    {
        $path = HierarchicalPath::createFromUri($uri)->withSegment($offset, $segment)->__toString();

        return $uri->withPath($path);
    }

    /**
     * Normalize a URI path.
     *
     * Make sure the path always has a leading slash if an authority is present
     * and the path is not the empty string.
     *
     * @param Psr7UriInterface|UriInterface $uri
     *
     * @return Psr7UriInterface|UriInterface
     */
    private static function normalizePath($uri, PathInterface $path)
    {
        $authority = $uri->getAuthority();
        if (null === $authority || '' === $authority) {
            return $uri->withPath($path->__toString());
        }

        if ('' === $path->getContent() || $path->isAbsolute()) {
            return $uri->withPath($path->__toString());
        }

        return $uri->withPath($path->withLeadingSlash()->__toString());
    }

    /**
     * Normalize the URI component value depending on the subject interface.
     *
     * null value MUST be converted to the emptu string if a Psr7 UriInterface is being manipulated.
     *
     * @param Psr7UriInterface|UriInterface $uri
     * @param ?string                       $component
     */
    private static function normalizeComponent(?string $component, $uri): ?string
    {
        if ($uri instanceof Psr7UriInterface) {
            return (string) $component;
        }

        return $component;
    }
}

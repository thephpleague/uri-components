<?php

/**
 * League.Uri (https://uri.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
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
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
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
     */
    public static function appendQuery(
        Psr7UriInterface|UriInterface|Stringable|string $uri,
        UriComponentInterface|Stringable|int|string|bool|null $query
    ): Psr7UriInterface|UriInterface {
        $uri = self::filterUri($uri);

        return $uri->withQuery(
            self::normalizeComponent(Query::createFromUri($uri)->append($query)->value(), $uri)
        );
    }

    /**
     * Merge a new query with the existing URI query.
     */
    public static function mergeQuery(
        Psr7UriInterface|UriInterface|Stringable|string $uri,
        UriComponentInterface|Stringable|int|string|bool|null $query
    ): Psr7UriInterface|UriInterface {
        $uri = self::filterUri($uri);

        return $uri->withQuery(
            self::normalizeComponent(Query::createFromUri($uri)->merge($query)->value(), $uri)
        );
    }

    /**
     * Remove query data according to their key name.
     */
    public static function removePairs(Psr7UriInterface|UriInterface|Stringable|string $uri, string ...$keys): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withQuery(
            self::normalizeComponent(Query::createFromUri($uri)->withoutPair(...$keys)->value(), $uri)
        );
    }

    /**
     * Remove empty pairs from the URL query component.
     *
     * A pair is considered empty if it's name is the empty string
     * and its value is either the empty string or the null value
     */
    public static function removeEmptyPairs(Psr7UriInterface|UriInterface|Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withQuery(
            self::normalizeComponent(Query::createFromUri($uri)->withoutEmptyPairs()->value(), $uri)
        );
    }

    /**
     * Remove query data according to their key name.
     */
    public static function removeParams(Psr7UriInterface|UriInterface|Stringable|string $uri, string ...$keys): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withQuery(
            self::normalizeComponent(Query::createFromUri($uri)->withoutParam(...$keys)->value(), $uri)
        );
    }

    /**
     * Sort the URI query by keys.
     */
    public static function sortQuery(Psr7UriInterface|UriInterface|Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withQuery(self::normalizeComponent(Query::createFromUri($uri)->sort()->value(), $uri));
    }

    /*********************************
     * Host resolution methods
     *********************************/

    /**
     * Add the root label to the URI.
     */
    public static function addRootLabel(Psr7UriInterface|UriInterface|Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withHost(self::normalizeComponent(Domain::createFromUri($uri)->withRootLabel()->value(), $uri));
    }

    /**
     * Append a label or a host to the current URI host.
     *
     * @throws SyntaxError If the host can not be appended
     */
    public static function appendLabel(Psr7UriInterface|UriInterface|Stringable|string $uri, Stringable|string|null $label): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);
        $host = Host::createFromUri($uri);
        $label = null === $label ? Host::createFromNull() : Host::createFromString($label);
        if (null === $label->value()) {
            return $uri;
        }

        if ($host->isDomain()) {
            $component = Domain::createFromHost($host)->append($label)->value();

            return $uri->withHost(self::normalizeComponent($component, $uri));
        }

        if ($host->isIpv4()) {
            return $uri->withHost($host->value().'.'.ltrim($label->value(), '.'));
        }

        throw new SyntaxError(sprintf('The URI host %s can not be appended.', $host->__toString()));
    }

    /**
     * Convert the URI host part to its ascii value.
     */
    public static function hostToAscii(Psr7UriInterface|UriInterface|Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);
        $host = Host::createFromUri($uri)->value();

        return $uri->withHost(self::normalizeComponent($host, $uri));
    }

    /**
     * Convert the URI host part to its unicode value.
     */
    public static function hostToUnicode(Psr7UriInterface|UriInterface|Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);
        $host = Host::createFromUri($uri)->toUnicode();

        return $uri->withHost(self::normalizeComponent($host, $uri));
    }

    /**
     * Prepend a label or a host to the current URI host.
     *
     * @throws SyntaxError If the host can not be prepended
     */
    public static function prependLabel(Psr7UriInterface|UriInterface|Stringable|string $uri, Stringable|string|null $label): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);
        $host = Host::createFromUri($uri);
        $label = null === $label ? Host::createFromNull() : Host::createFromString($label);
        if (null === $label->value()) {
            return $uri;
        }

        if ($host->isDomain()) {
            $component = Domain::createFromHost($host)->prepend($label)->value();

            return $uri->withHost($component);
        }

        if ($host->isIpv4()) {
            return $uri->withHost(rtrim($label->value(), '.').'.'.$host->value());
        }

        throw new SyntaxError(sprintf('The URI host %s can not be prepended.', (string) $host));
    }

    /**
     * Remove host labels according to their offset.
     */
    public static function removeLabels(Psr7UriInterface|UriInterface|Stringable|string $uri, int ...$keys): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withHost(
            self::normalizeComponent(Domain::createFromUri($uri)->withoutLabel(...$keys)->value(), $uri)
        );
    }

    /**
     * Remove the root label to the URI.
     */
    public static function removeRootLabel(Psr7UriInterface|UriInterface|Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withHost(
            self::normalizeComponent(Domain::createFromUri($uri)->withoutRootLabel()->value(), $uri)
        );
    }

    /**
     * Remove the host zone identifier.
     */
    public static function removeZoneId(Psr7UriInterface|UriInterface|Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withHost(
            self::normalizeComponent(Host::createFromUri($uri)->withoutZoneIdentifier()->value(), $uri)
        );
    }

    /**
     * Replace a label of the current URI host.
     */
    public static function replaceLabel(
        Psr7UriInterface|UriInterface|Stringable|string $uri,
        int $offset,
        UriComponentInterface|Stringable|int|string|bool|null $label
    ): Psr7UriInterface|UriInterface {
        $uri = self::filterUri($uri);
        $host = Domain::createFromUri($uri)->withLabel($offset, $label)->value();

        return $uri->withHost(self::normalizeComponent($host, $uri));
    }

    /*********************************
     * Path resolution methods
     *********************************/

    /**
     * Add a new basepath to the URI path.
     */
    public static function addBasePath(Psr7UriInterface|UriInterface|Stringable|string $uri, Stringable|string $path): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);
        /** @var HierarchicalPath $path */
        $path = HierarchicalPath::createFromPath(Path::createFromString($path))->withLeadingSlash();
        /** @var HierarchicalPath $currentPath */
        $currentPath = HierarchicalPath::createFromUri($uri)->withLeadingSlash();

        /**
         * @var int    $offset
         * @var string $segment
         */
        foreach ($path as $offset => $segment) {
            if ($currentPath->get($offset) !== $segment) {
                return $uri->withPath($path->append($currentPath)->__toString());
            }
        }

        return $uri->withPath($currentPath->__toString());
    }

    /**
     * Add a leading slash to the URI path.
     */
    public static function addLeadingSlash(Psr7UriInterface|UriInterface|Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withPath(Path::createFromUri($uri)->withLeadingSlash()->__toString());
    }

    /**
     * Add a trailing slash to the URI path.
     */
    public static function addTrailingSlash(Psr7UriInterface|UriInterface|Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withPath(Path::createFromUri($uri)->withTrailingSlash()->__toString());
    }

    /**
     * Append a new segment or a new path to the URI path.
     */
    public static function appendSegment(
        Psr7UriInterface|UriInterface|Stringable|string $uri,
        UriComponentInterface|Stringable|int|string|bool $segment
    ): Psr7UriInterface|UriInterface {
        $uri = self::filterUri($uri);

        return self::normalizePath($uri, HierarchicalPath::createFromUri($uri)->append($segment));
    }

    /**
     * Convert the Data URI path to its ascii form.
     */
    public static function dataPathToAscii(Psr7UriInterface|UriInterface|Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withPath(DataPath::createFromUri($uri)->toAscii()->__toString());
    }

    /**
     * Convert the Data URI path to its binary (base64encoded) form.
     */
    public static function dataPathToBinary(Psr7UriInterface|UriInterface|Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withPath(DataPath::createFromUri($uri)->toBinary()->__toString());
    }

    /**
     * Prepend an new segment or a new path to the URI path.
     */
    public static function prependSegment(
        Psr7UriInterface|UriInterface|Stringable|string $uri,
        UriComponentInterface|Stringable|int|string|bool $segment
    ): Psr7UriInterface|UriInterface {
        $uri = self::filterUri($uri);

        return self::normalizePath($uri, HierarchicalPath::createFromUri($uri)->prepend($segment));
    }

    /**
     * Remove a basepath from the URI path.
     */
    public static function removeBasePath(
        Psr7UriInterface|UriInterface|Stringable|string $uri,
        UriComponentInterface|Stringable|int|string|bool $path
    ): Psr7UriInterface|UriInterface {
        $uri = self::filterUri($uri);
        /** @var HierarchicalPath $basePath */
        $basePath = HierarchicalPath::createFromPath(new Path($path))->withLeadingSlash();
        $currentPath = HierarchicalPath::createFromUri($uri);
        if ('/' === (string) $basePath) {
            return $uri;
        }

        /**
         * @var int    $offset
         * @var string $segment
         */
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
     */
    public static function removeDotSegments(Psr7UriInterface|UriInterface|Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withPath(Path::createFromUri($uri)->withoutDotSegments()->__toString());
    }

    /**
     * Remove empty segments from the URI path.
     */
    public static function removeEmptySegments(Psr7UriInterface|UriInterface|Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withPath(HierarchicalPath::createFromUri($uri)->withoutEmptySegments()->__toString());
    }

    /**
     * Remove the leading slash from the URI path.
     */
    public static function removeLeadingSlash(Psr7UriInterface|UriInterface|Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return self::normalizePath($uri, Path::createFromUri($uri)->withoutLeadingSlash());
    }

    /**
     * Remove the trailing slash from the URI path.
     */
    public static function removeTrailingSlash(Psr7UriInterface|UriInterface|Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withPath(Path::createFromUri($uri)->withoutTrailingSlash()->__toString());
    }

    /**
     * Remove path segments from the URI path according to their offsets.
     */
    public static function removeSegments(Psr7UriInterface|UriInterface|Stringable|string $uri, int ...$keys): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withPath(HierarchicalPath::createFromUri($uri)->withoutSegment(...$keys)->__toString());
    }

    /**
     * Replace the URI path basename.
     */
    public static function replaceBasename(
        Psr7UriInterface|UriInterface|Stringable|string $uri,
        UriComponentInterface|Stringable|bool|int|null|string $basename
    ): Psr7UriInterface|UriInterface {
        $uri = self::filterUri($uri);

        return self::normalizePath($uri, HierarchicalPath::createFromUri($uri)->withBasename($basename));
    }

    /**
     * Replace the data URI path parameters.
     */
    public static function replaceDataUriParameters(
        Psr7UriInterface|UriInterface|Stringable|string $uri,
        UriComponentInterface|Stringable|bool|int|string $parameters
    ): Psr7UriInterface|UriInterface {
        $uri = self::filterUri($uri);

        return $uri->withPath(DataPath::createFromUri($uri)->withParameters($parameters)->__toString());
    }

    /**
     * Replace the URI path dirname.
     */
    public static function replaceDirname(
        Psr7UriInterface|UriInterface|Stringable|string $uri,
        UriComponentInterface|Stringable|bool|int|string $dirname
    ): Psr7UriInterface|UriInterface {
        $uri = self::filterUri($uri);

        return self::normalizePath($uri, HierarchicalPath::createFromUri($uri)->withDirname($dirname));
    }

    /**
     * Replace the URI path basename extension.
     */
    public static function replaceExtension(
        Psr7UriInterface|UriInterface|Stringable|string $uri,
        UriComponentInterface|Stringable|bool|int|null|string $extension
    ): Psr7UriInterface|UriInterface {
        $uri = self::filterUri($uri);

        return $uri->withPath(HierarchicalPath::createFromUri($uri)->withExtension($extension)->__toString());
    }

    /**
     * Replace a segment from the URI path according its offset.
     */
    public static function replaceSegment(
        Psr7UriInterface|UriInterface|Stringable|string $uri,
        int $offset,
        UriComponentInterface|Stringable|string $segment
    ): Psr7UriInterface|UriInterface {
        $uri = self::filterUri($uri);

        return $uri->withPath(HierarchicalPath::createFromUri($uri)->withSegment($offset, $segment)->__toString());
    }

    /**
     * Input URI normalization to allow Stringable and string URI.
     */
    private static function filterUri(Psr7UriInterface|UriInterface|Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        return match (true) {
            $uri instanceof Psr7UriInterface, $uri instanceof UriInterface => $uri,
            default => Uri::createFromString($uri),
        };
    }

    /**
     * Normalize a URI path.
     *
     * Make sure the path always has a leading slash if an authority is present
     * and the path is not the empty string.
     */
    private static function normalizePath(Psr7UriInterface|UriInterface $uri, PathInterface $path): Psr7UriInterface|UriInterface
    {
        $pathString = $path->__toString();
        if ('' === (string) $uri->getAuthority() || '' === $pathString || '/' === $pathString[0]) {
            return $uri->withPath($pathString);
        }

        return $uri->withPath('/'.$pathString);
    }

    /**
     * Normalize the URI component value depending on the subject interface.
     *
     * null value MUST be converted to the emptu string if a Psr7 UriInterface is being manipulated.
     */
    private static function normalizeComponent(?string $component, Psr7UriInterface|UriInterface $uri): ?string
    {
        if ($uri instanceof Psr7UriInterface) {
            return (string) $component;
        }

        return $component;
    }
}

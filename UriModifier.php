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
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use function ltrim;
use function rtrim;

final class UriModifier
{
    /*********************************
     * Query resolution methods
     *********************************/

    /**
     * Add the new query data to the existing URI query.
     */
    public static function appendQuery(
        Stringable|string $uri,
        Stringable|string|null $query
    ): Psr7UriInterface|UriInterface {
        $uri = self::filterUri($uri);

        return $uri->withQuery(
            self::normalizeComponent(Query::fromUri($uri)->append($query)->value(), $uri)
        );
    }

    /**
     * Merge a new query with the existing URI query.
     */
    public static function mergeQuery(
        Stringable|string $uri,
        Stringable|string|null $query
    ): Psr7UriInterface|UriInterface {
        $uri = self::filterUri($uri);

        return $uri->withQuery(
            self::normalizeComponent(Query::fromUri($uri)->merge($query)->value(), $uri)
        );
    }

    /**
     * Remove query data according to their key name.
     */
    public static function removePairs(Stringable|string $uri, string ...$keys): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withQuery(
            self::normalizeComponent(Query::fromUri($uri)->withoutPair(...$keys)->value(), $uri)
        );
    }

    /**
     * Remove empty pairs from the URL query component.
     *
     * A pair is considered empty if it's name is the empty string
     * and its value is either the empty string or the null value
     */
    public static function removeEmptyPairs(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withQuery(
            self::normalizeComponent(Query::fromUri($uri)->withoutEmptyPairs()->value(), $uri)
        );
    }

    /**
     * Remove query data according to their key name.
     */
    public static function removeParams(Stringable|string $uri, string ...$keys): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withQuery(
            self::normalizeComponent(Query::fromUri($uri)->withoutParameters(...$keys)->value(), $uri)
        );
    }

    /**
     * Sort the URI query by keys.
     */
    public static function sortQuery(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withQuery(self::normalizeComponent(Query::fromUri($uri)->sort()->value(), $uri));
    }

    /*********************************
     * Host resolution methods
     *********************************/

    /**
     * Add the root label to the URI.
     */
    public static function addRootLabel(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withHost(self::normalizeComponent(Domain::fromUri($uri)->withRootLabel()->value(), $uri));
    }

    /**
     * Append a label or a host to the current URI host.
     *
     * @throws SyntaxError If the host can not be appended
     */
    public static function appendLabel(Stringable|string $uri, Stringable|string|null $label): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);
        $host = Host::fromUri($uri);
        $label = Host::new($label);

        return match (true) {
            null === $label->value() => $uri,
            $host->isDomain() => $uri->withHost(self::normalizeComponent(Domain::new($host)->append($label)->value(), $uri)),
            $host->isIpv4() => $uri->withHost($host->value().'.'.ltrim($label->value(), '.')),
            default => throw new SyntaxError('The URI host '.$host->toString().' can not be appended.'),
        };
    }

    /**
     * Convert the URI host part to its ascii value.
     */
    public static function hostToAscii(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withHost(self::normalizeComponent(Host::fromUri($uri)->value(), $uri));
    }

    /**
     * Convert the URI host part to its unicode value.
     */
    public static function hostToUnicode(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withHost(self::normalizeComponent(Host::fromUri($uri)->toUnicode(), $uri));
    }

    /**
     * Prepend a label or a host to the current URI host.
     *
     * @throws SyntaxError If the host can not be prepended
     */
    public static function prependLabel(Stringable|string $uri, Stringable|string|null $label): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);
        $host = Host::fromUri($uri);
        $label = Host::new($label);

        return match (true) {
            null === $label->value() => $uri,
            $host->isDomain() => $uri->withHost(self::normalizeComponent(Domain::new($host)->prepend($label)->value(), $uri)),
            $host->isIpv4() => $uri->withHost(rtrim($label->value(), '.').'.'.$host->value()),
            default => throw new SyntaxError('The URI host '.$host->toString().' can not be prepended.'),
        };
    }

    /**
     * Remove host labels according to their offset.
     */
    public static function removeLabels(Stringable|string $uri, int ...$keys): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withHost(
            self::normalizeComponent(Domain::fromUri($uri)->withoutLabel(...$keys)->value(), $uri)
        );
    }

    /**
     * Remove the root label to the URI.
     */
    public static function removeRootLabel(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withHost(
            self::normalizeComponent(Domain::fromUri($uri)->withoutRootLabel()->value(), $uri)
        );
    }

    /**
     * Remove the host zone identifier.
     */
    public static function removeZoneId(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withHost(
            self::normalizeComponent(Host::fromUri($uri)->withoutZoneIdentifier()->value(), $uri)
        );
    }

    /**
     * Replace a label of the current URI host.
     */
    public static function replaceLabel(
        Stringable|string $uri,
        int $offset,
        Stringable|string|null $label
    ): Psr7UriInterface|UriInterface {
        $uri = self::filterUri($uri);

        return $uri->withHost(
            self::normalizeComponent(Domain::fromUri($uri)->withLabel($offset, $label)->value(), $uri)
        );
    }

    /*********************************
     * Path resolution methods
     *********************************/

    /**
     * Add a new basepath to the URI path.
     */
    public static function addBasePath(Stringable|string $uri, Stringable|string $path): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);
        /** @var HierarchicalPath $path */
        $path = HierarchicalPath::new($path)->withLeadingSlash();
        /** @var HierarchicalPath $currentPath */
        $currentPath = HierarchicalPath::fromUri($uri)->withLeadingSlash();

        return match (true) {
            !str_starts_with($currentPath->toString(), $path->toString()) => $uri->withPath($path->append($currentPath)->toString()),
            default => self::normalizePath($uri, $currentPath),
        };
    }

    /**
     * Add a leading slash to the URI path.
     */
    public static function addLeadingSlash(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withPath(Path::fromUri($uri)->withLeadingSlash()->toString());
    }

    /**
     * Add a trailing slash to the URI path.
     */
    public static function addTrailingSlash(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withPath(Path::fromUri($uri)->withTrailingSlash()->toString());
    }

    /**
     * Append a new segment or a new path to the URI path.
     */
    public static function appendSegment(
        Stringable|string $uri,
        Stringable|string $segment
    ): Psr7UriInterface|UriInterface {
        $uri = self::filterUri($uri);

        return self::normalizePath($uri, HierarchicalPath::fromUri($uri)->append($segment));
    }

    /**
     * Convert the Data URI path to its ascii form.
     */
    public static function dataPathToAscii(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withPath(DataPath::fromUri($uri)->toAscii()->toString());
    }

    /**
     * Convert the Data URI path to its binary (base64encoded) form.
     */
    public static function dataPathToBinary(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withPath(DataPath::fromUri($uri)->toBinary()->toString());
    }

    /**
     * Prepend an new segment or a new path to the URI path.
     */
    public static function prependSegment(
        Stringable|string $uri,
        Stringable|string $segment
    ): Psr7UriInterface|UriInterface {
        $uri = self::filterUri($uri);

        return self::normalizePath($uri, HierarchicalPath::fromUri($uri)->prepend($segment));
    }

    /**
     * Remove a basepath from the URI path.
     */
    public static function removeBasePath(
        Stringable|string $uri,
        Stringable|string $path
    ): Psr7UriInterface|UriInterface {
        $uri = self::filterUri($uri);
        $basePath = HierarchicalPath::new($path)->withLeadingSlash()->toString();
        $currentPath = HierarchicalPath::fromUri($uri)->withLeadingSlash()->toString();
        $newPath = substr($currentPath, strlen($basePath));

        return match (true) {
            '/' === $basePath,
            !str_starts_with($currentPath, $basePath),
            !str_starts_with($newPath, '/') => $uri,
            default => $uri->withPath($newPath),
        };
    }

    /**
     * Remove dot segments from the URI path.
     */
    public static function removeDotSegments(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withPath(Path::fromUri($uri)->withoutDotSegments()->toString());
    }

    /**
     * Remove empty segments from the URI path.
     */
    public static function removeEmptySegments(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withPath(HierarchicalPath::fromUri($uri)->withoutEmptySegments()->toString());
    }

    /**
     * Remove the leading slash from the URI path.
     */
    public static function removeLeadingSlash(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return self::normalizePath($uri, Path::fromUri($uri)->withoutLeadingSlash());
    }

    /**
     * Remove the trailing slash from the URI path.
     */
    public static function removeTrailingSlash(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withPath(Path::fromUri($uri)->withoutTrailingSlash()->toString());
    }

    /**
     * Remove path segments from the URI path according to their offsets.
     */
    public static function removeSegments(Stringable|string $uri, int ...$keys): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withPath(HierarchicalPath::fromUri($uri)->withoutSegment(...$keys)->toString());
    }

    /**
     * Replace the URI path basename.
     */
    public static function replaceBasename(Stringable|string $uri, Stringable|string $basename): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return self::normalizePath($uri, HierarchicalPath::fromUri($uri)->withBasename($basename));
    }

    /**
     * Replace the data URI path parameters.
     */
    public static function replaceDataUriParameters(Stringable|string $uri, Stringable|string $parameters): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withPath(DataPath::fromUri($uri)->withParameters($parameters)->toString());
    }

    /**
     * Replace the URI path dirname.
     */
    public static function replaceDirname(Stringable|string $uri, Stringable|string $dirname): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return self::normalizePath($uri, HierarchicalPath::fromUri($uri)->withDirname($dirname));
    }

    /**
     * Replace the URI path basename extension.
     */
    public static function replaceExtension(Stringable|string $uri, Stringable|string $extension): Psr7UriInterface|UriInterface
    {
        $uri = self::filterUri($uri);

        return $uri->withPath(HierarchicalPath::fromUri($uri)->withExtension($extension)->toString());
    }

    /**
     * Replace a segment from the URI path according its offset.
     */
    public static function replaceSegment(
        Stringable|string $uri,
        int $offset,
        Stringable|string $segment
    ): Psr7UriInterface|UriInterface {
        $uri = self::filterUri($uri);

        return $uri->withPath(HierarchicalPath::fromUri($uri)->withSegment($offset, $segment)->toString());
    }

    /**
     * Input URI normalization to allow Stringable and string URI.
     */
    private static function filterUri(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        return match (true) {
            $uri instanceof BaseUri => $uri->uri(),
            $uri instanceof Psr7UriInterface, $uri instanceof UriInterface => $uri,
            default => Uri::new($uri),
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
        $pathString = $path->toString();
        $authority = $uri->getAuthority();

        return match (true) {
            '' === $pathString,
            '/' === $pathString[0],
            null === $authority,
            '' === $authority => $uri->withPath($pathString),
            default => $uri->withPath('/'.$pathString),
        };
    }

    /**
     * Normalize the URI component value depending on the subject interface.
     *
     * null value MUST be converted to the emptu string if a Psr7 UriInterface is being manipulated.
     */
    private static function normalizeComponent(?string $component, Psr7UriInterface|UriInterface $uri): ?string
    {
        return match (true) {
            $uri instanceof Psr7UriInterface => (string) $component,
            default => $component,
        };
    }
}

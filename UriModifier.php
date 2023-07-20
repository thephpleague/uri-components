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

use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;

/**
 * @deprecated since version 7.0.0
 * @codeCoverageIgnore
 * @see Modifier
 */
class UriModifier
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
        return Modifier::from($uri)->appendQuery($query)->get();
    }

    /**
     * Merge a new query with the existing URI query.
     */
    public static function mergeQuery(
        Stringable|string $uri,
        Stringable|string|null $query
    ): Psr7UriInterface|UriInterface {
        return Modifier::from($uri)->mergeQuery($query)->get();
    }

    /**
     * Remove query data according to their key name.
     */
    public static function removePairs(Stringable|string $uri, string ...$keys): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->removePairs(...$keys)->get();
    }

    /**
     * Remove empty pairs from the URL query component.
     *
     * A pair is considered empty if it's name is the empty string
     * and its value is either the empty string or the null value
     */
    public static function removeEmptyPairs(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->removeEmptyPairs()->get();
    }

    /**
     * Remove query data according to their key name.
     */
    public static function removeParams(Stringable|string $uri, string ...$keys): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->removeParams(...$keys)->get();
    }

    /**
     * Sort the URI query by keys.
     */
    public static function sortQuery(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->sortQuery()->get();
    }

    /*********************************
     * Host resolution methods
     *********************************/

    /**
     * Add the root label to the URI.
     */
    public static function addRootLabel(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->addRootLabel()->get();
    }

    /**
     * Append a label or a host to the current URI host.
     *
     * @throws SyntaxError If the host can not be appended
     */
    public static function appendLabel(Stringable|string $uri, Stringable|string|null $label): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->appendLabel($label)->get();
    }

    /**
     * Convert the URI host part to its ascii value.
     */
    public static function hostToAscii(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->hostToAscii()->get();
    }

    /**
     * Convert the URI host part to its unicode value.
     */
    public static function hostToUnicode(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->hostToUnicode()->get();
    }

    /**
     * Prepend a label or a host to the current URI host.
     *
     * @throws SyntaxError If the host can not be prepended
     */
    public static function prependLabel(Stringable|string $uri, Stringable|string|null $label): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->prependLabel($label)->get();
    }

    /**
     * Remove host labels according to their offset.
     */
    public static function removeLabels(Stringable|string $uri, int ...$keys): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->removeLabels(...$keys)->get();
    }

    /**
     * Remove the root label to the URI.
     */
    public static function removeRootLabel(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->removeRootLabel()->get();
    }

    /**
     * Remove the host zone identifier.
     */
    public static function removeZoneId(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->removeZoneId()->get();
    }

    /**
     * Replace a label of the current URI host.
     */
    public static function replaceLabel(
        Stringable|string $uri,
        int $offset,
        Stringable|string|null $label
    ): Psr7UriInterface|UriInterface {
        return Modifier::from($uri)->replaceLabel($offset, $label)->get();
    }

    /*********************************
     * Path resolution methods
     *********************************/

    /**
     * Add a new basepath to the URI path.
     */
    public static function addBasePath(Stringable|string $uri, Stringable|string $path): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->addBasePath($path)->get();
    }

    /**
     * Add a leading slash to the URI path.
     */
    public static function addLeadingSlash(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->addLeadingSlash()->get();
    }

    /**
     * Add a trailing slash to the URI path.
     */
    public static function addTrailingSlash(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->addTrailingSlash()->get();
    }

    /**
     * Append a new segment or a new path to the URI path.
     */
    public static function appendSegment(Stringable|string $uri, Stringable|string $segment): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->appendSegment($segment)->get();
    }

    /**
     * Convert the Data URI path to its ascii form.
     */
    public static function dataPathToAscii(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->dataPathToAscii()->get();
    }

    /**
     * Convert the Data URI path to its binary (base64encoded) form.
     */
    public static function dataPathToBinary(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->dataPathToBinary()->get();
    }

    /**
     * Prepend an new segment or a new path to the URI path.
     */
    public static function prependSegment(
        Stringable|string $uri,
        Stringable|string $segment
    ): Psr7UriInterface|UriInterface {
        return Modifier::from($uri)->prependSegment($segment)->get();
    }

    /**
     * Remove a basepath from the URI path.
     */
    public static function removeBasePath(
        Stringable|string $uri,
        Stringable|string $path
    ): Psr7UriInterface|UriInterface {
        return Modifier::from($uri)->removeBasePath($path)->get();
    }

    /**
     * Remove dot segments from the URI path.
     */
    public static function removeDotSegments(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->removeDotSegments()->get();
    }

    /**
     * Remove empty segments from the URI path.
     */
    public static function removeEmptySegments(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->removeEmptySegments()->get();
    }

    /**
     * Remove the leading slash from the URI path.
     */
    public static function removeLeadingSlash(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->removeLeadingSlash()->get();

    }

    /**
     * Remove the trailing slash from the URI path.
     */
    public static function removeTrailingSlash(Stringable|string $uri): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->removeTrailingSlash()->get();
    }

    /**
     * Remove path segments from the URI path according to their offsets.
     */
    public static function removeSegments(Stringable|string $uri, int ...$keys): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->removeSegments(...$keys)->get();
    }

    /**
     * Replace the URI path basename.
     */
    public static function replaceBasename(Stringable|string $uri, Stringable|string $basename): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->replaceBasename($basename)->get();
    }

    /**
     * Replace the data URI path parameters.
     */
    public static function replaceDataUriParameters(Stringable|string $uri, Stringable|string $parameters): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->replaceDataUriParameters($parameters)->get();
    }

    /**
     * Replace the URI path dirname.
     */
    public static function replaceDirname(Stringable|string $uri, Stringable|string $dirname): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->replaceDirname($dirname)->get();
    }

    /**
     * Replace the URI path basename extension.
     */
    public static function replaceExtension(Stringable|string $uri, Stringable|string $extension): Psr7UriInterface|UriInterface
    {
        return Modifier::from($uri)->replaceExtension($extension)->get();
    }

    /**
     * Replace a segment from the URI path according its offset.
     */
    public static function replaceSegment(
        Stringable|string $uri,
        int $offset,
        Stringable|string $segment
    ): Psr7UriInterface|UriInterface {
        return Modifier::from($uri)->replaceSegment($offset, $segment)->get();
    }
}

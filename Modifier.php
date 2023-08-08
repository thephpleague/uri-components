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

use JsonSerializable;
use League\Uri\Components\DataPath;
use League\Uri\Components\Domain;
use League\Uri\Components\HierarchicalPath;
use League\Uri\Components\Host;
use League\Uri\Components\Path;
use League\Uri\Components\Query;
use League\Uri\Contracts\PathInterface;
use League\Uri\Contracts\UriAccess;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\IPv4\Converter;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use function ltrim;
use function rtrim;

class Modifier implements Stringable, JsonSerializable, UriAccess
{
    final public function __construct(protected readonly Psr7UriInterface|UriInterface $uri)
    {
    }

    public static function from(Stringable|string $uri, ?UriFactoryInterface $uriFactory = null): static
    {
        return new static(match (true) {
            $uri instanceof UriAccess => $uri->getUri(),
            $uri instanceof Psr7UriInterface, $uri instanceof UriInterface => $uri,
            $uriFactory instanceof UriFactoryInterface => $uriFactory->createUri((string) $uri),
            default => Uri::new($uri),
        });
    }

    public function getUri(): Psr7UriInterface|UriInterface
    {
        return $this->uri;
    }

    public function getUriString(): string
    {
        return $this->uri->__toString();
    }

    public function jsonSerialize(): string
    {
        return $this->uri->__toString();
    }

    public function __toString(): string
    {
        return $this->uri->__toString();
    }

    /*********************************
     * Query modifier methods
     *********************************/

    /**
     * Add the new query data to the existing URI query.
     */
    public function appendQuery(Stringable|string|null $query): static
    {
        return new static($this->uri->withQuery(
            static::normalizeComponent(
                Query::fromUri($this->uri)->append($query)->value(),
                $this->uri
            )
        ));
    }

    /**
     * Merge a new query with the existing URI query.
     */
    public function mergeQuery(Stringable|string|null $query): static
    {
        return new static($this->uri->withQuery(
            static::normalizeComponent(
                Query::fromUri($this->uri)->merge($query)->value(),
                $this->uri
            )
        ));
    }

    /**
     * Remove query data according to their key name.
     */
    public function removePairs(string ...$keys): static
    {
        return new static($this->uri->withQuery(
            static::normalizeComponent(
                Query::fromUri($this->uri)->withoutPair(...$keys)->value(),
                $this->uri
            )
        ));
    }

    /**
     * Remove empty pairs from the URL query component.
     *
     * A pair is considered empty if it's name is the empty string
     * and its value is either the empty string or the null value
     */
    public function removeEmptyPairs(): static
    {
        return new static($this->uri->withQuery(
            static::normalizeComponent(
                Query::fromUri($this->uri)->withoutEmptyPairs()->value(),
                $this->uri
            )
        ));
    }

    /**
     * Remove query data according to their key name.
     */
    public function removeParams(string ...$keys): static
    {
        return new static($this->uri->withQuery(
            static::normalizeComponent(
                Query::fromUri($this->uri)->withoutParameters(...$keys)->value(),
                $this->uri
            )
        ));
    }

    /**
     * Sort the URI query by keys.
     */
    public function sortQuery(): static
    {
        return new static($this->uri->withQuery(
            static::normalizeComponent(
                Query::fromUri($this->uri)->sort()->value(),
                $this->uri
            )
        ));
    }

    /*********************************
     * Host modifier methods
     *********************************/

    /**
     * Add the root label to the URI.
     */
    public function addRootLabel(): static
    {
        $host = $this->uri->getHost();

        return match (true) {
            null === $host,
            str_ends_with($host, '.') => $this,
            default => new static($this->uri->withHost(Host::new($host.'.')->toUnicode())),
        };
    }

    /**
     * Append a label or a host to the current URI host.
     *
     * @throws SyntaxError If the host can not be appended
     */
    public function appendLabel(Stringable|string|null $label): static
    {
        $host = Host::fromUri($this->uri);
        $label = Host::new($label);

        return match (true) {
            null === $label->value() => $this,
            $host->isDomain() => new static($this->uri->withHost(static::normalizeComponent(Domain::new($host)->append($label)->toUnicode(), $this->uri))),
            $host->isIpv4() => new static($this->uri->withHost($host->value().'.'.ltrim($label->value(), '.'))),
            default => throw new SyntaxError('The URI host '.$host->toString().' can not be appended.'),
        };
    }

    /**
     * Convert the URI host part to its ascii value.
     */
    public function hostToAscii(): static
    {
        return new static($this->uri->withHost(
            static::normalizeComponent(
                Host::fromUri($this->uri)->value(),
                $this->uri
            )
        ));
    }

    /**
     * Convert the URI host part to its unicode value.
     */
    public function hostToUnicode(): static
    {
        return new static($this->uri->withHost(
            static::normalizeComponent(
                Host::fromUri($this->uri)->toUnicode(),
                $this->uri
            )
        ));
    }

    /**
     * Normalizes the URI host content to a IPv4 dot-decimal notation if possible
     * otherwise returns the uri instance unchanged.
     *
     * @see https://url.spec.whatwg.org/#concept-ipv4-parser
     */
    public function hostToDecimal(): static
    {
        $currentHost = $this->uri->getHost();
        $hostIp = Converter::fromEnvironment()->toDecimal($currentHost);

        return match (true) {
            null === $currentHost,
            '' === $currentHost,
            null === $hostIp,
            $currentHost === $hostIp  => $this,
            default => new static($this->uri->withHost($hostIp)),
        };
    }

    /**
     * Normalizes the URI host content to a IPv4 octal notation if possible
     * otherwise returns the uri instance unchanged.
     *
     * @see https://url.spec.whatwg.org/#concept-ipv4-parser
     */
    public function hostToOctal(): static
    {
        $currentHost = $this->uri->getHost();
        $hostIp = Converter::fromEnvironment()->toOctal($currentHost);

        return match (true) {
            null === $currentHost,
            '' === $currentHost,
            null === $hostIp,
            $currentHost === $hostIp  => $this,
            default => new static($this->uri->withHost($hostIp)),
        };
    }

    /**
     * Normalizes the URI host content to a IPv4 octal notation if possible
     * otherwise returns the uri instance unchanged.
     *
     * @see https://url.spec.whatwg.org/#concept-ipv4-parser
     */
    public function hostToHexadecimal(): static
    {
        $currentHost = $this->uri->getHost();
        $hostIp = Converter::fromEnvironment()->toHexadecimal($currentHost);

        return match (true) {
            null === $currentHost,
            '' === $currentHost,
            null === $hostIp,
            $currentHost === $hostIp  => $this,
            default => new static($this->uri->withHost($hostIp)),
        };
    }

    /**
     * Prepend a label or a host to the current URI host.
     *
     * @throws SyntaxError If the host can not be prepended
     */
    public function prependLabel(Stringable|string|null $label): static
    {
        $host = Host::fromUri($this->uri);
        $label = Host::new($label);

        return match (true) {
            null === $label->value() => $this,
            $host->isIpv4() => new static($this->uri->withHost(rtrim($label->value(), '.').'.'.$host->value())),
            $host->isDomain() => new static($this->uri->withHost(static::normalizeComponent(Domain::new($host)->prepend($label)->toUnicode(), $this->uri))),
            default => throw new SyntaxError('The URI host '.$host->toString().' can not be prepended.'),
        };
    }

    /**
     * Remove host labels according to their offset.
     */
    public function removeLabels(int ...$keys): static
    {
        return new static($this->uri->withHost(
            static::normalizeComponent(
                Domain::fromUri($this->uri)->withoutLabel(...$keys)->toUnicode(),
                $this->uri
            )
        ));
    }

    /**
     * Remove the root label to the URI.
     */
    public function removeRootLabel(): static
    {
        $currentHost = $this->uri->getHost();

        return match (true) {
            null === $currentHost,
            '' === $currentHost,
            !str_ends_with($currentHost, '.') => $this,
            default => new static($this->uri->withHost(substr($currentHost, 0, -1))),
        };
    }

    /**
     * Slice the host from the URI.
     */
    public function sliceLabels(int $offset, int $length = null): static
    {
        $currentHost = $this->uri->getHost();
        $host = Domain::new($currentHost)->slice($offset, $length);

        return match (true) {
            $host->value() === $currentHost,
            $host->toUnicode() === $currentHost => $this,
            default => new static($this->uri->withHost($host->toUnicode())),
        };
    }

    /**
     * Remove the host zone identifier.
     */
    public function removeZoneId(): static
    {
        return new static($this->uri->withHost(
            static::normalizeComponent(
                Host::fromUri($this->uri)->withoutZoneIdentifier()->value(),
                $this->uri
            )
        ));
    }

    /**
     * Replace a label of the current URI host.
     */
    public function replaceLabel(int $offset, Stringable|string|null $label): static
    {
        return new static($this->uri->withHost(
            static::normalizeComponent(
                Domain::fromUri($this->uri)->withLabel($offset, $label)->toUnicode(),
                $this->uri
            )
        ));
    }

    /*********************************
     * Path modifier methods
     *********************************/

    /**
     * Add a new base path to the URI path.
     */
    public function addBasePath(Stringable|string $path): static
    {
        /** @var HierarchicalPath $path */
        $path = HierarchicalPath::new($path)->withLeadingSlash();
        /** @var HierarchicalPath $currentPath */
        $currentPath = HierarchicalPath::fromUri($this->uri)->withLeadingSlash();

        return new static(match (true) {
            !str_starts_with($currentPath->toString(), $path->toString()) => $this->uri->withPath($path->append($currentPath)->toString()),
            default => static::normalizePath($this->uri, $currentPath),
        });
    }

    /**
     * Add a leading slash to the URI path.
     */
    public function addLeadingSlash(): static
    {
        return new static($this->uri->withPath(Path::fromUri($this->uri)->withLeadingSlash()->toString()));
    }

    /**
     * Add a trailing slash to the URI path.
     */
    public function addTrailingSlash(): static
    {
        return new static($this->uri->withPath(Path::fromUri($this->uri)->withTrailingSlash()->toString()));
    }

    /**
     * Append a new segment or a new path to the URI path.
     */
    public function appendSegment(Stringable|string $segment): static
    {
        return new static(static::normalizePath($this->uri, HierarchicalPath::fromUri($this->uri)->append($segment)));
    }

    /**
     * Convert the Data URI path to its ascii form.
     */
    public function dataPathToAscii(): static
    {
        return new static($this->uri->withPath(DataPath::fromUri($this->uri)->toAscii()->toString()));
    }

    /**
     * Convert the Data URI path to its binary (base64encoded) form.
     */
    public function dataPathToBinary(): static
    {
        return new static($this->uri->withPath(DataPath::fromUri($this->uri)->toBinary()->toString()));
    }

    /**
     * Prepend a new segment or a new path to the URI path.
     */
    public function prependSegment(Stringable|string $segment): static
    {
        return new static(static::normalizePath($this->uri, HierarchicalPath::fromUri($this->uri)->prepend($segment)));
    }

    /**
     * Remove a base path from the URI path.
     */
    public function removeBasePath(Stringable|string $path): static
    {
        $basePath = HierarchicalPath::new($path)->withLeadingSlash()->toString();
        $currentPath = HierarchicalPath::fromUri($this->uri)->withLeadingSlash()->toString();
        $newPath = substr($currentPath, strlen($basePath));

        return match (true) {
            '/' === $basePath,
            !str_starts_with($currentPath, $basePath),
            !str_starts_with($newPath, '/') => $this,
            default => new static($this->uri->withPath($newPath)),
        };
    }

    /**
     * Remove dot segments from the URI path.
     */
    public function removeDotSegments(): static
    {
        return new static($this->uri->withPath(Path::fromUri($this->uri)->withoutDotSegments()->toString()));
    }

    /**
     * Remove empty segments from the URI path.
     */
    public function removeEmptySegments(): static
    {
        return new static($this->uri->withPath(HierarchicalPath::fromUri($this->uri)->withoutEmptySegments()->toString()));
    }

    /**
     * Remove the leading slash from the URI path.
     */
    public function removeLeadingSlash(): static
    {
        return new static(static::normalizePath($this->uri, Path::fromUri($this->uri)->withoutLeadingSlash()));
    }

    /**
     * Remove the trailing slash from the URI path.
     */
    public function removeTrailingSlash(): static
    {
        return new static($this->uri->withPath(Path::fromUri($this->uri)->withoutTrailingSlash()->toString()));
    }

    /**
     * Remove path segments from the URI path according to their offsets.
     */
    public function removeSegments(int ...$keys): static
    {
        return new static($this->uri->withPath(HierarchicalPath::fromUri($this->uri)->withoutSegment(...$keys)->toString()));
    }

    /**
     * Replace the URI path basename.
     */
    public function replaceBasename(Stringable|string $basename): static
    {
        return new static(static::normalizePath($this->uri, HierarchicalPath::fromUri($this->uri)->withBasename($basename)));
    }

    /**
     * Replace the data URI path parameters.
     */
    public function replaceDataUriParameters(Stringable|string $parameters): static
    {
        return new static($this->uri->withPath(DataPath::fromUri($this->uri)->withParameters($parameters)->toString()));
    }

    /**
     * Replace the URI path dirname.
     */
    public function replaceDirname(Stringable|string $dirname): static
    {
        return new static(static::normalizePath($this->uri, HierarchicalPath::fromUri($this->uri)->withDirname($dirname)));
    }

    /**
     * Replace the URI path basename extension.
     */
    public function replaceExtension(Stringable|string $extension): static
    {
        return new static($this->uri->withPath(HierarchicalPath::fromUri($this->uri)->withExtension($extension)->toString()));
    }

    /**
     * Replace a segment from the URI path according its offset.
     */
    public function replaceSegment(int $offset, Stringable|string $segment): static
    {
        return new static($this->uri->withPath(HierarchicalPath::fromUri($this->uri)->withSegment($offset, $segment)->toString()));
    }

    /**
     * Slice the host from the URI.
     */
    public function sliceSegments(int $offset, int $length = null): static
    {
        return new static(static::normalizePath($this->uri, HierarchicalPath::fromUri($this->uri)->slice($offset, $length)));
    }

    /**
     * Normalize a URI path.
     *
     * Make sure the path always has a leading slash if an authority is present
     * and the path is not the empty string.
     */
    final protected static function normalizePath(Psr7UriInterface|UriInterface $uri, PathInterface $path): Psr7UriInterface|UriInterface
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
     * null value MUST be converted to the empty string if a Psr7 UriInterface is being manipulated.
     */
    final protected static function normalizeComponent(?string $component, Psr7UriInterface|UriInterface $uri): ?string
    {
        return match (true) {
            $uri instanceof Psr7UriInterface => (string) $component,
            default => $component,
        };
    }
}

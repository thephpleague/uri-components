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
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use function ltrim;
use function rtrim;

final class Modifier implements Stringable, JsonSerializable, UriAccess
{
    public function __construct(private readonly Psr7UriInterface|UriInterface $uri)
    {
    }

    public static function from(Stringable|string $uri, ?UriFactoryInterface $uriFactory = null): self
    {
        return new self(match (true) {
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
    public function appendQuery(Stringable|string|null $query): self
    {
        return new self($this->uri->withQuery(
            self::normalizeComponent(
                Query::fromUri($this->uri)->append($query)->value(),
                $this->uri
            )
        ));
    }

    /**
     * Merge a new query with the existing URI query.
     */
    public function mergeQuery(Stringable|string|null $query): self
    {
        return new self($this->uri->withQuery(
            self::normalizeComponent(
                Query::fromUri($this->uri)->merge($query)->value(),
                $this->uri
            )
        ));
    }

    /**
     * Remove query data according to their key name.
     */
    public function removePairs(string ...$keys): self
    {
        return new self($this->uri->withQuery(
            self::normalizeComponent(
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
    public function removeEmptyPairs(): self
    {
        return new self($this->uri->withQuery(
            self::normalizeComponent(
                Query::fromUri($this->uri)->withoutEmptyPairs()->value(),
                $this->uri
            )
        ));
    }

    /**
     * Remove query data according to their key name.
     */
    public function removeParams(string ...$keys): self
    {
        return new self($this->uri->withQuery(
            self::normalizeComponent(
                Query::fromUri($this->uri)->withoutParameters(...$keys)->value(),
                $this->uri
            )
        ));
    }

    /**
     * Sort the URI query by keys.
     */
    public function sortQuery(): self
    {
        return new self($this->uri->withQuery(
            self::normalizeComponent(
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
    public function addRootLabel(): self
    {
        $host = Domain::fromUri($this->uri)->withRootLabel()->value();
        if (null === $host || $host === $this->uri->getHost() || !str_ends_with($host, '.')) {
            return $this;
        }

        return new self($this->uri->withHost($this->uri->getHost().'.'));
    }

    /**
     * Append a label or a host to the current URI host.
     *
     * @throws SyntaxError If the host can not be appended
     */
    public function appendLabel(Stringable|string|null $label): self
    {
        $host = Host::fromUri($this->uri);
        $label = Host::new($label);

        return match (true) {
            null === $label->value() => $this,
            $host->isDomain() => new self($this->uri->withHost(self::normalizeComponent(Domain::new($host)->append($label)->toUnicode(), $this->uri))),
            $host->isIpv4() => new self($this->uri->withHost($host->value().'.'.ltrim($label->value(), '.'))),
            default => throw new SyntaxError('The URI host '.$host->toString().' can not be appended.'),
        };
    }

    /**
     * Convert the URI host part to its ascii value.
     */
    public function hostToAscii(): self
    {
        return new self($this->uri->withHost(
            self::normalizeComponent(
                Host::fromUri($this->uri)->value(),
                $this->uri
            )
        ));
    }

    /**
     * Convert the URI host part to its unicode value.
     */
    public function hostToUnicode(): self
    {
        return new self($this->uri->withHost(
            self::normalizeComponent(
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
    public function normalizeIPv4(): self
    {
        $hostIp = Host::fromUri($this->uri)->toIPv4();

        return match (true) {
            null === $hostIp => $this,
            default => new self($this->uri->withHost($hostIp)),
        };
    }

    /**
     * Prepend a label or a host to the current URI host.
     *
     * @throws SyntaxError If the host can not be prepended
     */
    public function prependLabel(Stringable|string|null $label): self
    {
        $host = Host::fromUri($this->uri);
        $label = Host::new($label);

        return match (true) {
            null === $label->value() => $this,
            $host->isIpv4() => new self($this->uri->withHost(rtrim($label->value(), '.').'.'.$host->value())),
            $host->isDomain() => new self($this->uri->withHost(self::normalizeComponent(Domain::new($host)->prepend($label)->toUnicode(), $this->uri))),
            default => throw new SyntaxError('The URI host '.$host->toString().' can not be prepended.'),
        };
    }

    /**
     * Remove host labels according to their offset.
     */
    public function removeLabels(int ...$keys): self
    {
        return new self($this->uri->withHost(
            self::normalizeComponent(
                Domain::fromUri($this->uri)->withoutLabel(...$keys)->toUnicode(),
                $this->uri
            )
        ));
    }

    /**
     * Remove the root label to the URI.
     */
    public function removeRootLabel(): self
    {
        $currentHost = $this->uri->getHost();
        if (null === $currentHost || '' === $currentHost || !str_ends_with($currentHost, '.')) {
            return $this;
        }

        /** @var string $host */
        $host = Domain::new($currentHost)->value();

        return new self($this->uri->withHost(substr($host, 0, -1)));
    }

    /**
     * Remove the host zone identifier.
     */
    public function removeZoneId(): self
    {
        return new self($this->uri->withHost(
            self::normalizeComponent(
                Host::fromUri($this->uri)->withoutZoneIdentifier()->value(),
                $this->uri
            )
        ));
    }

    /**
     * Replace a label of the current URI host.
     */
    public function replaceLabel(int $offset, Stringable|string|null $label): self
    {
        return new self($this->uri->withHost(
            self::normalizeComponent(
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
    public function addBasePath(Stringable|string $path): self
    {
        /** @var HierarchicalPath $path */
        $path = HierarchicalPath::new($path)->withLeadingSlash();
        /** @var HierarchicalPath $currentPath */
        $currentPath = HierarchicalPath::fromUri($this->uri)->withLeadingSlash();

        return new self(match (true) {
            !str_starts_with($currentPath->toString(), $path->toString()) => $this->uri->withPath($path->append($currentPath)->toString()),
            default => self::normalizePath($this->uri, $currentPath),
        });
    }

    /**
     * Add a leading slash to the URI path.
     */
    public function addLeadingSlash(): self
    {
        return new self($this->uri->withPath(Path::fromUri($this->uri)->withLeadingSlash()->toString()));
    }

    /**
     * Add a trailing slash to the URI path.
     */
    public function addTrailingSlash(): self
    {
        return new self($this->uri->withPath(Path::fromUri($this->uri)->withTrailingSlash()->toString()));
    }

    /**
     * Append a new segment or a new path to the URI path.
     */
    public function appendSegment(Stringable|string $segment): self
    {
        return new self(self::normalizePath($this->uri, HierarchicalPath::fromUri($this->uri)->append($segment)));
    }

    /**
     * Convert the Data URI path to its ascii form.
     */
    public function dataPathToAscii(): self
    {
        return new self($this->uri->withPath(DataPath::fromUri($this->uri)->toAscii()->toString()));
    }

    /**
     * Convert the Data URI path to its binary (base64encoded) form.
     */
    public function dataPathToBinary(): self
    {
        return new self($this->uri->withPath(DataPath::fromUri($this->uri)->toBinary()->toString()));
    }

    /**
     * Prepend a new segment or a new path to the URI path.
     */
    public function prependSegment(Stringable|string $segment): self
    {
        return new self(self::normalizePath($this->uri, HierarchicalPath::fromUri($this->uri)->prepend($segment)));
    }

    /**
     * Remove a base path from the URI path.
     */
    public function removeBasePath(Stringable|string $path): self
    {
        $basePath = HierarchicalPath::new($path)->withLeadingSlash()->toString();
        $currentPath = HierarchicalPath::fromUri($this->uri)->withLeadingSlash()->toString();
        $newPath = substr($currentPath, strlen($basePath));

        return match (true) {
            '/' === $basePath,
            !str_starts_with($currentPath, $basePath),
            !str_starts_with($newPath, '/') => $this,
            default => new self($this->uri->withPath($newPath)),
        };
    }

    /**
     * Remove dot segments from the URI path.
     */
    public function removeDotSegments(): self
    {
        return new self($this->uri->withPath(Path::fromUri($this->uri)->withoutDotSegments()->toString()));
    }

    /**
     * Remove empty segments from the URI path.
     */
    public function removeEmptySegments(): self
    {
        return new self($this->uri->withPath(HierarchicalPath::fromUri($this->uri)->withoutEmptySegments()->toString()));
    }

    /**
     * Remove the leading slash from the URI path.
     */
    public function removeLeadingSlash(): self
    {
        return new self(self::normalizePath($this->uri, Path::fromUri($this->uri)->withoutLeadingSlash()));
    }

    /**
     * Remove the trailing slash from the URI path.
     */
    public function removeTrailingSlash(): self
    {
        return new self($this->uri->withPath(Path::fromUri($this->uri)->withoutTrailingSlash()->toString()));
    }

    /**
     * Remove path segments from the URI path according to their offsets.
     */
    public function removeSegments(int ...$keys): self
    {
        return new self($this->uri->withPath(HierarchicalPath::fromUri($this->uri)->withoutSegment(...$keys)->toString()));
    }

    /**
     * Replace the URI path basename.
     */
    public function replaceBasename(Stringable|string $basename): self
    {
        return new self(self::normalizePath($this->uri, HierarchicalPath::fromUri($this->uri)->withBasename($basename)));
    }

    /**
     * Replace the data URI path parameters.
     */
    public function replaceDataUriParameters(Stringable|string $parameters): self
    {
        return new self($this->uri->withPath(DataPath::fromUri($this->uri)->withParameters($parameters)->toString()));
    }

    /**
     * Replace the URI path dirname.
     */
    public function replaceDirname(Stringable|string $dirname): self
    {
        return new self(self::normalizePath($this->uri, HierarchicalPath::fromUri($this->uri)->withDirname($dirname)));
    }

    /**
     * Replace the URI path basename extension.
     */
    public function replaceExtension(Stringable|string $extension): self
    {
        return new self($this->uri->withPath(HierarchicalPath::fromUri($this->uri)->withExtension($extension)->toString()));
    }

    /**
     * Replace a segment from the URI path according its offset.
     */
    public function replaceSegment(int $offset, Stringable|string $segment): self
    {
        return new self($this->uri->withPath(HierarchicalPath::fromUri($this->uri)->withSegment($offset, $segment)->toString()));
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
     * null value MUST be converted to the empty string if a Psr7 UriInterface is being manipulated.
     */
    private static function normalizeComponent(?string $component, Psr7UriInterface|UriInterface $uri): ?string
    {
        return match (true) {
            $uri instanceof Psr7UriInterface => (string) $component,
            default => $component,
        };
    }
}

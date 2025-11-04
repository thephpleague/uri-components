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

namespace League\Uri\Components;

use Deprecated;
use League\Uri\Contracts\PathInterface;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriInterface;
use League\Uri\Encoder;
use League\Uri\UriString;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use Throwable;
use Uri\Rfc3986\Uri as Rfc3986Uri;
use Uri\WhatWg\Url as WhatWgUrl;

use function is_string;
use function substr;

final class Path extends Component implements PathInterface
{
    private const SEPARATOR = '/';

    private readonly string $path;

    /**
     * New instance.
     */
    private function __construct(Stringable|string $path)
    {
        $this->path = $this->validate($path);
    }

    /**
     * Validate the component content.
     */
    private function validate(Stringable|string $path): string
    {
        return (string) $this->validateComponent($path);
    }

    /**
     * Returns a new instance from a string or a stringable object.
     */
    public static function new(Stringable|string $value = ''): self
    {
        return new self($value);
    }

    /**
     * Create a new instance from a string or a stringable structure or returns null on failure.
     */
    public static function tryNew(Stringable|string $uri = ''): ?self
    {
        try {
            return self::new($uri);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Create a new instance from a URI object.
     */
    public static function fromUri(WhatwgUrl|Rfc3986Uri|Stringable|string $uri): self
    {
        $uri = self::filterUri($uri);
        if ($uri instanceof Rfc3986Uri) {
            return self::new($uri->getRawPath());
        }

        if ($uri instanceof WhatwgUrl) {
            return self::new($uri->getPath());
        }

        $path = $uri->getPath();
        $authority = $uri->getAuthority();

        return match (true) {
            null === $authority, '' === $authority, '' === $path, '/' === $path[0] => new self($path),
            default => new self('/'.$path),
        };
    }

    public function value(): string
    {
        return Encoder::encodePath($this->path);
    }

    public function equals(mixed $value): bool
    {
        if (!$value instanceof Stringable && !is_string($value)) {
            return false;
        }

        if (!$value instanceof UriComponentInterface) {
            $value = self::tryNew($value);
            if (null === $value) {
                return false;
            }
        }

        return $value->getUriComponent() === $this->getUriComponent();
    }

    public function decoded(): string
    {
        return $this->path;
    }

    public function normalize(): self
    {
        return new self((string) Encoder::normalizePath($this->withoutDotSegments()));
    }

    public function isAbsolute(): bool
    {
        return self::SEPARATOR === ($this->path[0] ?? '');
    }

    public function hasTrailingSlash(): bool
    {
        return '' !== $this->path && self::SEPARATOR === substr($this->path, -1);
    }

    public function withoutDotSegments(): PathInterface
    {
        $current = $this->toString();
        $new = UriString::removeDotSegments($current);
        if ($current === $new) {
            return $this;
        }

        return new self($new);
    }

    /**
     * Returns an instance with a trailing slash.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component with a trailing slash
     */
    public function withTrailingSlash(): PathInterface
    {
        return $this->hasTrailingSlash() ? $this : new self($this->toString().self::SEPARATOR);
    }

    public function withoutTrailingSlash(): PathInterface
    {
        return !$this->hasTrailingSlash() ? $this : new self(substr($this->toString(), 0, -1));
    }

    public function withLeadingSlash(): PathInterface
    {
        return $this->isAbsolute() ? $this : new self(self::SEPARATOR.$this->toString());
    }

    public function withoutLeadingSlash(): PathInterface
    {
        return !$this->isAbsolute() ? $this : new self(substr($this->toString(), 1));
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see HierarchicalPath::new()
     *
     * @codeCoverageIgnore
     *
     * Returns a new instance from a string or a stringable object.
     */
    #[Deprecated(message:'use League\Uri\Components\HierarchicalPath::new() instead', since:'league/uri-components:7.0.0')]
    public static function createFromString(Stringable|string|int $path): self
    {
        return self::new((string) $path);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see HierarchicalPath::fromUri()
     *
     * @codeCoverageIgnore
     *
     * Create a new instance from a URI object.
     */
    #[Deprecated(message:'use League\Uri\Components\HierarchicalPath::fromUri() instead', since:'league/uri-components:7.0.0')]
    public static function createFromUri(Psr7UriInterface|UriInterface $uri): self
    {
        return self::fromUri($uri);
    }
}

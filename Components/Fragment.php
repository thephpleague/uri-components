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
use League\Uri\Contracts\FragmentInterface;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriException;
use League\Uri\Contracts\UriInterface;
use League\Uri\Encoder;
use League\Uri\UriString;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use Uri\Rfc3986\Uri as Rfc3986Uri;
use Uri\WhatWg\Url as WhatWgUrl;

use function is_string;
use function str_replace;

final class Fragment extends Component implements FragmentInterface
{
    private readonly ?string $fragment;

    /**
     * New instance.
     */
    private function __construct(Stringable|string|null $fragment)
    {
        $this->fragment = $this->validateComponent($fragment);
    }

    public static function new(Stringable|string|null $value = null): self
    {
        return new self($value);
    }

    /**
     * Create a new instance from a string.or a stringable structure or returns null on failure.
     */
    public static function tryNew(Stringable|string|null $uri = null): ?self
    {
        try {
            return self::new($uri);
        } catch (UriException) {
            return null;
        }
    }

    /**
     * Create a new instance from a URI object.
     */
    public static function fromUri(WhatWgUrl|Rfc3986Uri|Stringable|string $uri): self
    {
        $uri = self::filterUri($uri);

        return match (true) {
            $uri instanceof Rfc3986Uri => new self($uri->getRawFragment()),
            $uri instanceof Psr7UriInterface => new self(UriString::parse($uri)['fragment']),
            default => new self($uri->getFragment()),
        };
    }

    public function value(): ?string
    {
        return Encoder::encodeQueryOrFragment($this->fragment);
    }

    public function getUriComponent(): string
    {
        return (null === $this->fragment ? '' : '#').$this->value();
    }

    /**
     * Returns the decoded fragment.
     */
    public function decoded(): ?string
    {
        if (null === $this->fragment) {
            return null;
        }

        return  str_replace('%20', ' ', $this->fragment);
    }

    public function equals(mixed $value): bool
    {
        if (!$value instanceof Stringable && !is_string($value) && null !== $value) {
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

    public function normalize(): self
    {
        return  new self(Encoder::normalizeFragment($this->value()));
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Fragment::new()
     *
     * @codeCoverageIgnore
     */
    #[Deprecated(message:'use League\Uri\Components\Fragment::new() instead', since:'league/uri-components:7.0.0')]
    public static function createFromString(Stringable|string $fragment): self
    {
        return self::new($fragment);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Fragment::fromUri()
     *
     * @codeCoverageIgnore
     *
     * Create a new instance from a URI object.
     */
    #[Deprecated(message:'use League\Uri\Components\Fragment::fromUri() instead', since:'league/uri-components:7.0.0')]
    public static function createFromUri(Psr7UriInterface|UriInterface $uri): self
    {
        return self::fromUri($uri);
    }
}

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
use League\Uri\Contracts\AuthorityInterface;
use League\Uri\Contracts\PortInterface;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriException;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\UriScheme;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use Uri\Rfc3986\Uri as Rfc3986Uri;
use Uri\WhatWg\Url as WhatWgUrl;

use function filter_var;
use function is_string;

use const FILTER_VALIDATE_INT;

final class Port extends Component implements PortInterface
{
    private readonly ?int $port;
    private ?array $cachedDefaultSchemes = null;

    /**
     * New instance.
     */
    private function __construct(Stringable|string|int|null $port = null)
    {
        $this->port = $this->validate($port);
    }

    public static function new(Stringable|string|int|null $value = null): self
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
        return new self(self::filterUri($uri)->getPort());
    }

    /**
     * Create a new instance from an Authority object.
     */
    public static function fromAuthority(Stringable|string $authority): self
    {
        return match (true) {
            $authority instanceof AuthorityInterface => new self($authority->getPort()),
            default => new self(Authority::new($authority)->getPort()),
        };
    }

    /**
     * Validate a port.
     *
     * @throws SyntaxError if the port is invalid
     */
    private function validate(Stringable|int|string|null $port): ?int
    {
        $port = self::filterComponent($port);
        if (null === $port) {
            return null;
        }

        $fport = filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if (false !== $fport) {
            return $fport;
        }

        throw new SyntaxError('Expected port to be a positive integer or 0; received '.$port.'.');
    }

    public function value(): ?string
    {
        return match (null) {
            $this->port => $this->port,
            default => (string) $this->port,
        };
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

    public function getUriComponent(): string
    {
        return match (null) {
            $this->port => '',
            default => ':'.$this->value(),
        };
    }

    public function toInt(): ?int
    {
        return $this->port;
    }

    public function defaultScheme(): ?Scheme
    {
        return $this->defaultSchemes()[0] ?? null;
    }

    /**
     * @return list<Scheme>
     */
    public function defaultSchemes(): array
    {
        return $this->cachedDefaultSchemes ??= array_map(
            fn (UriScheme $schemePort): Scheme => Scheme::new($schemePort->value),
            UriScheme::fromPort($this->port)
        );
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Port::fromUri()
     *
     * @codeCoverageIgnore
     *
     * Create a new instance from a URI object.
     */
    #[Deprecated(message:'use League\Uri\Components\Port::fromUri() instead', since:'league/uri-components:7.0.0')]
    public static function createFromUri(Psr7UriInterface|UriInterface $uri): self
    {
        return self::fromUri($uri);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Port::fromAuthority()
     *
     * @codeCoverageIgnore
     *
     * Create a new instance from an Authority object.
     */
    #[Deprecated(message:'use League\Uri\Components\Port::fromAuthority() instead', since:'league/uri-components:7.0.0')]
    public static function createFromAuthority(AuthorityInterface|Stringable|string $authority): self
    {
        return self::fromAuthority($authority);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Port::new()
     *
     * @codeCoverageIgnore
     */
    #[Deprecated(message:'use League\Uri\Components\Port::new() instead', since:'league/uri-components:7.0.0')]
    public static function fromInt(int $port): self
    {
        return self::new($port);
    }
}

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
use League\Uri\Contracts\HostInterface;
use League\Uri\Contracts\PortInterface;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriException;
use League\Uri\Contracts\UriInterface;
use League\Uri\Contracts\UserInfoInterface;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\UriString;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use SensitiveParameter;
use Stringable;
use Uri\Rfc3986\Uri as Rfc3986Uri;
use Uri\WhatWg\Url as WhatWgUrl;

use function is_string;

final class Authority extends Component implements AuthorityInterface
{
    private readonly HostInterface $host;
    private readonly PortInterface $port;
    private readonly UserInfoInterface $userInfo;

    public function __construct(
        Stringable|string|null $host,
        Stringable|string|int|null $port = null,
        #[SensitiveParameter] Stringable|string|null $userInfo = null
    ) {
        $this->host = !$host instanceof HostInterface ? Host::new($host) : $host;
        $this->port = !$port instanceof PortInterface ? Port::new($port) : $port;
        $this->userInfo = !$userInfo instanceof UserInfoInterface ? UserInfo::new($userInfo) : $userInfo;
        if (null === $this->host->value() && null !== $this->value()) {
            throw new SyntaxError('A non-empty authority must contains a non null host.');
        }
    }

    /**
     * @throws SyntaxError If the component contains invalid HostInterface part.
     */
    public static function new(Stringable|string|null $value = null): self
    {
        $components = UriString::parseAuthority(self::filterComponent($value));

        return new self(
            Host::new($components['host']),
            Port::new($components['port']),
            new UserInfo(
                $components['user'],
                $components['pass']
            )
        );
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
    public static function fromUri(WhatwgUrl|Rfc3986Uri|Stringable|string $uri): self
    {
        $uri = self::filterUri($uri);
        if ($uri instanceof Rfc3986Uri) {
            return new self($uri->getHost(), $uri->getPort(), $uri->getUserInfo());
        }

        if ($uri instanceof WhatWgUrl) {
            $userInfo = $uri->getUsername();
            if (null !== ($password = $uri->getPassword())) {
                $userInfo .= ':'.$password;
            }

            return new self($uri->getUnicodeHost(), $uri->getPort(), $userInfo);
        }

        if ($uri instanceof Psr7UriInterface) {
            $components = UriString::parse($uri);
            $userInfo = $components['user'];
            if (null !== ($password = $components['pass'])) {
                $userInfo .= ':'.$password;
            }

            return new self($components['host'], $components['port'], $userInfo);
        }

        return self::new($uri->getAuthority());
    }

    /**
     * Create a new instance from a hash of parse_url parts.
     *
     * Create a new instance from a hash representation of the URI similar
     * to PHP parse_url function result
     *
     * @param array{
     *     user? : ?string,
     *     pass? : ?string,
     *     host? : ?string,
     *     port? : ?int
     * } $components
     */
    public static function fromComponents(array $components): self
    {
        $components += ['user' => null, 'pass' => null, 'host' => null, 'port' => null];

        return match (true) {
            null === $components['user'] => new self($components['host'], $components['port']),
            null === $components['pass'] => new self($components['host'], $components['port'], $components['user']),
            default => new self($components['host'], $components['port'], $components['user'].':'.$components['pass']),
        };
    }

    public function value(): ?string
    {
        return self::getAuthorityValue($this->userInfo, $this->host, $this->port);
    }

    private static function getAuthorityValue(
        UserInfoInterface $userInfo,
        HostInterface $host,
        PortInterface $port
    ): ?string {
        $auth = $host->value();
        $port = $port->value();
        if (null !== $port) {
            $auth .= ':'.$port;
        }

        $userInfo = $userInfo->value();

        return match (null) {
            $userInfo => $auth,
            default => $userInfo.'@'.$auth,
        };
    }

    public function getUriComponent(): string
    {
        return match (null) {
            $this->host->value() => $this->toString(),
            default => '//'.$this->toString(),
        };
    }

    public function getHost(): ?string
    {
        return $this->host->value();
    }

    public function getPort(): ?int
    {
        return $this->port->toInt();
    }

    public function getUserInfo(): ?string
    {
        return $this->userInfo->value();
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

    /**
     * @return array{user: ?string, pass: ?string, host: ?string, port: ?int}
     */
    public function components(): array
    {
        return  $this->userInfo->components() + [
            'host' => $this->host->value(),
            'port' => $this->port->toInt(),
        ];
    }

    public function withHost(Stringable|string|null $host): AuthorityInterface
    {
        if (!$host instanceof HostInterface) {
            $host = Host::new($host);
        }

        return match ($this->host->value()) {
            $host->value() => $this,
            default => new self($host, $this->port, $this->userInfo),
        };
    }

    public function withPort(Stringable|string|int|null $port): AuthorityInterface
    {
        if (!$port instanceof PortInterface) {
            $port = Port::new($port);
        }

        return match ($this->port->value()) {
            $port->value() => $this,
            default => new self($this->host, $port, $this->userInfo),
        };
    }

    public function withUserInfo(Stringable|string|null $user, #[SensitiveParameter] Stringable|string|null $password = null): AuthorityInterface
    {
        $userInfo = new UserInfo($user, $password);

        return match ($this->userInfo->value()) {
            $userInfo->value() => $this,
            default => new self($this->host, $this->port, $userInfo),
        };
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Authority::fromUri()
     *
     * @codeCoverageIgnore
     *
     * Create a new instance from a URI object.
     */
    #[Deprecated(message:'use League\Uri\Components\Authority::fromUri() instead', since:'league/uri-components:7.0.0')]
    public static function createFromUri(UriInterface|Psr7UriInterface $uri): self
    {
        return self::fromUri($uri);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Authority::new()
     *
     * @codeCoverageIgnore
     *
     * Returns a new instance from a string or a stringable object.
     */
    #[Deprecated(message:'use League\Uri\Components\Authority::new() instead', since:'league/uri-components:7.0.0')]
    public static function createFromString(Stringable|string $authority): self
    {
        return self::new($authority);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Authority::new()
     *
     * @codeCoverageIgnore
     *
     * Returns a new instance from null.
     */
    #[Deprecated(message:'use League\Uri\Components\Authority::new() instead', since:'league/uri-components:7.0.0')]
    public static function createFromNull(): self
    {
        return self::new();
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Authority::fromComponents()
     *
     * @codeCoverageIgnore
     *
     * Create a new instance from a hash of parse_url parts.
     *
     * Create a new instance from a hash representation of the URI similar
     * to PHP parse_url function result
     *
     * @param array{
     *     user? : ?string,
     *     pass? : ?string,
     *     host? : ?string,
     *     port? : ?int
     * } $components
     */
    #[Deprecated(message:'use League\Uri\Components\Authority::fromComponents() instead', since:'league/uri-components:7.0.0')]
    public static function createFromComponents(array $components): self
    {
        return self::fromComponents($components);
    }
}

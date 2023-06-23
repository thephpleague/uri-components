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

use League\Uri\Contracts\AuthorityInterface;
use League\Uri\Contracts\HostInterface;
use League\Uri\Contracts\PortInterface;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriInterface;
use League\Uri\Contracts\UserInfoInterface;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Uri;
use League\Uri\UriString;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;

final class Authority extends Component implements AuthorityInterface
{
    private readonly UserInfoInterface $userInfo;
    private readonly HostInterface $host;
    private readonly PortInterface $port;

    /**
     * @throws SyntaxError If the component contains invalid HostInterface part.
     */
    private function __construct(Stringable|string|null $authority)
    {
        $components = UriString::parseAuthority(self::filterComponent($authority));
        $this->host = Host::new($components['host']);
        $this->port = Port::new($components['port']);
        $this->userInfo = new UserInfo($components['user'], $components['pass']);

        if (null === $this->host->value() && null !== $this->value()) {
            throw new SyntaxError('A non-empty authority must contains a non null host.');
        }
    }

    /**
     * Returns a new instance from a value.
     */
    public static function new(Stringable|string|null $value = null): self
    {
        if ($value instanceof UriComponentInterface) {
            return new self($value->value());
        }

        return new self($value);
    }

    /**
     * Create a new instance from a URI object.
     */
    public static function fromUri(Stringable|string $uri): self
    {
        if ($uri instanceof Psr7UriInterface) {
            $authority = $uri->getAuthority();
            if ('' === $authority) {
                return self::new();
            }

            return new self($authority);
        }

        if ($uri instanceof UriInterface) {
            return new self($uri->getAuthority());
        }

        return new self(Uri::new($uri)->getAuthority());
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

        $authority = $components['host'];
        if (null !== $components['port']) {
            $authority .= ':'.$components['port'];
        }

        $userInfo = null;
        if (null !== $components['user']) {
            $userInfo = $components['user'];
            if (null !== $components['pass']) {
                $userInfo .= ':'.$components['pass'];
            }
        }

        if (null !== $userInfo) {
            $authority = $userInfo.'@'.$authority;
        }

        return new self($authority);
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
        if (null === $userInfo) {
            return $auth;
        }

        return $userInfo.'@'.$auth;
    }

    public function getUriComponent(): string
    {
        return  (null === $this->host->value()) ? $this->toString() : '//'.$this->toString();
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

    public function withHost(UriComponentInterface|Stringable|string|null $host): AuthorityInterface
    {
        if (!$host instanceof HostInterface) {
            $host = Host::new($host);
        }

        if ($host->value() === $this->host->value()) {
            return $this;
        }

        return $this->newInstance($this->userInfo, $host, $this->port);
    }

    public function withPort(UriComponentInterface|Stringable|string|int|null $port): AuthorityInterface
    {
        if (!$port instanceof Port) {
            $port = Port::new($port);
        }

        if ($port->value() === $this->port->value()) {
            return $this;
        }

        return $this->newInstance($this->userInfo, $this->host, $port);
    }

    public function withUserInfo(Stringable|string|null $user, Stringable|string|null $password = null): AuthorityInterface
    {
        $userInfo = new UserInfo($user, $password);
        if ($userInfo->value() === $this->userInfo->value()) {
            return $this;
        }

        return $this->newInstance($userInfo, $this->host, $this->port);
    }

    private function newInstance(UserInfoInterface $userInfo, HostInterface $host, PortInterface $port): self
    {
        $value = self::getAuthorityValue($userInfo, $host, $port);
        if (null === $host->value() && null !== $value) {
            throw new SyntaxError('A non-empty authority must contains a non null host.');
        }

        return new self($value);
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
    public static function createFromNull(): self
    {
        return self::new();
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Authority::fromComponents()
     *x
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
    public static function createFromComponents(array $components): self
    {
        return self::fromComponents($components);
    }
}

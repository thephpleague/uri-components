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
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriException;
use League\Uri\Contracts\UriInterface;
use League\Uri\Contracts\UserInfoInterface;
use League\Uri\Encoder;
use League\Uri\UriString;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use SensitiveParameter;
use Stringable;
use Uri\Rfc3986\Uri as Rfc3986Uri;
use Uri\WhatWg\Url as WhatWgUrl;

use function explode;
use function is_string;

final class UserInfo extends Component implements UserInfoInterface
{
    private readonly ?string $username;
    private readonly ?string $password;

    /**
     * New instance.
     */
    public function __construct(
        Stringable|string|null $username,
        #[SensitiveParameter] Stringable|string|null $password = null,
    ) {
        $this->username = $this->validateComponent($username);
        $this->password = $this->validateComponent($password);
    }

    /**
     * Create a new instance from a URI object.
     */
    public static function fromUri(Rfc3986Uri|WhatWgUrl|Stringable|string $uri): self
    {
        $uri = self::filterUri($uri);
        if ($uri instanceof Rfc3986Uri) {
            return new self($uri->getRawUsername(), $uri->getRawPassword());
        }

        if ($uri instanceof WhatWgUrl || $uri instanceof UriInterface) {
            return new self($uri->getUsername(), $uri->getPassword());
        }

        $components = UriString::parse($uri);

        return new self($components['user'], $components['pass']);
    }

    /**
     * Create a new instance from an Authority object.
     */
    public static function fromAuthority(Stringable|string|null $authority): self
    {
        return match (true) {
            $authority instanceof AuthorityInterface => self::new($authority->getUserInfo()),
            default => self::new(Authority::new($authority)->getUserInfo()),
        };
    }

    /**
     * Create a new instance from a hash of parse_url parts.
     *
     * Create a new instance from a hash representation of the URI similar
     * to PHP parse_url function result
     *
     * @param array{user? : ?string, pass? : ?string} $components
     */
    public static function fromComponents(array $components): self
    {
        $components += ['user' => null, 'pass' => null];

        return match (null) {
            $components['user'] => new self(null),
            default => new self($components['user'], $components['pass']),
        };
    }

    /**
     * Creates a new instance from an encoded string.
     */
    public static function new(Stringable|string|null $value = null): self
    {
        if ($value instanceof UriComponentInterface) {
            $value = $value->value();
        }

        if (null === $value) {
            return new self(null);
        }

        $value = (string) $value;

        [$user, $pass] = explode(':', $value, 2) + [1 => null];

        return new self(Encoder::decodeAll($user), Encoder::decodeAll($pass));
    }

    /**
     * Create a new instance from a string or a stringable structure or returns null on failure.
     */
    public static function tryNew(Stringable|string|null $uri = null): ?self
    {
        try {
            return self::new($uri);
        } catch (UriException) {
            return null;
        }
    }

    public function value(): ?string
    {
        return match (true) {
            null === $this->password => $this->getUsername(),
            default => $this->getUsername().':'.$this->getPassword(),
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
        return $this->value().(null === $this->username ? '' : '@');
    }

    public function getUser(): ?string
    {
        return $this->username;
    }

    public function getPass(): ?string
    {
        return $this->password;
    }

    public function getUsername(): ?string
    {
        return Encoder::encodeUser($this->username);
    }

    public function getPassword(): ?string
    {
        return Encoder::encodePassword($this->password);
    }

    /**
     * @return array{user: ?string, pass: ?string}
     */
    public function components(): array
    {
        return [
            'user' => $this->username,
            'pass' => $this->password,
        ];
    }

    public function withUser(Stringable|string|null $username): self
    {
        $username = $this->validateComponent($username);
        if ($this->username === $username) {
            return $this;
        }

        return new self($username, $this->password);
    }

    public function withPass(#[SensitiveParameter] Stringable|string|null $password): self
    {
        $password = $this->validateComponent($password);
        if ($password === $this->password) {
            return $this;
        }

        return new self($this->username, $password);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see UserInfo::fromUri()
     *
     * @codeCoverageIgnore
     *
     * Create a new instance from a URI object.
     */
    #[Deprecated(message:'use League\Uri\Components\UserInfo::fromUri() instead', since:'league/uri-components:7.0.0')]
    public static function createFromUri(Rfc3986Uri|WhatWgUrl|Psr7UriInterface|UriInterface $uri): self
    {
        return self::fromUri($uri);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see UserInfo::fromAuthority()
     *
     * @codeCoverageIgnore
     *
     * Create a new instance from an Authority object.
     */
    #[Deprecated(message:'use League\Uri\Components\UserInfo::fromAuthority() instead', since:'league/uri-components:7.0.0')]
    public static function createFromAuthority(AuthorityInterface|Stringable|string $authority): self
    {
        return self::fromAuthority($authority);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see UserInfo::new()
     *
     * @codeCoverageIgnore
     *
     * Creates a new instance from an encoded string.
     */
    #[Deprecated(message:'use League\Uri\Components\UserInfo::new() instead', since:'league/uri-components:7.0.0')]
    public static function createFromString(Stringable|string $userInfo): self
    {
        return self::new($userInfo);
    }
}

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
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriInterface;
use League\Uri\Contracts\UserInfoInterface;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Uri;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use SensitiveParameter;
use Stringable;
use function explode;
use function preg_replace_callback;
use function rawurldecode;

final class UserInfo extends Component implements UserInfoInterface
{
    private const REGEXP_USER_ENCODING = '/[^A-Za-z0-9_\-.~!$&\'()*+,;=%]+|%(?![A-Fa-f0-9]{2})/x';
    private const REGEXP_PASS_ENCODING = '/[^A-Za-z0-9_\-.~!$&\'()*+,;=%:]+|%(?![A-Fa-f0-9]{2})/x';
    private const REGEXP_ENCODED_CHAR = ',%[A-Fa-f0-9]{2},';

    private readonly ?string $username;
    private readonly ?string $password;

    /**
     * New instance.
     */
    public function __construct(
        Stringable|string|null $username,
        #[SensitiveParameter] Stringable|string|null $password = null
    ) {
        $this->username = $this->validateComponent($username);
        $password = $this->validateComponent($password);
        if (null === $this->username && null !== $password) {
            throw new SyntaxError('It is not possible to associated a password to an undefined user.');
        }

        $this->password = $password;
    }

    /**
     * Create a new instance from a URI object.
     */
    public static function fromUri(Stringable|string $uri): self
    {
        if ($uri instanceof UriInterface) {
            return self::new($uri->getUserInfo());
        }

        if ($uri instanceof Psr7UriInterface) {
            $component = $uri->getUserInfo();
            if ('' === $component) {
                return self::new();
            }

            return self::new($component);
        }

        return self::new(Uri::new($uri)->getUserInfo());
    }

    /**
     * Create a new instance from an Authority object.
     */
    public static function fromAuthority(Stringable|string $authority): self
    {
        if (!$authority instanceof AuthorityInterface) {
            $authority = Authority::new($authority);
        }

        return self::new($authority->getUserInfo());
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
        if (null !== $user) {
            $user = self::decode($user);
        }

        if (null !== $pass) {
            $pass = self::decode($pass);
        }

        return new self($user, $pass);
    }

    /**
     * Decodes an encoded string.
     */
    private static function decode(string $str): ?string
    {
        return preg_replace_callback(
            self::REGEXP_ENCODED_CHAR,
            static fn (array $matches): string => rawurldecode($matches[0]),
            $str
        );
    }

    public function value(): ?string
    {
        if (null === $this->username) {
            return null;
        }

        $userInfo = $this->encodeComponent($this->username, self::REGEXP_USER_ENCODING);
        if (null === $this->password) {
            return $userInfo;
        }

        return $userInfo.':'.$this->encodeComponent($this->password, self::REGEXP_PASS_ENCODING);
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

    public function withUser(Stringable|string|null $username): self
    {
        $username = $this->validateComponent($username);

        return match (true) {
            $username === $this->username => $this,
            default => new self($username, $this->password),
        };
    }

    public function withPass(#[SensitiveParameter] Stringable|string|null $password): self
    {
        $password = $this->validateComponent($password);

        return match (true) {
            $password === $this->password => $this,
            null === $this->username => throw new SyntaxError('It is not possible to associated a password to an undefined user.'),
            default => new self($this->username, $password),
        };
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
    public static function createFromUri(Psr7UriInterface|UriInterface $uri): self
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
    public static function createFromString(Stringable|string $userInfo): self
    {
        return self::new($userInfo);
    }
}

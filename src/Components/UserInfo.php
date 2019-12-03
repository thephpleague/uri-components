<?php

/**
 * League.Uri (http://uri.thephpleague.com/components)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.2
 * @link       https://github.com/thephpleague/uri-components
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
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use TypeError;
use function explode;
use function preg_replace_callback;
use function rawurldecode;
use function sprintf;

final class UserInfo extends Component implements UserInfoInterface
{
    private const REGEXP_USER_ENCODING = '/(?:[^A-Za-z0-9_\-\.~\!\$&\'\(\)\*\+,;\=%]+|%(?![A-Fa-f0-9]{2}))/x';

    private const REGEXP_PASS_ENCODING = '/(?:[^A-Za-z0-9_\-\.~\!\$&\'\(\)\*\+,;\=%\:]+|%(?![A-Fa-f0-9]{2}))/x';

    private const REGEXP_ENCODED_CHAR = ',%[A-Fa-f0-9]{2},';

    /**
     * @var string|null
     */
    private $user;

    /**
     * @var string|null
     */
    private $pass;

    /**
     * New instance.
     *
     * @param mixed|null $user
     * @param mixed|null $pass
     */
    public function __construct($user = null, $pass = null)
    {
        $this->user = $this->validateComponent($user);
        $this->pass = $this->validateComponent($pass);
        if (null === $this->user || '' === $this->user) {
            $this->pass = null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['user'], $properties['pass']);
    }

    /**
     * Create a new instance from a URI object.
     *
     * @param mixed $uri an URI object
     *
     * @throws TypeError If the URI object is not supported
     */
    public static function createFromUri($uri): self
    {
        if ($uri instanceof UriInterface) {
            $component = $uri->getUserInfo();
            if (null === $component) {
                return new self();
            }

            return self::createFromComponent($component);
        }

        if (!$uri instanceof Psr7UriInterface) {
            throw new TypeError(sprintf('The object must implement the `%s` or the `%s` interface.', Psr7UriInterface::class, UriInterface::class));
        }

        $component = $uri->getUserInfo();
        if ('' === $component) {
            return new self();
        }

        return self::createFromComponent($component);
    }

    /**
     * Create a new instance from a Authority object.
     */
    public static function createFromAuthority(AuthorityInterface $authority): self
    {
        $userInfo = $authority->getUserInfo();
        if (null === $userInfo) {
            return new self();
        }

        return self::createFromComponent($userInfo);
    }

    /**
     * Creates a new instance from a encoded string.
     */
    private static function createFromComponent(string $userInfo): self
    {
        [$user, $pass] = explode(':', $userInfo, 2) + [1 => null];
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
        $decoder = static function (array $matches): string {
            return rawurldecode($matches[0]);
        };

        return preg_replace_callback(self::REGEXP_ENCODED_CHAR, $decoder, $str);
    }

    /**
     * {@inheritDoc}
     */
    public function getContent(): ?string
    {
        if (null === $this->user) {
            return null;
        }

        $userInfo = $this->encodeComponent($this->user, self::REGEXP_USER_ENCODING);
        if (null === $this->pass) {
            return $userInfo;
        }

        return $userInfo.':'.$this->encodeComponent($this->pass, self::REGEXP_PASS_ENCODING);
    }

    /**
     * {@inheritDoc}
     */
    public function getUriComponent(): string
    {
        return $this->getContent().(null === $this->user ? '' : '@');
    }

    /**
     * {@inheritDoc}
     */
    public function getUser(): ?string
    {
        return $this->user;
    }

    /**
     * {@inheritDoc}
     */
    public function getPass(): ?string
    {
        return $this->pass;
    }

    /**
     * {@inheritDoc}
     */
    public function withContent($content): UriComponentInterface
    {
        $content = self::filterComponent($content);
        if ($content === $this->getContent()) {
            return $this;
        }

        if (null === $content) {
            return new self();
        }

        return self::createFromComponent($content);
    }

    /**
     * {@inheritDoc}
     */
    public function withUserInfo($user, $pass = null): UserInfoInterface
    {
        $user = $this->validateComponent($user);
        $pass = $this->validateComponent($pass);
        if (null === $user || '' === $user) {
            $pass = null;
        }

        if ($user === $this->user && $pass === $this->pass) {
            return $this;
        }

        $clone = clone $this;
        $clone->user = $user;
        $clone->pass = $pass;

        return $clone;
    }
}

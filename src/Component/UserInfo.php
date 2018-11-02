<?php

/**
 * League.Uri (http://uri.thephpleague.com/components).
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.0
 * @link       https://github.com/thephpleague/uri-schemes
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\Component;

use function explode;

final class UserInfo extends Component
{
    private const REGEXP_USERINFO_ENCODING = '/(?:[^A-Za-z0-9_\-\.~\!\$&\'\(\)\*\+,;\=%]+|%(?![A-Fa-f0-9]{2}))/x';

    /**
     * @var string|null
     */
    private $user;

    /**
     * @var string|null
     */
    private $pass;

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['user'], $properties['pass']);
    }

    /**
     * New instance.
     *
     * @param null|mixed $user
     * @param null|mixed $pass
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
     * {@inheritdoc}
     */
    public function getContent(): ?string
    {
        if (null === $this->user) {
            return null;
        }

        $userInfo = $this->encodeComponent($this->user, self::RFC3986_ENCODING, self::REGEXP_USERINFO_ENCODING);
        if (null === $this->pass) {
            return $userInfo;
        }

        return $userInfo.':'.$this->encodeComponent($this->pass, self::RFC3986_ENCODING, self::REGEXP_USERINFO_ENCODING);
    }

    /**
     * Returns the decoded component.
     */
    public function decoded(): ?string
    {
        if (null === $this->user) {
            return null;
        }

        $userInfo = $this->getUser();
        if (null === $this->pass) {
            return $userInfo;
        }

        return $userInfo.':'.$this->getPass();
    }

    /**
     * Retrieve the user component of the URI User Info part.
     */
    public function getUser(): ?string
    {
        return $this->encodeComponent($this->user, self::NO_ENCODING, self::REGEXP_USERINFO_ENCODING);
    }

    /**
     * Retrieve the pass component of the URI User Info part.
     */
    public function getPass(): ?string
    {
        return $this->encodeComponent($this->pass, self::NO_ENCODING, self::REGEXP_USERINFO_ENCODING);
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($content): self
    {
        $content = $this->filterComponent($content);
        if ($content === $this->getContent()) {
            return $this;
        }

        if (null === $content) {
            return new self();
        }

        return new self(...explode(':', $content, 2) + [1 => null]);
    }

    /**
     * Return an instance with the specified user.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified user.
     *
     * An empty user is equivalent to removing the user information.
     * @param null|mixed $pass
     */
    public function withUserInfo($user, $pass = null): self
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

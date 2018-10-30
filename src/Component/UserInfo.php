<?php

/**
 * League.Uri (http://uri.thephpleague.com).
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

final class UserInfo extends Component
{
    /**
     * @internal
     *
     * matches invalid URI chars + URI delimiters and the : character
     */
    const REGEXP_USER_RFC3987_ENCODING = '/[\x00-\x1f\x7f\/#\?\:@]/';

    /**
     * @internal
     *
     * matches invalid URI chars + URI delimiters without the : character
     */
    const REGEXP_PASS_RFC3987_ENCODING = '/[\x00-\x1f\x7f\/#\?@]/';

    /**
     * @internal
     */
    const REGEXP_USERINFO_ENCODING = '/(?:[^A-Za-z0-9_\-\.~\!\$&\'\(\)\*\+,;\=%]+|%(?![A-Fa-f0-9]{2}))/x';

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
    public static function __set_state(array $properties)
    {
        return new static($properties['user'], $properties['pass']);
    }

    /**
     * Create a new instance of UserInfo.
     *
     * @param mixed $user
     * @param mixed $pass
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
    public function getContent()
    {
        if (null === $this->user) {
            return null;
        }

        $userInfo = $this->getUser(self::RFC3986_ENCODING);
        if (null === $this->pass) {
            return $userInfo;
        }

        return $userInfo.':'.$this->getPass(self::RFC3986_ENCODING);
    }

    /**
     * Returns the decoded component.
     *
     * @return null|string
     */
    public function decoded()
    {
        if (null === $this->user) {
            return null;
        }

        $userInfo = $this->getUser(self::NO_ENCODING);
        if (null === $this->pass) {
            return $userInfo;
        }

        return $userInfo.':'.$this->getPass(self::NO_ENCODING);
    }

    /**
     * Retrieve the user component of the URI User Info part.
     *
     * @param int $enc_type
     *
     * @return string|null
     */
    public function getUser(int $enc_type = self::RFC3986_ENCODING)
    {
        return $this->encodeComponent($this->user, $enc_type, self::REGEXP_USERINFO_ENCODING, self::REGEXP_USER_RFC3987_ENCODING);
    }

    /**
     * Retrieve the pass component of the URI User Info part.
     *
     * @param int $enc_type
     *
     * @return string|null
     */
    public function getPass(int $enc_type = self::RFC3986_ENCODING)
    {
        return $this->encodeComponent($this->pass, $enc_type, self::REGEXP_USERINFO_ENCODING, self::REGEXP_PASS_RFC3987_ENCODING);
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($content)
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
     *
     * @param mixed $user The user to use with the new instance.
     * @param mixed $pass The pass to use with the new instance.
     *
     * @return self
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

<?php
/**
 * League.Uri (http://uri.thephpleague.com).
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.0
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace League\Uri\Components;

/**
 * Value object representing the UserInfo part of an URI.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 * @see        https://tools.ietf.org/html/rfc3986#section-3.2.1
 *
 */
final class UserInfo extends AbstractComponent
{
    /**
     * User user component.
     *
     * @var string|null
     */
    private $user;

    /**
     * Pass URI component.
     *
     * @var string|null
     */
    private $pass;

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
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
    public function __toString()
    {
        return (string) $this->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(int $enc_type = self::RFC3986_ENCODING)
    {
        $this->filterEncoding($enc_type);

        if (null === $this->user) {
            return null;
        }

        $userInfo = $this->getUser($enc_type);
        if (null === $this->pass) {
            return $userInfo;
        }

        return $userInfo.':'.$this->getPass($enc_type);
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
    {
        return [
            'user' => $this->getUser(),
            'pass' => $this->getPass(),
        ];
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
        $this->filterEncoding($enc_type);
        if (null === $this->user || self::NO_ENCODING == $enc_type || !preg_match('/[^A-Za-z0-9_\-\.~]/', $this->user)) {
            return $this->user;
        }

        if ($enc_type == self::RFC3987_ENCODING) {
            static $pattern = '/[\x00-\x1f\x7f\/#\?\:@]/';
            return preg_replace_callback($pattern, [$this, 'encodeMatches'], $this->user) ?? $this->user;
        }

        static $regexp = '/(?:[^A-Za-z0-9_\-\.~\!\$&\'\(\)\*\+,;\=%]+|%(?![A-Fa-f0-9]{2}))/x';
        $content = preg_replace_callback($regexp, [$this, 'encodeMatches'], $this->user) ?? rawurlencode($this->user);
        if (self::RFC3986_ENCODING === $enc_type) {
            return $content;
        }

        return str_replace(['+', '~'], ['%2B', '%7E'], $content);
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
        $this->filterEncoding($enc_type);
        if (null === $this->pass || self::NO_ENCODING == $enc_type || !preg_match('/[^A-Za-z0-9_\-\.~]/', $this->pass)) {
            return $this->pass;
        }

        if ($enc_type == self::RFC3987_ENCODING) {
            static $pattern = '/[\x00-\x1f\x7f\/#\?@]/';
            return preg_replace_callback($pattern, [$this, 'encodeMatches'], $this->pass) ?? $this->pass;
        }

        static $regexp = '/(?:[^A-Za-z0-9_\-\.~\!\$&\'\(\)\*\+,;\=%]+|%(?![A-Fa-f0-9]{2}))/x';
        $content = preg_replace_callback($regexp, [$this, 'encodeMatches'], $this->pass) ?? rawurlencode($this->pass);
        if (self::RFC3986_ENCODING === $enc_type) {
            return $content;
        }

        return str_replace(['+', '~'], ['%2B', '%7E'], $content);
    }

    /**
     * {@inheritdoc}
     */
    public function getUriComponent(): string
    {
        if (null === $this->user || '' === $this->user) {
            return '';
        }

        return $this->getContent().'@';
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($content): ComponentInterface
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
     * @return static
     */
    public function withUserInfo($user, $pass = null): self
    {
        $user = $this->validateComponent($user);
        $pass = $this->validateComponent($pass);
        if (null === $user || '' === $user) {
            $pass = null;
        }

        if ($user == $this->user && $pass == $this->pass) {
            return $this;
        }

        $clone = clone $this;
        $clone->user = $user;
        $clone->pass = $pass;

        return $clone;
    }
}

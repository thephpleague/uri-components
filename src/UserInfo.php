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

use TypeError;

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
final class UserInfo implements ComponentInterface
{
    /**
     * @internal
     */
    const ENCODING_LIST = [
        self::RFC1738_ENCODING => 1,
        self::RFC3986_ENCODING => 1,
        self::RFC3987_ENCODING => 1,
        self::NO_ENCODING => 1,
    ];

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
        $this->user = $this->filterPart($user);
        $this->pass = $this->filterPart($pass);
        if (null === $this->user || '' === $this->user) {
            $this->pass = null;
        }
    }

    /**
     * Filter the URI password component.
     *
     * @param mixed $str
     *
     * @throws Exception If the content is invalid
     *
     * @return string|null
     */
    private function filterPart($str = null)
    {
        if ($str instanceof ComponentInterface) {
            $str = $str->getContent();
        }

        if (null === $str) {
            return $str;
        }

        if (!is_scalar($str) && !method_exists($str, '__toString')) {
            throw new TypeError(sprintf('Expected userinfo to be stringable or null; received %s', gettype($str)));
        }

        static $pattern = '/[\x00-\x1f\x7f]/';
        if (preg_match($pattern, $str)) {
            throw new Exception(sprintf('Invalid string: %s', $str));
        }

        static $encoded_pattern = ',%[A-Fa-f0-9]{2},';

        return preg_replace_callback($encoded_pattern, [$this, 'decodeMatches'], $str);
    }

    /**
     * Decodes Matches sequence.
     *
     * @param array $matches
     *
     * @return string
     */
    private function decodeMatches(array $matches): string
    {
        static $regexp = ',%2[D|E]|3[0-9]|4[1-9|A-F]|5[0-9|A|F]|6[1-9|A-F]|7[0-9|E],i';
        if (preg_match($regexp, $matches[0])) {
            return strtoupper($matches[0]);
        }

        return rawurldecode($matches[0]);
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
        if (!isset(self::ENCODING_LIST[$enc_type])) {
            throw new Exception(sprintf('Unsupported or Unknown Encoding: %s', $enc_type));
        }

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
        if (!isset(self::ENCODING_LIST[$enc_type])) {
            throw new Exception(sprintf('Unsupported or Unknown Encoding: %s', $enc_type));
        }

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
     * Encode Matches sequence.
     *
     * @param array $matches
     *
     * @return string
     */
    private function encodeMatches(array $matches): string
    {
        return rawurlencode($matches[0]);
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
        if (!isset(self::ENCODING_LIST[$enc_type])) {
            throw new Exception(sprintf('Unsupported or Unknown Encoding: %s', $enc_type));
        }

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
        if ($content instanceof ComponentInterface) {
            $content = $content->getContent();
        }

        if ($content === $this->getContent()) {
            return $this;
        }

        if (null === $content) {
            return new self();
        }

        if (is_scalar($content) || method_exists($content, '__toString')) {
            return new self(...explode(':', (string) $content, 2) + [1 => null]);
        }

        throw new Exception(sprintf('Expected userinfo to be stringable or null; received %s', gettype($content)));
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
        $user = $this->filterPart($user);
        $pass = $this->filterPart($pass);
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

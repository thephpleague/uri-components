<?php

/**
 * League.Uri (https://uri.thephpleague.com/components/).
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.8.2
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
class UserInfo implements ComponentInterface
{
    use ComponentTrait;

    /**
     * User user component.
     *
     * @var string|null
     */
    protected $user;

    /**
     * Pass URI component.
     *
     * @var string|null
     */
    protected $pass;

    /**
     * Create a new instance of UserInfo.
     *
     */
    public function __construct(string $user = null, string $pass = null)
    {
        $this->user = $this->filterUser($user);
        if ('' != $this->user) {
            $this->pass = $this->filterPass($pass);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
    {
        return [
            'component' => $this->getContent(),
            'user' => $this->getUser(),
            'pass' => $this->getPass(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function isNull(): bool
    {
        return null === $this->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        return '' == $this->getContent();
    }

    /**
     * Filter the URI user component.
     *
     *
     * @throws Exception If the content is invalid
     *
     * @return string|null
     */
    protected function filterUser(string $str = null)
    {
        if (null === $str) {
            return $str;
        }

        $str = $this->validateString($str);

        return $this->decodeComponent($str);
    }

    /**
     * Filter the URI password component.
     *
     *
     * @throws Exception If the content is invalid
     *
     * @return string|null
     */
    protected function filterPass(string $str = null)
    {
        if (null === $str) {
            return $str;
        }

        $str = $this->validateString($str);

        return $this->decodeComponent($str);
    }

    /**
     * Retrieve the user component of the URI User Info part.
     *
     *
     * @return string|null
     */
    public function getUser(int $enc_type = self::RFC3986_ENCODING)
    {
        $this->assertValidEncoding($enc_type);
        if (null === $this->user || '' === $this->user || self::NO_ENCODING == $enc_type) {
            return $this->user;
        }

        if ($enc_type == self::RFC3987_ENCODING) {
            $pattern = array_merge(str_split(self::$invalid_uri_chars), ['/', '#', '?', ':', '@']);

            return str_replace($pattern, array_map('rawurlencode', $pattern), $this->user);
        }

        $regexp = '/(?:[^'.static::$unreserved_chars.static::$subdelim_chars.']+|%(?!'.static::$encoded_chars.'))/x';

        if (self::RFC1738_ENCODING == $enc_type) {
            return $this->toRFC1738($this->encode($this->user, $regexp));
        }

        return $this->encode($this->user, $regexp);
    }

    /**
     * Retrieve the pass component of the URI User Info part.
     *
     *
     * @return string|null
     */
    public function getPass(int $enc_type = self::RFC3986_ENCODING)
    {
        $this->assertValidEncoding($enc_type);
        if (null === $this->pass || '' === $this->pass || self::NO_ENCODING == $enc_type) {
            return $this->pass;
        }

        if ($enc_type == self::RFC3987_ENCODING) {
            $pattern = array_merge(str_split(self::$invalid_uri_chars), ['/', '#', '?', '@']);

            return str_replace($pattern, array_map('rawurlencode', $pattern), $this->pass);
        }

        $regexp = '/(?:[^'.static::$unreserved_chars.static::$subdelim_chars.']+|%(?!'.static::$encoded_chars.'))/x';

        if (self::RFC1738_ENCODING == $enc_type) {
            return $this->toRFC1738($this->encode($this->pass, $regexp));
        }

        return $this->encode($this->pass, $regexp);
    }

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return new static($properties['user'], $properties['pass']);
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(int $enc_type = self::RFC3986_ENCODING)
    {
        $this->assertValidEncoding($enc_type);
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
    public function __toString()
    {
        return (string) $this->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function getUriComponent(): string
    {
        $component = (string) $this->getContent();
        if ('' == $component) {
            return $component;
        }

        return $component.'@';
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($content): ComponentInterface
    {
        if (null !== $content) {
            $content = $this->validateString($content);
        }

        if ($content === $this->getContent()) {
            return $this;
        }

        $res = explode(':', $content, 2);

        return $this->withUserInfo(array_shift($res), array_shift($res));
    }

    /**
     * Return an instance with the specified user.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified user.
     *
     * An empty user is equivalent to removing the user information.
     *
     * @param string      $user The user to use with the new instance.
     * @param string|null $pass The pass to use with the new instance.
     *
     * @return static
     */
    public function withUserInfo(string $user, string $pass = null): self
    {
        $user = $this->filterUser($this->validateString($user));
        $pass = $this->filterPass($pass);
        if ('' == $user) {
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

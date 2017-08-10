<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.0.4
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
     * User user component
     *
     * @var string|null
     */
    protected $user;

    /**
     * Pass URI component
     *
     * @var string|null
     */
    protected $pass;

    /**
     * Create a new instance of UserInfo
     *
     * @param string|null $user
     * @param string|null $pass
     */
    public function __construct(string $user = null, string $pass = null)
    {
        $this->user = $this->filterUser($user);
        if ('' != $this->user) {
            $this->pass = $this->filterPass($pass);
        }
    }

    /**
     * Called by var_dump() when dumping The object
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            'component' => $this->getContent(),
            'user' => $this->getUser(),
            'pass' => $this->getPass(),
        ];
    }

    /**
     * Filter the URI user component
     *
     * @param string|null $str
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
        if (strlen($str) === strcspn($str, '/:@?#')) {
            return $this->decodeComponent($str);
        }

        throw new Exception(sprintf('The encoded user string `%s` contains invalid characters `/:@?#`', $str));
    }

    /**
     * Filter the URI password component
     *
     * @param string|null $str
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
        if (strlen($str) === strcspn($str, '/@?#')) {
            return $this->decodeComponent($str);
        }

        throw new Exception(sprintf(
            'The encoded pass string `%s` contains invalid characters `/@?#`',
            $str
        ));
    }

    /**
     * Retrieve the user component of the URI User Info part
     *
     * @param int $enc_type
     *
     * @return string|null
     */
    public function getUser(int $enc_type = ComponentInterface::RFC3986_ENCODING)
    {
        $this->assertValidEncoding($enc_type);
        if ('' == $this->user || ComponentInterface::NO_ENCODING == $enc_type) {
            return $this->user;
        }

        if ($enc_type == ComponentInterface::RFC3987_ENCODING) {
            $pattern = array_merge(str_split(self::$invalid_uri_chars), ['/', '#', '?', ':', '@']);

            return str_replace($pattern, array_map('rawurlencode', $pattern), $this->user);
        }

        $regexp = '/(?:[^'.static::$unreserved_chars.static::$subdelim_chars.']+|%(?!'.static::$encoded_chars.'))/x';

        if (ComponentInterface::RFC1738_ENCODING == $enc_type) {
            return $this->toRFC1738($this->encode($this->user, $regexp));
        }

        return $this->encode($this->user, $regexp);
    }

    /**
     * Retrieve the pass component of the URI User Info part
     *
     * @param int $enc_type
     *
     * @return string|null
     */
    public function getPass(int $enc_type = ComponentInterface::RFC3986_ENCODING)
    {
        $this->assertValidEncoding($enc_type);
        if ('' == $this->pass || ComponentInterface::NO_ENCODING == $enc_type) {
            return $this->pass;
        }

        if ($enc_type == ComponentInterface::RFC3987_ENCODING) {
            $pattern = array_merge(str_split(self::$invalid_uri_chars), ['/', '#', '?', '@']);

            return str_replace($pattern, array_map('rawurlencode', $pattern), $this->pass);
        }

        $regexp = '/(?:[^'.static::$unreserved_chars.static::$subdelim_chars.']+|%(?!'.static::$encoded_chars.'))/x';

        if (ComponentInterface::RFC1738_ENCODING == $enc_type) {
            return $this->toRFC1738($this->encode($this->pass, $regexp));
        }

        return $this->encode($this->pass, $regexp);
    }

    /**
     * This static method is called for classes exported by var_export()
     *
     * @param array $properties
     *
     * @return static
     */
    public static function __set_state(array $properties): self
    {
        return new static($properties['user'], $properties['pass']);
    }

    /**
     * Returns the instance content encoded in RFC3986 or RFC3987.
     *
     * If the instance is defined, the value returned MUST be percent-encoded,
     * but MUST NOT double-encode any characters depending on the encoding type selected.
     *
     * To determine what characters to encode, please refer to RFC 3986, Sections 2 and 3.
     * or RFC 3987 Section 3.
     *
     * By default the content is encoded according to RFC3986
     *
     * If the instance is not defined null is returned
     *
     * @param int $enc_type
     *
     * @return string|null
     */
    public function getContent(int $enc_type = ComponentInterface::RFC3986_ENCODING)
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
     * Returns the instance string representation; If the
     * instance is not defined an empty string is returned
     *
     * @return string
     */
    public function __toString(): string
    {
        return (string) $this->getContent();
    }

    /**
     * Returns the instance string representation
     * with its optional URI delimiters
     *
     * @return string
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
     * Create a new instance from a string
     *
     * @param string|null $content
     *
     * @return ComponentInterface
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

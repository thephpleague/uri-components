<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @copyright  2016 Ignace Nyamagana Butera
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.0.0
 * @link       https://github.com/thephpleague/uri-components
 */
namespace League\Uri\Components;

use League\Uri\Interfaces\Component as UriComponent;

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
class UserInfo implements UriComponent
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
    public function __construct($user = null, $pass = null)
    {
        $this->user = $this->filterUser($user);
        if ('' != $this->user) {
            $this->pass = $this->filterPass($pass);
        }
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
    protected function filterUser($str)
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
    protected function filterPass($str)
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
     * @return string|null
     */
    public function getUser($enc_type = self::RFC3986_ENCODING)
    {
        $this->assertValidEncoding($enc_type);
        if ('' == $this->user || self::NO_ENCODING == $enc_type) {
            return $this->user;
        }

        if ($enc_type == self::RFC3987_ENCODING) {
            $pattern = array_merge(str_split(self::$invalidUriChars), ['/', '#', '?', ':', '@']);

            return str_replace($pattern, array_map('rawurlencode', $pattern), $this->user);
        }

        $regexp = '/(?:[^'.static::$unreservedChars.static::$subdelimChars.']+|%(?!'.static::$encodedChars.'))/x';

        return $this->encode($this->user, $regexp);
    }

    /**
     * Retrieve the pass component of the URI User Info part
     *
     * @return string
     */
    public function getPass($enc_type = self::RFC3986_ENCODING)
    {
        $this->assertValidEncoding($enc_type);
        if ('' == $this->pass || self::NO_ENCODING == $enc_type) {
            return $this->pass;
        }

        if ($enc_type == self::RFC3987_ENCODING) {
            $pattern = array_merge(str_split(self::$invalidUriChars), ['/', '#', '?', '@']);

            return str_replace($pattern, array_map('rawurlencode', $pattern),  $this->pass);
        }

        $regexp = '/(?:[^'.static::$unreservedChars.static::$subdelimChars.']+|%(?!'.static::$encodedChars.'))/x';

        return $this->encode($this->pass, $regexp);
    }

    /**
     * @inheritdoc
     */
    public static function __set_state(array $properties)
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
    public function getContent($enc_type = self::RFC3986_ENCODING)
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
    public function __toString()
    {
        return (string) $this->getContent();
    }

    /**
     * Returns the instance string representation
     * with its optional URI delimiters
     *
     * @return string
     */
    public function getUriComponent()
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
     * @return static
     */
    public function withContent($content)
    {
        if ($content === $this->getContent()) {
            return $this;
        }

        if (null !== $content && !is_string($content)) {
            throw new Exception(sprintf(
                'Expected data to be a string or NULL; received "%s"',
                gettype($content)
            ));
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
     * @param string $user The user to use with the new instance.
     *
     * @return static
     */
    public function withUserInfo($user, $pass = null)
    {
        $user = $this->filterUser($this->validateString($user));
        $pass = $this->filterPass($pass);
        if (in_array($user, [null, ''], true)) {
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

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
namespace League\Uri\Components\Traits;

use League\Uri\Components\Exception;
use League\Uri\Interfaces\Component as UriComponent;

/**
 * Value object representing a URI path component.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
trait PathInfo
{
    /**
     * Dot Segment pattern
     *
     * @var array
     */
    protected static $dotSegments = ['.' => 1, '..' => 1];

    /**
     * Filter the encoded path string
     *
     * @param string $path the encoded path
     *
     * @throws Exception If the encoded path is invalid
     *
     * @return string
     */
    protected function filterEncodedPath($path)
    {
        if (strlen($path) === strcspn($path, '?#')) {
            return $path;
        }

        throw new Exception(sprintf('The encoded path `%s` contains invalid characters', $path));
    }

    /**
     * Returns the instance string representation; If the
     * instance is not defined an empty string is returned
     *
     * @return string
     */
    abstract public function __toString();

    /**
     * Returns an instance with the specified string
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified data
     *
     * @param string $value
     *
     * @return static
     */
    abstract public function withContent($value);

    /**
     * @inheritdoc
     */
    public function __debugInfo()
    {
        return ['path' => $this->getContent()];
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
    public function getContent($enc_type = UriComponent::RFC3986_ENCODING)
    {
        $this->assertValidEncoding($enc_type);

        if ($enc_type == UriComponent::RFC3987_ENCODING) {
            $pattern = str_split(self::$invalidUriChars);
            $pattern[] = '#';
            $pattern[] = '?';

            return str_replace($pattern, array_map('rawurlencode', $pattern), $this->getDecoded());
        }

        if ($enc_type == UriComponent::RFC3986_ENCODING) {
            return $this->encodePath($this->getDecoded());
        }

        return $this->getDecoded();
    }

    /**
     * Validate the encoding type value
     *
     * @param int $enc_type
     *
     * @throws Exception If the encoding type is invalid
     */
    abstract protected function assertValidEncoding($enc_type);

    /**
     * Encode a path string according to RFC3986
     *
     * @param string $str can be a string or an array
     *
     * @return string The same type as the input parameter
     */
    abstract protected function encodePath($str);

    /**
     * Return the decoded string representation of the component
     *
     * @return string
     */
    abstract protected function getDecoded();

    /**
     * Returns an instance without dot segments
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component normalized by removing
     * the dot segment.
     *
     * @return static
     */
    public function withoutDotSegments()
    {
        $current = $this->__toString();
        if (false === strpos($current, '.')) {
            return $this;
        }

        $input = explode('/', $current);
        $new = implode('/', array_reduce($input, [$this, 'filterDotSegments'], []));
        if (isset(static::$dotSegments[end($input)])) {
            $new .= '/';
        }

        return $this->withContent($new);
    }

    /**
     * Filter Dot segment according to RFC3986
     *
     * @see http://tools.ietf.org/html/rfc3986#section-5.2.4
     *
     * @param array  $carry   Path segments
     * @param string $segment a path segment
     *
     * @return array
     */
    protected function filterDotSegments(array $carry, $segment)
    {
        if ('..' === $segment) {
            array_pop($carry);

            return $carry;
        }

        if (!isset(static::$dotSegments[$segment])) {
            $carry[] = $segment;
        }

        return $carry;
    }

    /**
     * Returns an instance without duplicate delimiters
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component normalized by removing
     * multiple consecutive empty segment
     *
     * @return static
     */
    public function withoutEmptySegments()
    {
        return $this->withContent(preg_replace(',/+,', '/', $this->__toString()));
    }

    /**
     * Returns whether or not the path has a trailing delimiter
     *
     * @return bool
     */
    public function hasTrailingSlash()
    {
        $path = $this->__toString();

        return '' !== $path && '/' === mb_substr($path, -1, 1, 'UTF-8');
    }

    /**
     * Returns an instance with a trailing slash
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component with a trailing slash
     *
     *
     * @return static
     */
    public function withTrailingSlash()
    {
        return $this->hasTrailingSlash() ? $this : $this->withContent($this->__toString().'/');
    }

    /**
     * Returns an instance without a trailing slash
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component without a trailing slash
     *
     * @return static
     */
    public function withoutTrailingSlash()
    {
        return !$this->hasTrailingSlash() ? $this : $this->withContent(mb_substr($this, 0, -1, 'UTF-8'));
    }

    /**
     * Returns whether or not the path is absolute or relative
     *
     * @return bool
     */
    public function isAbsolute()
    {
        $path = $this->__toString();

        return '' !== $path && '/' === mb_substr($path, 0, 1, 'UTF-8');
    }

    /**
     * Returns whether or not the path is empty or not
     *
     * @return bool
     */
    public function isEmpty()
    {
        $path = $this->__toString();

        return '' === $path;
    }

    /**
     * Returns an instance with a leading slash
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component with a leading slash
     *
     *
     * @return static
     */
    public function withLeadingSlash()
    {
        return $this->isAbsolute() ? $this : $this->withContent('/'.$this->__toString());
    }

    /**
     * Returns an instance without a leading slash
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component without a leading slash
     *
     * @return static
     */
    public function withoutLeadingSlash()
    {
        return !$this->isAbsolute() ? $this : $this->withContent(mb_substr($this, 1, null, 'UTF-8'));
    }
}

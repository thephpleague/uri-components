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

namespace League\Uri\Components;

/**
 * Value object representing a URI path component.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 *
 * @internal used internally to add default Path component behaviour
 */
trait PathInfoTrait
{
    /**
     * Dot Segment pattern.
     *
     * @var array
     */
    protected static $dot_segments = ['.' => 1, '..' => 1];

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
    {
        return ['component' => $this->getContent()];
    }

    /**
     * Returns whether or not the component is defined.
     *
     */
    public function isNull(): bool
    {
        return null === $this->getContent();
    }

    /**
     * Returns whether or not the component is empty.
     *
     */
    public function isEmpty(): bool
    {
        return '' == $this->getContent();
    }

    /**
     * Returns whether or not the path is absolute or relative.
     *
     */
    public function isAbsolute(): bool
    {
        $path = $this->__toString();

        return '' !== $path && '/' === substr($path, 0, 1);
    }

    /**
     * {@inheritdoc}
     */
    abstract public function __toString();

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
     *
     * @return string|null
     */
    public function getContent(int $enc_type = EncodingInterface::RFC3986_ENCODING)
    {
        $this->assertValidEncoding($enc_type);

        if ($enc_type == EncodingInterface::RFC3987_ENCODING) {
            $pattern = str_split(self::$invalid_uri_chars);
            $pattern[] = '#';
            $pattern[] = '?';

            return str_replace($pattern, array_map('rawurlencode', $pattern), $this->getDecoded());
        }

        if ($enc_type == EncodingInterface::RFC3986_ENCODING) {
            return $this->encodePath($this->getDecoded());
        }

        if ($enc_type == EncodingInterface::RFC1738_ENCODING) {
            return $this->toRFC1738($this->encodePath($this->getDecoded()));
        }

        return $this->getDecoded();
    }

    /**
     * Validate the encoding type value.
     *
     *
     * @throws Exception If the encoding type is invalid
     */
    abstract protected function assertValidEncoding(int $enc_type);

    /**
     * Return the decoded string representation of the component.
     *
     */
    abstract protected function getDecoded(): string;

    /**
     * Encode a path string according to RFC3986.
     *
     * @param string $str can be a string or an array
     *
     * @return string The same type as the input parameter
     */
    abstract protected function encodePath(string $str): string;

    /**
     * Convert a RFC3986 encoded string into a RFC1738 string.
     *
     *
     */
    abstract protected function toRFC1738(string $str): string;

    /**
     * Returns an instance without dot segments.
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
        if (isset(static::$dot_segments[end($input)])) {
            $new .= '/';
        }

        return $this->withContent($new);
    }

    /**
     * Filter Dot segment according to RFC3986.
     *
     * @see http://tools.ietf.org/html/rfc3986#section-5.2.4
     *
     * @param array  $carry   Path segments
     * @param string $segment a path segment
     *
     */
    protected function filterDotSegments(array $carry, string $segment): array
    {
        if ('..' === $segment) {
            array_pop($carry);

            return $carry;
        }

        if (!isset(static::$dot_segments[$segment])) {
            $carry[] = $segment;
        }

        return $carry;
    }

    /**
     * Returns an instance with the specified string.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified data
     *
     * @param string $value
     *
     */
    abstract public function withContent($value): ComponentInterface;

    /**
     * Returns an instance without duplicate delimiters.
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
     * Returns whether or not the path has a trailing delimiter.
     *
     */
    public function hasTrailingSlash(): bool
    {
        $path = $this->__toString();

        return '' !== $path && '/' === substr($path, -1);
    }

    /**
     * Returns an instance with a trailing slash.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component with a trailing slash
     *
     * @throws Exception for transformations that would result in a invalid object.
     *
     * @return static
     */
    public function withTrailingSlash()
    {
        return $this->hasTrailingSlash() ? $this : $this->withContent($this->__toString().'/');
    }

    /**
     * Returns an instance without a trailing slash.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component without a trailing slash
     *
     * @throws Exception for transformations that would result in a invalid object.
     *
     * @return static
     */
    public function withoutTrailingSlash()
    {
        return !$this->hasTrailingSlash() ? $this : $this->withContent(substr($this->__toString(), 0, -1));
    }

    /**
     * Returns an instance with a leading slash.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component with a leading slash
     *
     * @throws Exception for transformations that would result in a invalid object.
     *
     * @return static
     */
    public function withLeadingSlash()
    {
        return $this->isAbsolute() ? $this : $this->withContent('/'.$this->__toString());
    }

    /**
     * Returns an instance without a leading slash.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component without a leading slash
     *
     * @throws Exception for transformations that would result in a invalid object.
     *
     * @return static
     */
    public function withoutLeadingSlash()
    {
        return !$this->isAbsolute() ? $this : $this->withContent(substr($this->__toString(), 1));
    }
}

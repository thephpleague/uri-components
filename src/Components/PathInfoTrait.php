<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.3.0

 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace League\Uri\Components;

/**
 * Value object representing a URI path component.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
trait PathInfoTrait
{
    /**
     * Dot Segment pattern
     *
     * @var array
     */
    protected static $dot_segments = ['.' => 1, '..' => 1];

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
     * @return ComponentInterface
     */
    public function withContent($value): ComponentInterface
    {
        if ($value === $this->getContent()) {
            return $this;
        }

        return new static($value);
    }

    /**
     * new instance
     *
     * @param string|null $data the component value
     */
    abstract public function __construct(string $data = null);

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
    {
        return ['component' => $this->getContent()];
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

        if ($enc_type == ComponentInterface::RFC3987_ENCODING) {
            $pattern = str_split(self::$invalid_uri_chars);
            $pattern[] = '#';
            $pattern[] = '?';

            return str_replace($pattern, array_map('rawurlencode', $pattern), $this->getDecoded());
        }

        if ($enc_type == ComponentInterface::RFC3986_ENCODING) {
            return $this->encodePath($this->getDecoded());
        }

        if ($enc_type == ComponentInterface::RFC1738_ENCODING) {
            return $this->toRFC1738($this->encodePath($this->getDecoded()));
        }

        return $this->getDecoded();
    }

    /**
     * Convert a RFC3986 encoded string into a RFC1738 string
     *
     * @param string $str
     *
     * @return string
     */
    abstract protected function toRFC1738(string $str): string;

    /**
     * Validate the encoding type value
     *
     * @param int $enc_type
     *
     * @throws Exception If the encoding type is invalid
     */
    abstract protected function assertValidEncoding(int $enc_type);

    /**
     * Encode a path string according to RFC3986
     *
     * @param string $str can be a string or an array
     *
     * @return string The same type as the input parameter
     */
    abstract protected function encodePath(string $str): string;

    /**
     * Return the decoded string representation of the component
     *
     * @return string
     */
    abstract protected function getDecoded(): string;

    /**
     * Returns an instance without dot segments
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component normalized by removing
     * the dot segment.
     *
     * @return static
     */
    public function withoutDotSegments(): self
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
     * Filter Dot segment according to RFC3986
     *
     * @see http://tools.ietf.org/html/rfc3986#section-5.2.4
     *
     * @param array  $carry   Path segments
     * @param string $segment a path segment
     *
     * @return array
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
     * Returns an instance without duplicate delimiters
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component normalized by removing
     * multiple consecutive empty segment
     *
     * @return static
     */
    public function withoutEmptySegments(): self
    {
        return $this->withContent(preg_replace(',/+,', '/', $this->__toString()));
    }

    /**
     * Returns whether or not the path has a trailing delimiter
     *
     * @return bool
     */
    public function hasTrailingSlash(): bool
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
     * @throws Exception for transformations that would result in a invalid object.
     *
     * @return static
     */
    public function withTrailingSlash(): self
    {
        return $this->hasTrailingSlash() ? $this : $this->withContent($this->__toString().'/');
    }

    /**
     * Returns an instance without a trailing slash
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component without a trailing slash
     *
     * @throws Exception for transformations that would result in a invalid object.
     *
     * @return static
     */
    public function withoutTrailingSlash(): self
    {
        return !$this->hasTrailingSlash() ? $this : $this->withContent(mb_substr($this->__toString(), 0, -1, 'UTF-8'));
    }

    /**
     * Returns whether or not the path is absolute or relative
     *
     * @return bool
     */
    public function isAbsolute(): bool
    {
        $path = $this->__toString();

        return '' !== $path && '/' === mb_substr($path, 0, 1, 'UTF-8');
    }

    /**
     * Returns an instance with a leading slash
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component with a leading slash
     *
     * @throws Exception for transformations that would result in a invalid object.
     *
     * @return static
     */
    public function withLeadingSlash(): self
    {
        return $this->isAbsolute() ? $this : $this->withContent('/'.$this->__toString());
    }

    /**
     * Returns an instance without a leading slash
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component without a leading slash
     *
     * @throws Exception for transformations that would result in a invalid object.
     *
     * @return static
     */
    public function withoutLeadingSlash(): self
    {
        return !$this->isAbsolute() ? $this : $this->withContent(mb_substr($this->__toString(), 1, null, 'UTF-8'));
    }
}

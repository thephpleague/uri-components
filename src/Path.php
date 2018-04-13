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
 * Value object representing a URI path component.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
class Path extends AbstractComponent
{
    /**
     * @internal
     */
    const DOT_SEGMENTS = ['.' => 1, '..' => 1];

    /**
     * @var string
     */
    protected $path;

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return new static($properties['path']);
    }

    /**
     * new instance.
     *
     * @param mixed $path the component value
     */
    public function __construct($path = '')
    {
        $this->path = $this->validate($path);
    }

    /**
     * Validate the component content.
     *
     * @param mixed $path
     *
     * @throws Exception if the component is no valid
     *
     * @return mixed
     */
    protected function validate($path)
    {
        $path = $this->filterComponent($path);
        if (null === $path) {
            throw new TypeError(sprintf('Expected path to be stringable; received %s', gettype($path)));
        }

        return preg_replace_callback(',%[A-Fa-f0-9]{2},', [$this, 'decodeMatches'], $path);
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
        static $regexp = ',%2[D|E]|3[0-9]|4[1-9|A-F]|5[0-9|A|F]|6[1-9|A-F]|7[0-9|E]|2F,i';
        if (preg_match($regexp, $matches[0])) {
            return strtoupper($matches[0]);
        }

        return rawurldecode($matches[0]);
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
    {
        return ['path' => $this->path];
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
        return (string) $this->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(int $enc_type = self::RFC3986_ENCODING)
    {
        $this->filterEncoding($enc_type);

        if (self::NO_ENCODING == $enc_type || !preg_match('/[^A-Za-z0-9_\-\.~]/', $this->path)) {
            return $this->path;
        }

        if ($enc_type === self::RFC3987_ENCODING) {
            static $pattern = '/[\x00-\x1f\x7f\#\?]/';

            return preg_replace_callback($pattern, [$this, 'encodeMatches'], $this->path) ?? $this->path;
        }

        static $regexp = '/(?:[^A-Za-z0-9_\-\.~\!\$&\'\(\)\*\+,;\=%\:\/@]+|%(?![A-Fa-f0-9]{2}))/x';
        $content = preg_replace_callback($regexp, [$this, 'encodeMatches'], $this->path) ?? rawurlencode($this->path);
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
    protected function encodeMatches(array $matches): string
    {
        return rawurlencode($matches[0]);
    }

    /**
     * Returns whether or not the path is absolute or relative.
     *
     * @return bool
     */
    public function isAbsolute(): bool
    {
        return '/' === ($this->path[0] ?? '');
    }

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
        if (isset(self::DOT_SEGMENTS[end($input)])) {
            $new .= '/';
        }

        return new static($new);
    }

    /**
     * Filter Dot segment according to RFC3986.
     *
     * @see http://tools.ietf.org/html/rfc3986#section-5.2.4
     *
     * @param array  $carry   Path segments
     * @param string $segment a path segment
     *
     * @return array
     */
    private function filterDotSegments(array $carry, string $segment): array
    {
        if ('..' === $segment) {
            array_pop($carry);

            return $carry;
        }

        if (!isset(self::DOT_SEGMENTS[$segment])) {
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
     * @return ComponentInterface
     */
    public function withContent($value)
    {
        $value = $this->validate($value);
        if ($value === $this->path) {
            return $this;
        }

        return new static($value);
    }

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
        return new static(preg_replace(',/+,', '/', $this->__toString()));
    }

    /**
     * Returns whether or not the path has a trailing delimiter.
     *
     * @return bool
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
        return $this->hasTrailingSlash() ? $this : new static($this->__toString().'/');
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
        return !$this->hasTrailingSlash() ? $this : new static(substr($this->__toString(), 0, -1));
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
        return $this->isAbsolute() ? $this : new static('/'.$this->__toString());
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
        return !$this->isAbsolute() ? $this : new static(substr($this->__toString(), 1));
    }
}

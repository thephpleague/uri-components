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

namespace League\Uri\Components;

use TypeError;

class Path extends AbstractComponent
{
    /**
     * @internal
     */
    const SEPARATOR = '/';

    /**
     * @internal
     */
    const DOT_SEGMENTS = ['.' => 1, '..' => 1];

    /**
     * @internal
     *
     * matches invalid URI chars + query and fragment delimiters
     */
    const REGEXP_PATH_RFC3987_ENCODING = '/[\x00-\x1f\x7f\#\?]/';

    /**
     * @internal
     */
    const REGEXP_PATH_ENCODING = '/
        (?:[^A-Za-z0-9_\-\.~\!\$&\'\(\)\*\+,;\=%\:\/@]+|
        %(?![A-Fa-f0-9]{2}))
    /x';

    /**
     * @var string
     */
    protected $component;

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties)
    {
        return new static($properties['component']);
    }

    /**
     * new instance.
     *
     * @param mixed $path the component value
     */
    public function __construct($path = '')
    {
        $this->component = $this->validate($path);
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
        $path = $this->validateComponent($path);
        if (null !== $path) {
            return $path;
        }

        throw new TypeError('The path can not be null');
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
    {
        return ['component' => $this->component];
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
        return $this->encodeComponent($this->component, $enc_type, self::REGEXP_PATH_ENCODING, self::REGEXP_PATH_RFC3987_ENCODING);
    }

    /**
     * Returns whether or not the path is absolute or relative.
     *
     * @return bool
     */
    public function isAbsolute(): bool
    {
        return self::SEPARATOR === ($this->component[0] ?? '');
    }

    /**
     * Returns whether or not the path has a trailing delimiter.
     *
     * @return bool
     */
    public function hasTrailingSlash(): bool
    {
        $path = $this->__toString();

        return '' !== $path && self::SEPARATOR === substr($path, -1);
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
        if ($value === $this->component) {
            return $this;
        }

        return new static($value);
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

        $input = explode(self::SEPARATOR, $current);
        $new = implode(self::SEPARATOR, array_reduce($input, [$this, 'filterDotSegments'], []));
        if (isset(self::DOT_SEGMENTS[end($input)])) {
            $new .= self::SEPARATOR ;
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
        return new static(preg_replace(',/+,', self::SEPARATOR, $this->__toString()));
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
        return $this->hasTrailingSlash() ? $this : new static($this->__toString().self::SEPARATOR);
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
        return $this->isAbsolute() ? $this : new static(self::SEPARATOR.$this->__toString());
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

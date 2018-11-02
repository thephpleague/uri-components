<?php

/**
 * League.Uri (http://uri.thephpleague.com/components).
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

namespace League\Uri\Component;

use League\Uri\PathInterface;
use TypeError;
use function array_pop;
use function array_reduce;
use function end;
use function explode;
use function implode;
use function strpos;
use function substr;

final class Path extends Component implements PathInterface
{
    private const SEPARATOR = '/';

    private const DOT_SEGMENTS = ['.' => 1, '..' => 1];

    private const REGEXP_PATH_ENCODING = '/
        (?:[^A-Za-z0-9_\-\.\!\$&\'\(\)\*\+,;\=%\:\/@]+|
        %(?![A-Fa-f0-9]{2}))
    /x';

    /**
     * @var string
     */
    private $component;

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties)
    {
        return new self($properties['component']);
    }

    /**
     * New instance.
     */
    public function __construct($path = '')
    {
        $this->component = $this->validate($path);
    }

    /**
     * Validate the component content.
     *
     * @throws TypeError if the component is no valid
     */
    private function validate($path): string
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
    public function getContent(): ?string
    {
        return $this->encodeComponent($this->component, self::RFC3986_ENCODING, self::REGEXP_PATH_ENCODING);
    }

    /**
     * Returns the decoded path.
     */
    public function decoded(): string
    {
        return (string) $this->encodeComponent($this->component, self::NO_ENCODING, self::REGEXP_PATH_ENCODING);
    }

    /**
     * Returns whether or not the path is absolute or relative.
     */
    public function isAbsolute(): bool
    {
        return self::SEPARATOR === ($this->component[0] ?? '');
    }

    /**
     * Returns whether or not the path has a trailing delimiter.
     */
    public function hasTrailingSlash(): bool
    {
        $path = $this->__toString();

        return '' !== $path && self::SEPARATOR === substr($path, -1);
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($content)
    {
        $content = $this->filterComponent($content);
        if ($content === $this->getContent()) {
            return $this;
        }

        return new self($content);
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

        return new self($new);
    }

    /**
     * Filter Dot segment according to RFC3986.
     *
     * @see http://tools.ietf.org/html/rfc3986#section-5.2.4
     *
     * @param array  $carry   Path segments
     * @param string $segment a path segment
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
     * Returns an instance with a trailing slash.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component with a trailing slash
     *
     * @return static
     */
    public function withTrailingSlash()
    {
        return $this->hasTrailingSlash() ? $this : new self($this->__toString().self::SEPARATOR);
    }

    /**
     * Returns an instance without a trailing slash.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component without a trailing slash
     *
     * @return static
     */
    public function withoutTrailingSlash()
    {
        return !$this->hasTrailingSlash() ? $this : new self(substr($this->__toString(), 0, -1));
    }

    /**
     * Returns an instance with a leading slash.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component with a leading slash
     *
     * @return static
     */
    public function withLeadingSlash()
    {
        return $this->isAbsolute() ? $this : new self(self::SEPARATOR.$this->__toString());
    }

    /**
     * Returns an instance without a leading slash.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component without a leading slash
     *
     * @return static
     */
    public function withoutLeadingSlash()
    {
        return !$this->isAbsolute() ? $this : new self(substr($this->__toString(), 1));
    }
}

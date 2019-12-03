<?php

/**
 * League.Uri (http://uri.thephpleague.com/components)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.2
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\Components;

use League\Uri\Contracts\PathInterface;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriInterface;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use TypeError;
use function array_pop;
use function array_reduce;
use function end;
use function explode;
use function implode;
use function sprintf;
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
    private $path;

    /**
     * {@inheritDoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['path']);
    }

    /**
     * New instance.
     *
     * @param mixed|string $path
     */
    public function __construct($path = '')
    {
        $this->path = $this->validate($path);
    }

    /**
     * Validate the component content.
     *
     * @param mixed $path the path content
     *
     * @throws TypeError if the component is no valid
     */
    private function validate($path): string
    {
        $path = $this->validateComponent($path);
        if (null !== $path) {
            return $path;
        }

        throw new TypeError('The path can not be null.');
    }

    /**
     * Create a new instance from a URI object.
     *
     * @param mixed $uri an URI object
     *
     * @throws TypeError If the URI object is not supported
     */
    public static function createFromUri($uri): self
    {
        if (!$uri instanceof UriInterface && !$uri instanceof Psr7UriInterface) {
            throw new TypeError(sprintf('The object must implement the `%s` or the `%s` interface.', Psr7UriInterface::class, UriInterface::class));
        }

        $path = $uri->getPath();
        $authority = $uri->getAuthority();
        if (null === $authority || '' === $authority) {
            return new self($path);
        }

        if ('' === $path || '/' === $path[0]) {
            return new self($path);
        }

        return new self('/'.$path);
    }

    /**
     * {@inheritDoc}
     */
    public function getContent(): ?string
    {
        return $this->encodeComponent($this->path, self::REGEXP_PATH_ENCODING);
    }

    /**
     * Returns the decoded path.
     */
    public function decoded(): string
    {
        return $this->path;
    }

    /**
     * {@inheritDoc}
     */
    public function isAbsolute(): bool
    {
        return self::SEPARATOR === ($this->path[0] ?? '');
    }

    /**
     * Returns whether or not the path has a trailing delimiter.
     */
    public function hasTrailingSlash(): bool
    {
        return '' !== $this->path && self::SEPARATOR === substr($this->path, -1);
    }

    /**
     * {@inheritDoc}
     */
    public function withContent($content): UriComponentInterface
    {
        $content = self::filterComponent($content);
        if ($content === $this->getContent()) {
            return $this;
        }

        return new self($content);
    }

    /**
     * {@inheritDoc}
     */
    public function withoutDotSegments(): PathInterface
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
     * @return string[]
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
     */
    public function withTrailingSlash(): PathInterface
    {
        return $this->hasTrailingSlash() ? $this : new self($this->__toString().self::SEPARATOR);
    }

    /**
     * Returns an instance without a trailing slash.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the path component without a trailing slash
     */
    public function withoutTrailingSlash(): PathInterface
    {
        return !$this->hasTrailingSlash() ? $this : new self(substr($this->__toString(), 0, -1));
    }

    /**
     * {@inheritDoc}
     */
    public function withLeadingSlash(): PathInterface
    {
        return $this->isAbsolute() ? $this : new self(self::SEPARATOR.$this->__toString());
    }

    /**
     * {@inheritDoc}
     */
    public function withoutLeadingSlash(): PathInterface
    {
        return !$this->isAbsolute() ? $this : new self(substr($this->__toString(), 1));
    }
}

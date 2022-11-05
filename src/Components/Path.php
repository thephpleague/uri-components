<?php

/**
 * League.Uri (https://uri.thephpleague.com/components/2.0/)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
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
use League\Uri\Exceptions\SyntaxError;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use function array_pop;
use function array_reduce;
use function end;
use function explode;
use function implode;
use function substr;

final class Path extends Component implements PathInterface
{
    private const DOT_SEGMENTS = ['.' => 1, '..' => 1];
    private const REGEXP_PATH_ENCODING = '/[^A-Za-z0-9_\-.!$&\'()*+,;=%:\/@]+|%(?![A-Fa-f0-9]{2})/';
    private const SEPARATOR = '/';

    private readonly string $path;

    /**
     * New instance.
     */
    public function __construct(Stringable|float|int|string|bool $path = '')
    {
        $this->path = $this->validate($path);
    }

    public static function __set_state(array $properties): self
    {
        return new self($properties['path']);
    }

    /**
     * Validate the component content.
     */
    private function validate(Stringable|float|int|string|bool $path): string
    {
        return (string) $this->validateComponent($path);
    }

    /**
     * Returns a new instance from a string or a stringable object.
     */
    public static function createFromString(Stringable|string $path = ''): self
    {
        return new self((string) $path);
    }

    /**
     * Create a new instance from a URI object.
     */
    public static function createFromUri(Psr7UriInterface|UriInterface $uri): self
    {
        $path = $uri->getPath();
        $authority = $uri->getAuthority();
        if (null === $authority || '' === $authority || '' === $path || '/' === $path[0]) {
            return new self($path);
        }

        return new self('/'.$path);
    }

    public function value(): ?string
    {
        return $this->encodeComponent($this->path, self::REGEXP_PATH_ENCODING);
    }

    public function getUriComponent(): string
    {
        return (string) $this->value();
    }

    public function decoded(): string
    {
        return $this->path;
    }

    public function isAbsolute(): bool
    {
        return self::SEPARATOR === ($this->path[0] ?? '');
    }

    public function hasTrailingSlash(): bool
    {
        return '' !== $this->path && self::SEPARATOR === substr($this->path, -1);
    }

    public function withContent($content): UriComponentInterface
    {
        $content = self::filterComponent($content);
        if (null === $content) {
            throw new SyntaxError('The path component can not be `null`.');
        }

        if ($content === $this->value()) {
            return $this;
        }

        return new self($content);
    }

    public function withoutDotSegments(): PathInterface
    {
        $current = $this->__toString();
        if (!str_contains($current, '.')) {
            return $this;
        }

        $input = explode(self::SEPARATOR, $current);
        $new = implode(self::SEPARATOR, array_reduce($input, $this->filterDotSegments(...), []));
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

    public function withoutTrailingSlash(): PathInterface
    {
        return !$this->hasTrailingSlash() ? $this : new self(substr($this->__toString(), 0, -1));
    }

    public function withLeadingSlash(): PathInterface
    {
        return $this->isAbsolute() ? $this : new self(self::SEPARATOR.$this->__toString());
    }

    public function withoutLeadingSlash(): PathInterface
    {
        return !$this->isAbsolute() ? $this : new self(substr($this->__toString(), 1));
    }
}

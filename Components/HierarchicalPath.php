<?php

/**
 * League.Uri (https://uri.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\Components;

use Iterator;
use League\Uri\Contracts\PathInterface;
use League\Uri\Contracts\SegmentedPathInterface;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\OffsetOutOfBounds;
use League\Uri\Exceptions\SyntaxError;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use Traversable;
use TypeError;
use function array_count_values;
use function array_filter;
use function array_keys;
use function array_pop;
use function array_unshift;
use function count;
use function dirname;
use function explode;
use function implode;
use function ltrim;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strrpos;
use function substr;
use const ARRAY_FILTER_USE_KEY;
use const FILTER_VALIDATE_INT;
use const PATHINFO_EXTENSION;

final class HierarchicalPath extends Component implements SegmentedPathInterface
{
    private const SEPARATOR = '/';
    private const IS_ABSOLUTE = 1;
    private const IS_RELATIVE = 0;
    private readonly PathInterface $path;
    /** @var string[] */
    private readonly array $segments;

    /**
     * @see ::createFromString
     * @see ::createFromPath
     *
     * New instance.
     */
    private function __construct(UriComponentInterface|Stringable|string|null $path = '')
    {
        if (!$path instanceof PathInterface) {
            $path = new Path($path);
        }

        $this->path = $path;
        $segments = (string) $this->decodeComponent($path->__toString());
        if ($this->path->isAbsolute()) {
            $segments = substr($segments, 1);
        }

        $this->segments = explode(self::SEPARATOR, $segments);
    }

    /**
     * Returns a new instance from a string or a stringable object.
     */
    public static function createFromString(Stringable|string $path = ''): self
    {
        return self::createFromPath(Path::createFromString($path));
    }

    public static function createFromPath(PathInterface $path): self
    {
        return new self($path);
    }

    /**
     * Returns a new instance from an iterable structure.
     *
     * @throws TypeError If the segments are malformed
     */
    public static function createRelativeFromSegments(iterable $segments): self
    {
        return self::createFromSegments(self::IS_RELATIVE, $segments);
    }

    private static function createFromSegments(int $pathType, iterable $segments): self
    {
        if ($segments instanceof Traversable) {
            $segments = iterator_to_array($segments, false);
        }

        $pathSegments = array_map(fn (Stringable|string $segment): string => (string) $segment, $segments);
        $path = implode(self::SEPARATOR, $pathSegments);
        if (self::IS_RELATIVE === $pathType) {
            return new self(ltrim($path, self::SEPARATOR));
        }

        if (self::SEPARATOR !== ($path[0] ?? '')) {
            return new self(self::SEPARATOR.$path);
        }

        return new self($path);
    }

    /**
     * Returns a new instance from an iterable structure.
     *
     * @throws TypeError If the segments are malformed
     */
    public static function createAbsoluteFromSegments(iterable $segments): self
    {
        return self::createFromSegments(self::IS_ABSOLUTE, $segments);
    }

    /**
     * Create a new instance from a URI object.
     */
    public static function createFromUri(Psr7UriInterface|UriInterface $uri): self
    {
        return new self(Path::createFromUri($uri));
    }

    public function count(): int
    {
        return count($this->segments);
    }

    public function getIterator(): Iterator
    {
        yield from $this->segments;
    }

    public function isAbsolute(): bool
    {
        return $this->path->isAbsolute();
    }

    public function hasTrailingSlash(): bool
    {
        return $this->path->hasTrailingSlash();
    }

    public function value(): ?string
    {
        return $this->path->value();
    }

    public function getUriComponent(): string
    {
        return (string) $this->value();
    }

    public function decoded(): string
    {
        return $this->path->decoded();
    }

    public function getDirname(): string
    {
        $path = (string) $this->decodeComponent($this->path->__toString());

        return str_replace(
            ['\\', "\0"],
            [self::SEPARATOR, '\\'],
            dirname(str_replace('\\', "\0", $path))
        );
    }

    public function getBasename(): string
    {
        $data = $this->segments;

        return (string) array_pop($data);
    }

    public function getExtension(): string
    {
        [$basename] = explode(';', $this->getBasename(), 2);

        return pathinfo($basename, PATHINFO_EXTENSION);
    }

    public function get(int $offset): ?string
    {
        if ($offset < 0) {
            $offset += count($this->segments);
        }

        return $this->segments[$offset] ?? null;
    }

    public function keys(?string $segment = null): array
    {
        if (null === $segment) {
            return array_keys($this->segments);
        }

        return array_keys($this->segments, $segment, true);
    }

    public function segments(): array
    {
        return $this->segments;
    }

    public function withoutDotSegments(): PathInterface
    {
        $path = $this->path->withoutDotSegments();
        if ($path !== $this->path) {
            return new self($path);
        }

        return $this;
    }

    public function withLeadingSlash(): PathInterface
    {
        $path = $this->path->withLeadingSlash();
        if ($path !== $this->path) {
            return new self($path);
        }

        return $this;
    }

    public function withoutLeadingSlash(): PathInterface
    {
        $path = $this->path->withoutLeadingSlash();
        if ($path !== $this->path) {
            return new self($path);
        }

        return $this;
    }

    public function withoutTrailingSlash(): PathInterface
    {
        $path = $this->path->withoutTrailingSlash();
        if ($path !== $this->path) {
            return new self($path);
        }

        return $this;
    }

    public function withTrailingSlash(): PathInterface
    {
        $path = $this->path->withTrailingSlash();
        if ($path !== $this->path) {
            return new self($path);
        }

        return $this;
    }

    public function append(Stringable|int|string|bool|null $segment): SegmentedPathInterface
    {
        $segment = self::filterComponent($segment);
        if (null === $segment) {
            throw new TypeError('The appended path can not be null.');
        }

        return new self(
            rtrim($this->path->__toString(), self::SEPARATOR)
            .self::SEPARATOR
            .ltrim($segment, self::SEPARATOR)
        );
    }

    public function prepend(Stringable|int|string|bool|null $segment): SegmentedPathInterface
    {
        $segment = self::filterComponent($segment);
        if (null === $segment) {
            throw new TypeError('The prepended path can not be null.');
        }

        return new self(
            rtrim($segment, self::SEPARATOR)
            .self::SEPARATOR
            .ltrim($this->path->__toString(), self::SEPARATOR)
        );
    }

    public function withSegment(int $key, Stringable|string|null $segment): SegmentedPathInterface
    {
        $nb_segments = count($this->segments);
        if ($key < - $nb_segments - 1 || $key > $nb_segments) {
            throw new OffsetOutOfBounds(sprintf('The given key `%s` is invalid.', $key));
        }

        if (0 > $key) {
            $key += $nb_segments;
        }

        if ($nb_segments === $key) {
            return $this->append($segment);
        }

        if (-1 === $key) {
            return $this->prepend($segment);
        }

        if (null === $segment) {
            throw new SyntaxError('The segment can not be null.');
        }

        if (!$segment instanceof PathInterface) {
            $segment = new self($segment);
        }

        $segment = $this->decodeComponent((string) $segment);
        if ($segment === $this->segments[$key]) {
            return $this;
        }

        $segments = $this->segments;
        $segments[$key] = $segment;
        if ($this->isAbsolute()) {
            array_unshift($segments, '');
        }

        return new self(implode(self::SEPARATOR, $segments));
    }

    public function withoutEmptySegments(): SegmentedPathInterface
    {
        /** @var string $path */
        $path = preg_replace(',/+,', self::SEPARATOR, $this->__toString());

        return new self($path);
    }

    public function withoutSegment(int ...$keys): SegmentedPathInterface
    {
        if ([] === $keys) {
            return $this;
        }
        $nb_segments = count($this->segments);
        $options = ['options' => ['min_range' => - $nb_segments, 'max_range' => $nb_segments - 1]];
        $deleted_keys = [];
        foreach ($keys as $value) {
            /** @var false|int $offset */
            $offset = filter_var($value, FILTER_VALIDATE_INT, $options);
            if (false === $offset) {
                throw new OffsetOutOfBounds(sprintf('The key `%s` is invalid.', $value));
            }

            if ($offset < 0) {
                $offset += $nb_segments;
            }
            $deleted_keys[] = $offset;
        }

        $deleted_keys = array_keys(array_count_values($deleted_keys));
        $filter = static fn ($key): bool => !in_array($key, $deleted_keys, true);

        $path = implode(self::SEPARATOR, array_filter($this->segments, $filter, ARRAY_FILTER_USE_KEY));
        if ($this->isAbsolute()) {
            return new self(self::SEPARATOR.$path);
        }

        return new self($path);
    }

    public function withDirname(PathInterface|Stringable|int|string|bool|null $path): SegmentedPathInterface
    {
        if (null === $path) {
            throw new SyntaxError('A path can not be null.');
        }

        if (!$path instanceof PathInterface) {
            $path = new Path($path);
        }

        if ($path->value() === $this->getDirname()) {
            return $this;
        }

        $segments = $this->segments;

        return new self(
            rtrim($path->__toString(), self::SEPARATOR)
            .self::SEPARATOR
            .array_pop($segments)
        );
    }

    /**
     * @param UriComponentInterface|Stringable|bool|int|null|string $basename
     */
    public function withBasename($basename): SegmentedPathInterface
    {
        $basename = $this->validateComponent($basename);
        if (null === $basename) {
            throw new SyntaxError('A basename sequence can not be null.');
        }

        if (str_contains($basename, self::SEPARATOR)) {
            throw new SyntaxError('The basename can not contain the path separator.');
        }

        return $this->withSegment(count($this->segments) - 1, $basename);
    }

    /**
     * @param UriComponentInterface|Stringable|bool|int|null|string $extension
     */
    public function withExtension($extension): SegmentedPathInterface
    {
        $extension = $this->validateComponent($extension);
        if (null === $extension) {
            throw new SyntaxError('An extension sequence can not be null.');
        }

        if (str_contains($extension, self::SEPARATOR)) {
            throw new SyntaxError('An extension sequence can not contain a path delimiter.');
        }

        if (str_starts_with($extension, '.')) {
            throw new SyntaxError('An extension sequence can not contain a leading `.` character.');
        }

        /** @var string $basename */
        $basename = $this->segments[array_key_last($this->segments)];
        [$ext, $param] = explode(';', $basename, 2) + [1 => null];
        if ('' === $ext) {
            return $this;
        }

        return $this->withBasename($this->buildBasename($extension, (string) $ext, $param));
    }

    /**
     * Creates a new basename with a new extension.
     */
    private function buildBasename(string $extension, string $ext, string $param = null): string
    {
        $length = strrpos($ext, '.'.pathinfo($ext, PATHINFO_EXTENSION));
        if (false !== $length) {
            $ext = substr($ext, 0, $length);
        }

        if (null !== $param && '' !== $param) {
            $param = ';'.$param;
        }

        $extension = trim($extension);
        if ('' === $extension) {
            return $ext.$param;
        }

        return $ext.'.'.$extension.$param;
    }
}

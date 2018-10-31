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

use Countable;
use IteratorAggregate;
use League\Uri\Exception\InvalidKey;
use League\Uri\Exception\InvalidPathSegment;
use League\Uri\Exception\MalformedUriComponent;
use League\Uri\Exception\UnknownType;
use Traversable;
use function array_count_values;
use function array_filter;
use function array_keys;
use function array_pop;
use function array_unshift;
use function count;
use function dirname;
use function end;
use function explode;
use function implode;
use function is_scalar;
use function iterator_to_array;
use function ltrim;
use function method_exists;
use function rtrim;
use function sprintf;
use function str_replace;
use function strpos;
use function strrpos;
use function substr;
use const ARRAY_FILTER_USE_KEY;
use const FILTER_VALIDATE_INT;
use const PATHINFO_EXTENSION;

final class HierarchicalPath extends Path implements Countable, IteratorAggregate
{
    public const IS_ABSOLUTE = 1;

    public const IS_RELATIVE = 0;

    /**
     * @var string[]
     */
    private $segments;

    /**
     * Returns a new instance from an array or a traversable object.
     *
     * @param int $type one of the constant IS_ABSOLUTE or IS_RELATIVE
     *
     * @throws UnknownType        If the type is not recognized
     * @throws InvalidPathSegment If the segments are malformed
     */
    public static function createFromSegments(iterable $segments, int $type = self::IS_RELATIVE): self
    {
        static $type_list = [self::IS_ABSOLUTE => 1, self::IS_RELATIVE => 1];

        if (!isset($type_list[$type])) {
            throw new UnknownType(sprintf('"%s" is an invalid or unsupported %s type', $type, self::class));
        }

        if ($segments instanceof Traversable) {
            $segments = iterator_to_array($segments, false);
        }

        $pathSegments = [];
        foreach ($segments as $value) {
            if (!is_scalar($value) && !method_exists($value, '__toString')) {
                throw new InvalidPathSegment('The submitted segments are invalid');
            }
            $pathSegments[] = (string) $value;
        }

        $path = implode(self::SEPARATOR, $pathSegments);
        if (static::IS_ABSOLUTE !== $type) {
            return new self(ltrim($path, self::SEPARATOR));
        }

        if (self::SEPARATOR !== ($path[0] ?? '')) {
            return new self(self::SEPARATOR.$path);
        }

        return new self($path);
    }

    /**
     * {@inheritdoc}
     */
    protected function parse(): void
    {
        $path = $this->component;
        if (self::SEPARATOR === ($path[0] ?? '')) {
            $path = substr($path, 1);
        }

        $this->segments = explode(self::SEPARATOR, $path);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->segments);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): iterable
    {
        foreach ($this->segments as $segment) {
            yield $segment;
        }
    }

    /**
     * Returns parent directory's path.
     */
    public function getDirname(): string
    {
        return str_replace(
            ['\\', "\0"],
            [self::SEPARATOR, '\\'],
            dirname(str_replace('\\', "\0", $this->component))
        );
    }

    /**
     * Returns the path basename.
     */
    public function getBasename(): string
    {
        $data = $this->segments;

        return (string) array_pop($data);
    }

    /**
     * Returns the basename extension.
     */
    public function getExtension(): string
    {
        [$basename, ] = explode(';', $this->getBasename(), 2);

        return pathinfo($basename, PATHINFO_EXTENSION);
    }

    /**
     * Retrieves a single path segment.
     *
     * Retrieves a single path segment. If the segment offset has not been set,
     * returns the default value provided.
     *
     * @param int $offset the segment offset
     *
     * @return string|null
     */
    public function get(int $offset)
    {
        if ($offset < 0) {
            $offset += count($this->segments);
        }

        return $this->segments[$offset] ?? null;
    }

    /**
     * Returns the associated key for a specific segment.
     *
     * If a value is specified only the keys associated with
     * the given value will be returned
     */
    public function keys(string $segment): array
    {
        return array_keys($this->segments, $segment, true);
    }

    /**
     * Appends a segment to the path.
     *
     * @see ::withSegment
     */
    public function append($segment): self
    {
        if (!$segment instanceof Path) {
            $segment = new self($segment);
        }

        return new self(rtrim($this->component, self::SEPARATOR).self::SEPARATOR.ltrim($segment->component, self::SEPARATOR));
    }

    /**
     * Prepends a segment to the path.
     *
     * @see ::withSegment
     */
    public function prepend($segment): self
    {
        if (!$segment instanceof Path) {
            $segment = new self($segment);
        }

        return new self(rtrim($segment->component, self::SEPARATOR).self::SEPARATOR.ltrim($this->component, self::SEPARATOR));
    }

    /**
     * Returns an instance with the modified segment.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the new segment
     *
     * If $key is non-negative, the added segment will be the segment at $key position from the start.
     * If $key is negative, the added segment will be the segment at $key position from the end.
     *
     * @throws InvalidKey If the key is invalid
     */
    public function withSegment(int $key, $segment): self
    {
        $nb_segments = count($this->segments);
        if ($key < - $nb_segments - 1 || $key > $nb_segments) {
            throw new InvalidKey(sprintf('the given key `%s` is invalid', $key));
        }

        if (0 > $key) {
            $key += $nb_segments;
        }

        if (!$segment instanceof Path) {
            $segment = new self($segment);
        }

        if ($nb_segments === $key) {
            return $this->append($segment);
        }

        if (-1 === $key) {
            return $this->prepend($segment);
        }

        $segment = $segment->decoded();
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

    /**
     * Returns an instance without the specified segment.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component
     *
     * If $key is non-negative, the removed segment will be the segment at $key position from the start.
     * If $key is negative, the removed segment will be the segment at $key position from the end.
     *
     * @param int $key     required key to remove
     * @param int ...$keys remaining keys to remove
     *
     * @throws InvalidKey If the key is invalid
     */
    public function withoutSegment(int $key, int ...$keys): self
    {
        array_unshift($keys, $key);
        $nb_segments = count($this->segments);
        $options = ['options' => ['min_range' => - $nb_segments, 'max_range' => $nb_segments - 1]];
        $deleted_keys = [];
        foreach ($keys as $key) {
            if (false === ($offset = filter_var($key, FILTER_VALIDATE_INT, $options))) {
                throw new InvalidKey(sprintf('the key `%s` is invalid', $key));
            }

            if ($offset < 0) {
                $offset += $nb_segments;
            }
            $deleted_keys[] = $offset;
        }

        $deleted_keys = array_keys(array_count_values($deleted_keys));
        $filter = static function ($key) use ($deleted_keys): bool {
            return !in_array($key, $deleted_keys, true);
        };

        $path = implode(self::SEPARATOR, array_filter($this->segments, $filter, ARRAY_FILTER_USE_KEY));
        if ($this->isAbsolute()) {
            return new self(self::SEPARATOR.$path);
        }

        return new self($path);
    }

    /**
     * Returns an instance with the specified parent directory's path.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the extension basename modified.
     */
    public function withDirname($path): self
    {
        if (!$path instanceof self) {
            $path = new self($path);
        }

        if ($path->getContent() === $this->getDirname()) {
            return $this;
        }

        return $path->withSegment(count($path), array_pop($this->segments));
    }

    /**
     * Returns an instance with the specified basename.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the extension basename modified.
     */
    public function withBasename($basename): self
    {
        if (!$basename instanceof self) {
            $basename = new self($basename);
        }

        if (false !== strpos($basename->component, self::SEPARATOR)) {
            throw new MalformedUriComponent('The basename can not contain the path separator');
        }

        return $this->withSegment(count($this->segments) - 1, $basename);
    }

    /**
     * Returns an instance with the specified basename extension.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the extension basename modified.
     */
    public function withExtension($extension): self
    {
        if (!$extension instanceof self) {
            $extension = new self($extension);
        }

        if (strpos($extension->component, self::SEPARATOR)) {
            throw new MalformedUriComponent('an extension sequence can not contain a path delimiter');
        }

        if (0 === strpos($extension->component, '.')) {
            throw new MalformedUriComponent('an extension sequence can not contain a leading `.` character');
        }

        $basename = end($this->segments);
        [$ext, $param] = explode(';', $basename, 2) + [1 => null];
        if ('' === $ext) {
            return $this;
        }

        return $this->withBasename($this->buildBasename($extension, $ext, $param));
    }

    /**
     * Creates a new basename with a new extension.
     */
    private function buildBasename(self $extension, string $ext, string $param = null): string
    {
        $length = strrpos($ext, '.'.pathinfo($ext, PATHINFO_EXTENSION));
        if (false !== $length) {
            $ext = substr($ext, 0, $length);
        }

        if (null !== $param && '' !== $param) {
            $param = ';'.$param;
        }

        $extension = trim($extension->component);
        if ('' === $extension) {
            return $ext.$param;
        }

        return $ext.'.'.$extension.$param;
    }
}

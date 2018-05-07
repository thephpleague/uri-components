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

use Countable;
use IteratorAggregate;
use Traversable;

final class HierarchicalPath extends Path implements Countable, IteratorAggregate
{
    const IS_ABSOLUTE = 1;

    const IS_RELATIVE = 0;

    /**
     * @var string[]
     */
    private $segments;

    /**
     * @var int
     */
    private $is_absolute;

    /**
     * Returns a new instance from an array or a traversable object.
     *
     * @param mixed $segments The segments list
     * @param int   $type     one of the constant IS_ABSOLUTE or IS_RELATIVE
     *
     * @throws Exception If $data is invalid
     * @throws Exception If $type is not a recognized constant
     *
     * @return self
     */
    public static function createFromSegments($segments, int $type = self::IS_RELATIVE): self
    {
        static $type_list = [self::IS_ABSOLUTE => 1, self::IS_RELATIVE => 1];

        if (!isset($type_list[$type])) {
            throw new Exception(sprintf('"%s" is an invalid flag', $type));
        }

        if ($segments instanceof Traversable) {
            $segments = iterator_to_array($segments, false);
        }

        if (!is_array($segments)) {
            throw new Exception('the segments must be iterable');
        }

        $path = implode(self::SEPARATOR, $segments);
        if (static::IS_ABSOLUTE !== $type) {
            return new static(ltrim($path, self::SEPARATOR));
        }

        if (self::SEPARATOR !== ($path[0] ?? '')) {
            return new static(self::SEPARATOR.$path);
        }

        return new static($path);
    }

    /**
     * New Instance.
     *
     * @param mixed $path
     */
    public function __construct($path = '')
    {
        parent::__construct($path);
        $this->segments = $this->filterSegments($this->component);
        $this->is_absolute = $this->isAbsolute() ? self::IS_ABSOLUTE : self::IS_RELATIVE;
    }

    /**
     * Filter the path segments.
     *
     * @param string $path
     *
     * @return array
     */
    private function filterSegments(string $path): array
    {
        if ('' === $path) {
            return [''];
        }

        if (self::SEPARATOR === $path[0]) {
            $path = substr($path, 1);
        }

        $filterSegment = function ($segment) {
            return isset($segment);
        };

        return array_filter(explode(self::SEPARATOR, $path), $filterSegment);
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
    public function getIterator()
    {
        foreach ($this->segments as $segment) {
            yield $segment;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
    {
        return [
            'component' => $this->component,
            'segments' => $this->segments,
            'is_absolute' => $this->isAbsolute(),
        ];
    }

    /**
     * Returns parent directory's path.
     *
     * @return string
     */
    public function getDirname(): string
    {
        return str_replace(
            ['\\', "\0"],
            [self::SEPARATOR, '\\'],
            dirname(str_replace('\\', "\0", $this->__toString()))
        );
    }

    /**
     * Returns the path basename.
     *
     * @return string
     */
    public function getBasename(): string
    {
        $data = $this->segments;

        return (string) array_pop($data);
    }

    /**
     * Returns the basename extension.
     *
     * @return string
     */
    public function getExtension(): string
    {
        list($basename, ) = explode(';', $this->getBasename(), 2);

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
    public function getSegment(int $offset)
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
     *
     * @param string $segment
     *
     * @return array
     */
    public function keys(string $segment): array
    {
        return array_keys($this->segments, $segment, true);
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return (string) $this->getContent();
    }

    /**
     * Appends a segment to the path.
     *
     * @see ::withSegment
     *
     * @param mixed $segment
     *
     * @return self
     */
    public function append($segment): self
    {
        return $this->withSegment(count($this->segments), $segment);
    }

    /**
     * Prepends a segment to the path.
     *
     * @see ::withSegment
     *
     * @param mixed $segment
     *
     * @return self
     */
    public function prepend($segment): self
    {
        return $this->withSegment(- count($this->segments) - 1, $segment);
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
     * @param int   $key
     * @param mixed $segment
     *
     * @throws Exception If the key is invalid
     *
     * @return self
     */
    public function withSegment(int $key, $segment): self
    {
        $nb_elements = count($this->segments);
        if (false === ($offset = filter_var($key, FILTER_VALIDATE_INT, ['options' => ['min_range' => - $nb_elements - 1, 'max_range' => $nb_elements + 1]]))) {
            throw new Exception(sprintf('the given key `%s` is invalid', $key));
        }

        if ($offset < 0) {
            $offset += $nb_elements;
        }

        if (!$segment instanceof self) {
            $segment = new self($segment);
        }

        //append segment
        if ($nb_elements === $offset) {
            return new self(rtrim($this->component, self::SEPARATOR).self::SEPARATOR.ltrim($segment->component, self::SEPARATOR));
        }

        //prepend segment
        if (-1 === $offset) {
            return new self(rtrim($segment->component, self::SEPARATOR).self::SEPARATOR.ltrim($this->component, self::SEPARATOR));
        }

        //replace segment path while respecting path type
        if (1 === $nb_elements) {
            return self::IS_ABSOLUTE === $this->is_absolute ? $segment->withLeadingSlash() : $segment;
        }

        $segment = trim($segment->getContent(self::NO_ENCODING), self::SEPARATOR);
        if ($segment === $this->segments[$offset]) {
            return $this;
        }

        $segments = $this->segments;
        $segments[$offset] = $segment;

        return self::createFromSegments($segments, $this->is_absolute);
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
     * @throws Exception If the key is invalid
     *
     * @return self
     */
    public function withoutSegments(int $key, int ...$keys): self
    {
        array_unshift($keys, $key);
        $nb_elements = count($this->segments);
        $options = ['options' => ['min_range' => - $nb_elements, 'max_range' => $nb_elements - 1]];
        $deleted_keys = [];
        foreach ($keys as $key) {
            if (false === ($offset = filter_var($key, FILTER_VALIDATE_INT, $options))) {
                throw new Exception(sprintf('the key `%s` is invalid', $key));
            }

            if ($offset < 0) {
                $offset += $nb_elements;
            }
            $deleted_keys[] = $offset;
        }

        $deleted_keys = array_keys(array_count_values($deleted_keys));
        $filter = function ($key) use ($deleted_keys): bool {
            return !in_array($key, $deleted_keys, true);
        };

        return self::createFromSegments(
            array_filter($this->segments, $filter, ARRAY_FILTER_USE_KEY),
            $this->is_absolute
        );
    }

    /**
     * Returns an instance with the specified parent directory's path.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the extension basename modified.
     *
     * @param mixed $path the new parent directory path
     *
     * @return self
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
     *
     * @param mixed $basename the new path basename
     *
     * @return self
     */
    public function withBasename($basename): self
    {
        if (!$basename instanceof self) {
            $basename = new self($basename);
        }

        if (false !== strpos($basename->component, self::SEPARATOR)) {
            throw new Exception('The basename can not contain the path separator');
        }

        return $this->withSegment(count($this->segments) - 1, $basename);
    }

    /**
     * Returns an instance with the specified basename extension.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the extension basename modified.
     *
     * @param mixed $extension the new extension
     *                         can preceeded with or without the dot (.) character
     *
     * @return self
     */
    public function withExtension($extension): self
    {
        if (!$extension instanceof self) {
            $extension = new self($extension);
        }

        if (strpos($extension->component, self::SEPARATOR)) {
            throw new Exception('an extension sequence can not contain a path delimiter');
        }

        if (0 === strpos($extension->component, '.')) {
            throw new Exception('an extension sequence can not contain a leading `.` character');
        }

        $basename = end($this->segments);
        list($ext, $param) = explode(';', $basename, 2) + [1 => null];
        if ('' === $ext) {
            return $this;
        }

        return $this->withBasename($this->buildBasename($extension, $ext, $param));
    }

    /**
     * create a new basename with a new extension.
     *
     * @param self   $extension the new extension to add
     * @param string $ext       the basename file part
     * @param string $param     the basename parameter part
     *
     * @return string
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

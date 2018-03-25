<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.8.0
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace League\Uri\Components;

use Countable;
use IteratorAggregate;
use League\Uri\Exception;
use Traversable;

/**
 * Value object representing a URI path component.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
final class HierarchicalPath extends Path implements Countable, IteratorAggregate
{
    const IS_ABSOLUTE = 1;
    const IS_RELATIVE = 0;

    /**
     * @internal
     */
    const SEPARATOR = '/';

    private $segments;

    private $is_absolute;

    /**
     * return a new instance from an array or a traversable object
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

        if ($segments instanceof self) {
            $segments = $segments->segments;
        }

        if ($segments instanceof Traversable) {
            $segments = iterator_to_array($segments, false);
        }

        if (!is_array($segments)) {
            throw new Exception('the segments must be iterable');
        }

        $path = implode(self::SEPARATOR, $segments);
        if (static::IS_ABSOLUTE !== $type) {
            return new static(ltrim($path, '/'));
        }

        if (self::SEPARATOR !== substr($path, 0, 1)) {
            return new static(self::SEPARATOR.$path);
        }

        return new static($path);
    }

    /**
     * New Instance
     *
     * @param mixed $path
     */
    public function __construct($path = '')
    {
        parent::__construct($path);
        $this->segments = $this->filterSegments($this->path);
        $this->is_absolute = $this->isAbsolute() ? self::IS_ABSOLUTE : self::IS_RELATIVE;
    }

    private function filterSegments(string $path)
    {
        if ('' === $path) {
            return [''];
        }

        if ('/' === $path[0]) {
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
            'path' => $this->path,
            'segments' => $this->segments,
            'is_absolute' => $this->isAbsolute(),
        ];
    }

    /**
     * Returns parent directory's path
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
     * Returns the path basename
     *
     * @return string
     */
    public function getBasename(): string
    {
        $data = $this->segments;

        return (string) array_pop($data);
    }

    /**
     * Returns the basename extension
     *
     * @return string
     */
    public function getExtension(): string
    {
        list($basename, ) = explode(';', $this->getBasename(), 2);

        return pathinfo($basename, PATHINFO_EXTENSION);
    }

    /**
     * Returns an array representation of the HierarchicalPath
     *
     * @return array
     */
    public function getSegments(): array
    {
        return $this->segments;
    }

    /**
     * Retrieves a single path segment.
     *
     * Retrieves a single path segment. If the segment offset has not been set,
     * returns the default value provided.
     *
     * @param int   $offset  the segment offset
     * @param mixed $default Default value to return if the offset does not exist.
     *
     * @return mixed
     */
    public function getSegment(int $offset, $default = null)
    {
        if ($offset < 0) {
            $offset += count($this->segments);
        }

        return $this->segments[$offset] ?? $default;
    }

    /**
     * Returns the associated key for each label.
     *
     * If a value is specified only the keys associated with
     * the given value will be returned
     *
     * @param mixed ...$args the total number of argument given to the method
     *
     * @return array
     */
    public function keys(...$args): array
    {
        if (empty($args)) {
            return array_keys($this->segments);
        }

        return array_keys($this->segments, $args[0], true);
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return (string) $this->getContent();
    }

    /**
     * Returns an instance with the specified component prepended
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component with the prepended data
     *
     * @param mixed $path the component to append
     *
     * @return self
     */
    public function prepend($path): self
    {
        $path = $this->validate($path);
        if ('/' === substr($path, -1, 1)) {
            $path = substr($path, 0, -1);
        }

        $old_path = $this->path;
        if (self::IS_ABSOLUTE === $this->is_absolute) {
            $old_path = substr($old_path, 1);
        }

        return new self($path.'/'.$old_path);
    }

    /**
     * Returns an instance with the specified component appended
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component with the appended data
     *
     * @param mixed $path the component to append
     *
     * @return self
     */
    public function append($path): self
    {
        $path = $this->validate($path);
        if ('/' === ($path[0] ?? '')) {
            $path = substr($path, 1);
        }

        $old_path = $this->path;
        if ('/' === substr($old_path, -1, 1)) {
            $old_path = substr($old_path, 0, -1);
        }

        return new self($old_path.'/'.$path);
    }

    /**
     * Returns an instance with the modified label
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component with the replaced data
     *
     * @param int    $offset    the label offset to remove and replace by the given component
     * @param string $component the component added
     *
     * @return self
     */
    public function replaceSegment(int $offset, string $component): self
    {
        $nb_elements = count($this->segments);
        $offset = filter_var($offset, FILTER_VALIDATE_INT, ['options' => ['min_range' => - $nb_elements, 'max_range' => $nb_elements - 1]]);
        if (false === $offset) {
            return $this;
        }

        if ($offset < 0) {
            $offset = $nb_elements + $offset;
        }

        $dest = $this->filterSegments($this->validate($component));
        if ('' === $dest[count($dest) - 1]) {
            array_pop($dest);
        }

        $segments = array_merge(array_slice($this->segments, 0, $offset), $dest, array_slice($this->segments, $offset + 1));

        if ($segments === $this->segments) {
            return $this;
        }

        return self::createFromSegments($segments, $this->is_absolute);
    }

    /**
     * Returns an instance without the specified keys
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component
     *
     * @param int[] $offsets the list of keys to remove from the collection
     *
     * @return self
     */
    public function withoutSegments(array $offsets): self
    {
        if (array_filter($offsets, 'is_int') !== $offsets) {
            throw new Exception('the list of keys must contain integer only values');
        }

        $segments = $this->segments;
        foreach ($this->filterOffsets(...$offsets) as $offset) {
            unset($segments[$offset]);
        }

        if ($segments === $this->segments) {
            return $this;
        }

        return self::createFromSegments($segments, $this->is_absolute);
    }

    /**
     * Filter Offset list
     *
     * @param int ...$offsets list of keys to remove from the collection
     *
     * @return int[]
     */
    protected function filterOffsets(int ...$offsets)
    {
        $nb_elements = count($this->segments);
        $options = ['options' => ['min_range' => - $nb_elements, 'max_range' => $nb_elements - 1]];
        $keys_to_remove = [];
        foreach ($offsets as $offset) {
            $offset = filter_var($offset, FILTER_VALIDATE_INT, $options);
            if (false === $offset) {
                continue;
            }
            if ($offset < 0) {
                $offset += $nb_elements;
            }
            $keys_to_remove[] = $offset;
        }

        return array_flip(array_flip(array_reverse($keys_to_remove)));
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
        $path = $this->validate($path);
        if ($path === $this->getDirname()) {
            return $this;
        }

        if ('' !== $path && substr($path, -1, 1) === '/') {
            $path = substr($path, 0, -1);
        }

        return new static($path.'/'.array_pop($this->segments));
    }

    /**
     * Returns an instance with the specified basename.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the extension basename modified.
     *
     * @param string $path the new path basename
     *
     * @return self
     */
    public function withBasename(string $path): self
    {
        $path = $this->validate($path);
        if (false !== strpos($path, '/')) {
            throw new Exception('The submitted basename can not contain the path separator');
        }

        $segments = $this->segments;
        $basename = array_pop($segments);
        if ($path == $basename) {
            return $this;
        }

        $segments[] = $path;

        return static::createFromSegments($segments, $this->is_absolute);
    }

    /**
     * Returns an instance with the specified basename extension
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the extension basename modified.
     *
     * @param string $extension the new extension
     *                          can preceeded with or without the dot (.) character
     *
     * @return self
     */
    public function withExtension(string $extension): self
    {
        $extension = $this->formatExtension($extension);
        $segments = $this->segments;
        $basename = array_pop($segments);
        $parts = explode(';', $basename, 2) + [1 => null];
        $basenamePart = $parts[0];
        if ('' === $basenamePart || null === $basenamePart) {
            return $this;
        }

        $newBasename = $this->buildBasename($basenamePart, $extension, $parts[1]);
        if ($basename === $newBasename) {
            return $this;
        }
        $segments[] = $newBasename;

        return static::createFromSegments($segments, $this->is_absolute);
    }

    /**
     * validate and format the given extension
     *
     * @param string $extension the new extension to use
     *
     * @throws Exception If the extension is not valid
     *
     * @return string
     */
    private function formatExtension(string $extension): string
    {
        static $pattern = '/[\x00-\x1f\x7f]/';
        if (preg_match($pattern, $extension)) {
            throw new Exception(sprintf('Invalid path string: %s', $extension));
        }

        if (0 === strpos($extension, '.')) {
            throw new Exception('an extension sequence can not contain a leading `.` character');
        }

        if (strpos($extension, self::SEPARATOR)) {
            throw new Exception('an extension sequence can not contain a path delimiter');
        }

        return implode(self::SEPARATOR, $this->filterSegments($this->validate($extension)));
    }

    /**
     * create a new basename with a new extension
     *
     * @param string $basenamePart  the basename file part
     * @param string $extension     the new extension to add
     * @param string $parameterPart the basename parameter part
     *
     * @return string
     */
    private function buildBasename(
        string $basenamePart,
        string $extension,
        string $parameterPart = null
    ): string {
        $length = strrpos($basenamePart, '.'.pathinfo($basenamePart, PATHINFO_EXTENSION));
        if (false !== $length) {
            $basenamePart = substr($basenamePart, 0, $length);
        }

        $parameterPart = trim((string) $parameterPart);
        if ('' !== $parameterPart) {
            $parameterPart = ";$parameterPart";
        }

        $extension = trim($extension);
        if ('' !== $extension) {
            $extension = ".$extension";
        }

        return $basenamePart.$extension.$parameterPart;
    }
}

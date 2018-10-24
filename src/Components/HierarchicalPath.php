<?php

/**
 * League.Uri (https://uri.thephpleague.com/components/).
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.8.2
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\Components;

use Traversable;

/**
 * Value object representing a URI path component.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
class HierarchicalPath extends AbstractHierarchicalComponent implements ComponentInterface
{
    use PathInfoTrait;

    /**
     * Path segment separator.
     *
     * @var string
     */
    protected static $separator = '/';

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return static::createFromSegments($properties['data'], $properties['is_absolute']);
    }

    /**
     * return a new instance from an array or a traversable object.
     *
     * @param Traversable|array $data The segments list
     * @param int               $type one of the constant IS_ABSOLUTE or IS_RELATIVE
     *
     * @throws Exception If $data is invalid
     * @throws Exception If $type is not a recognized constant
     *
     * @return static
     */
    public static function createFromSegments($data, int $type = self::IS_RELATIVE): self
    {
        static $type_list = [self::IS_ABSOLUTE => 1, self::IS_RELATIVE => 1];

        if (!isset($type_list[$type])) {
            throw Exception::fromInvalidFlag($type);
        }

        if ($data instanceof self) {
            $new = clone $data;
            $new->is_absolute = $type;

            return $new;
        }

        $path = implode(static::$separator, static::filterIterable($data));
        if (static::IS_ABSOLUTE === $type) {
            if (static::$separator !== substr($path, 0, 1)) {
                return new static(static::$separator.$path);
            }

            return new static($path);
        }

        return new static(ltrim($path, '/'));
    }

    /**
     * New Instance.
     *
     */
    public function __construct(string $path = null)
    {
        if (null === $path) {
            $path = '';
        }

        $path = $this->validateString($path);
        $this->is_absolute = static::IS_RELATIVE;
        if (static::$separator === substr($path, 0, 1)) {
            $this->is_absolute = static::IS_ABSOLUTE;
            $path = substr($path, 1, strlen($path));
        }

        $append_delimiter = false;
        if (static::$separator === substr($path, -1, 1)) {
            $path = substr($path, 0, -1);
            $append_delimiter = true;
        }

        $this->data = $this->validate($path);
        if ($append_delimiter) {
            $this->data[] = '';
        }
    }

    /**
     * validate the submitted data.
     *
     *
     */
    protected function validate(string $data): array
    {
        $filterSegment = function ($segment) {
            return isset($segment);
        };

        $data = $this->decodePath($data);

        return array_filter(explode(static::$separator, $data), $filterSegment);
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
    {
        return [
            'component' => $this->getContent(),
            'segments' => $this->data,
            'is_absolute' => (bool) $this->is_absolute,
        ];
    }

    /**
     * Returns parent directory's path.
     *
     */
    public function getDirname(): string
    {
        return str_replace(
            ['\\', "\0"],
            [static::$separator, '\\'],
            dirname(str_replace('\\', "\0", $this->__toString()))
        );
    }

    /**
     * Returns the path basename.
     *
     */
    public function getBasename(): string
    {
        $data = $this->data;

        return (string) array_pop($data);
    }

    /**
     * Returns the basename extension.
     *
     */
    public function getExtension(): string
    {
        list($basename, ) = explode(';', $this->getBasename(), 2);

        return pathinfo($basename, PATHINFO_EXTENSION);
    }

    /**
     * Returns an array representation of the HierarchicalPath.
     *
     */
    public function getSegments(): array
    {
        return $this->data;
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
     */
    public function getSegment(int $offset, $default = null)
    {
        if ($offset < 0) {
            $offset += count($this->data);
        }

        return $this->data[$offset] ?? $default;
    }

    /**
     * Returns the associated key for each label.
     *
     * If a value is specified only the keys associated with
     * the given value will be returned
     *
     * @param mixed ...$args the total number of argument given to the method
     *
     */
    public function keys(...$args): array
    {
        if (empty($args)) {
            return array_keys($this->data);
        }

        return array_keys($this->data, $this->decodeComponent($this->validateString($args[0])), true);
    }

    /**
     * Return the decoded string representation of the component.
     *
     */
    protected function getDecoded(): string
    {
        $front_delimiter = '';
        if ($this->is_absolute === static::IS_ABSOLUTE) {
            $front_delimiter = static::$separator;
        }

        return $front_delimiter.implode(static::$separator, $this->data);
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
    public function withContent($value): ComponentInterface
    {
        if ($value === $this->getContent()) {
            return $this;
        }

        return new static($value);
    }

    /**
     * Returns an instance with the specified component prepended.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component with the prepended data
     *
     * @param string $path the component to append
     *
     * @return static
     */
    public function prepend(string $path): self
    {
        $new_segments = $this->filterComponent($path);
        if (!empty($new_segments) && '' === end($new_segments)) {
            array_pop($new_segments);
        }

        return static::createFromSegments(array_merge($new_segments, $this->data), $this->is_absolute);
    }

    /**
     * Returns an instance with the specified component appended.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component with the appended data
     *
     * @param string $path the component to append
     *
     * @return static
     */
    public function append(string $path): self
    {
        $new_segments = $this->filterComponent($path);
        $data = $this->data;
        if (!empty($data) && '' === end($data)) {
            array_pop($data);
        }

        return static::createFromSegments(array_merge($data, $new_segments), $this->is_absolute);
    }

    /**
     * Filter the component to append or prepend.
     *
     *
     */
    protected function filterComponent(string $path): array
    {
        $path = $this->validateString($path);
        if ('' != $path && '/' == $path[0]) {
            $path = substr($path, 1);
        }

        $filterSegment = function ($segment) {
            return isset($segment);
        };

        return array_filter(explode(static::$separator, $path), $filterSegment);
    }

    /**
     * Returns an instance with the modified label.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component with the replaced data
     *
     * @param int    $offset    the label offset to remove and replace by the given component
     * @param string $component the component added
     *
     * @return static
     */
    public function replaceSegment(int $offset, string $component): self
    {
        $data = $this->replace($offset, $component);
        if ($data === $this->data) {
            return $this;
        }

        return self::createFromSegments($data, $this->is_absolute);
    }


    /**
     * Returns an instance without the specified keys.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component
     *
     * @param int[] $offsets the list of keys to remove from the collection
     *
     * @return static
     */
    public function withoutSegments(array $offsets): self
    {
        $data = $this->delete($offsets);
        if ($data === $this->data) {
            return $this;
        }

        return self::createFromSegments($data, $this->is_absolute);
    }

    /**
     * Returns an instance with the specified parent directory's path.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the extension basename modified.
     *
     * @param string $path the new parent directory path
     *
     * @return static
     */
    public function withDirname(string $path): self
    {
        $path = $this->validateString($path);
        if ($path === $this->getDirname()) {
            return $this;
        }

        if ('' !== $path && substr($path, -1, 1) === '/') {
            $path = substr($path, 0, -1);
        }

        return new static($path.'/'.array_pop($this->data));
    }

    /**
     * Returns an instance with the specified basename.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the extension basename modified.
     *
     * @param string $path the new path basename
     *
     * @return static
     */
    public function withBasename(string $path): self
    {
        $path = $this->validateString($path);
        if (false !== strpos($path, '/')) {
            throw new Exception('The submitted basename can not contain the path separator');
        }

        $data = $this->data;
        $basename = array_pop($data);
        if ($path == $basename) {
            return $this;
        }

        $data[] = $path;

        return static::createFromSegments($data, $this->is_absolute);
    }

    /**
     * Returns an instance with the specified basename extension.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the extension basename modified.
     *
     * @param string $extension the new extension
     *                          can preceeded with or without the dot (.) character
     *
     * @return static
     */
    public function withExtension(string $extension): self
    {
        $extension = $this->formatExtension($extension);
        $segments = $this->getSegments();
        $basename = array_pop($segments);
        $parts = explode(';', $basename, 2);
        $basenamePart = array_shift($parts);
        if ('' === $basenamePart || is_null($basenamePart)) {
            return $this;
        }

        $newBasename = $this->buildBasename($basenamePart, $extension, array_shift($parts));
        if ($basename === $newBasename) {
            return $this;
        }
        $segments[] = $newBasename;

        return $this->createFromSegments($segments, $this->is_absolute);
    }

    /**
     * create a new basename with a new extension.
     *
     * @param string $basenamePart  the basename file part
     * @param string $extension     the new extension to add
     * @param string $parameterPart the basename parameter part
     *
     */
    protected function buildBasename(
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

    /**
     * validate and format the given extension.
     *
     * @param string $extension the new extension to use
     *
     * @throws Exception If the extension is not valid
     *
     */
    protected function formatExtension(string $extension): string
    {
        if (0 === strpos($extension, '.')) {
            throw new Exception('an extension sequence can not contain a leading `.` character');
        }

        if (strpos($extension, static::$separator)) {
            throw new Exception('an extension sequence can not contain a path delimiter');
        }

        return implode(static::$separator, $this->validate($extension));
    }
}

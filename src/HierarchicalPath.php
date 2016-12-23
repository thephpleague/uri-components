<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @copyright  2016 Ignace Nyamagana Butera
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.0.0
 * @link       https://github.com/thephpleague/uri-components
 */
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
class HierarchicalPath extends AbstractHierarchicalComponent implements PathInterface
{
    use PathInfo;

    /**
     * Path segment separator
     *
     * @var string
     */
    protected static $separator = '/';

    /**
     * This static method is called for classes exported by var_export()
     *
     * @param array $properties
     *
     * @return static
     */
    public static function __set_state(array $properties): self
    {
        return static::createFromSegments($properties['data'], $properties['is_absolute']);
    }

    /**
     * return a new instance from an array or a traversable object
     *
     * @param Traversable|string[] $data The segments list
     * @param int                  $type one of the constant IS_ABSOLUTE or IS_RELATIVE
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

        $path = implode(static::$separator, static::validateIterator($data));
        if (static::IS_ABSOLUTE === $type) {
            $path = static::$separator.$path;
        }

        return new static($path);
    }

    /**
     * New Instance
     *
     * @param string|null $path
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
     * validate the submitted data
     *
     * @param string $data
     *
     * @return array
     */
    protected function validate(string $data): array
    {
        $data = $this->filterEncodedPath($data);

        $filterSegment = function ($segment) {
            return isset($segment);
        };

        $data = $this->decodePath($data);

        return array_filter(explode(static::$separator, $data), $filterSegment);
    }

    /**
     * Return a new instance when needed
     *
     * @param array $data
     * @param int   $is_absolute
     *
     * @return static
     */
    protected function newHierarchicalInstance(array $data, int $is_absolute): AbstractHierarchicalComponent
    {
        return static::createFromSegments($data, $is_absolute);
    }

    /**
     * Called by var_dump() when dumping The object
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            'segments' => $this->data,
            'is_absolute' => (bool) $this->is_absolute,
            'component' => $this->getContent(),
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
            [static::$separator, '\\'],
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
        $data = $this->data;

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
     * @return mixed
     */
    public function getSegment(int $offset, $default = null)
    {
        if ($offset > -1 && isset($this->data[$offset])) {
            return $this->data[$offset];
        }

        $nb_segments = count($this->data);
        if ($offset <= -1 && $nb_segments + $offset > -1) {
            return $this->data[$nb_segments + $offset];
        }

        return $default;
    }

    /**
     * Returns the associated key for each label.
     *
     * If a value is specified only the keys associated with
     * the given value will be returned
     *
     * @return array
     */
    public function keys(): array
    {
        if (0 === func_num_args()) {
            return array_keys($this->data);
        }

        return array_keys(
            $this->data,
            $this->decodeComponent($this->validateString(func_get_arg(0))),
            true
        );
    }

    /**
     * Return the decoded string representation of the component
     *
     * @return string
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
     * Returns the instance string representation; If the
     * instance is not defined an empty string is returned
     *
     * @return string
     */
    public function __toString(): string
    {
        return (string) $this->getContent();
    }

    /**
     * Returns an instance with the specified component prepended
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component with the prepended data
     *
     * @param string $component the component to append
     *
     * @return static
     */
    public function prepend(string $component): self
    {
        $new_segments = $this->filterComponent($component);
        if (!empty($new_segments) && '' === end($new_segments)) {
            array_pop($new_segments);
        }

        return static::createFromSegments(array_merge($new_segments, $this->data), $this->is_absolute);
    }

    /**
     * Returns an instance with the specified component appended
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component with the appended data
     *
     * @param string $component the component to append
     *
     * @return static
     */
    public function append(string $component): self
    {
        $new_segments = $this->filterComponent($component);
        $data = $this->data;
        if (!empty($data) && '' === end($data)) {
            array_pop($data);
        }

        return static::createFromSegments(array_merge($data, $new_segments), $this->is_absolute);
    }

    /**
     * Filter the component to append or prepend
     *
     * @param string $component
     *
     * @return array
     */
    protected function filterComponent(string $component): array
    {
        $component = $this->validateString($component);
        if ('' != $component && '/' == $component[0]) {
            $component = substr($component, 1);
        }

        return $this->validate($component);
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
     * Returns an instance with the specified basename extension
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
     * create a new basename with a new extension
     *
     * @param string $basenamePart  the basename file part
     * @param string $extension     the new extension to add
     * @param string $parameterPart the basename parameter part
     *
     * @return string
     */
    protected function buildBasename(
        string $basenamePart,
        string $extension,
        string $parameterPart = null): string
    {
        $length = mb_strrpos($basenamePart, '.'.pathinfo($basenamePart, PATHINFO_EXTENSION), 'UTF-8');
        if (false !== $length) {
            $basenamePart = mb_substr($basenamePart, 0, $length, 'UTF-8');
        }

        $parameterPart = trim($parameterPart);
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
     * validate and format the given extension
     *
     * @param string $extension the new extension to use
     *
     * @throws Exception If the extension is not valid
     *
     * @return string
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

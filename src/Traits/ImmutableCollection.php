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
namespace League\Uri\Components\Traits;

use ArrayIterator;
use InvalidArgumentException;
use League\Uri\Components\Collection;
use Traversable;

/**
 * Common methods for Immutable Collection objects
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
trait ImmutableCollection
{
    /**
     * The component Data
     *
     * @var array
     */
    protected $data = [];

    /**
     * Count elements of an object
     *
     * @return int
     */
    public function count()
    {
        return count($this->data);
    }

    /**
     * Returns an external iterator
     *
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->data);
    }

    /**
     * Returns whether the given key exists in the current instance
     *
     * @param string $offset
     *
     * @return bool
     */
    public function hasKey($offset)
    {
        return array_key_exists($offset, $this->data);
    }

    /**
     * Returns the component $keys.
     *
     * If a value is specified only the keys associated with
     * the given value will be returned
     *
     * @return array
     */
    public function keys()
    {
        if (0 === func_num_args()) {
            return array_keys($this->data);
        }

        return array_keys($this->data, func_get_arg(0), true);
    }

    /**
     * Returns an instance without the specified keys
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component
     *
     * @param array $offsets the list of keys to remove from the collection
     *
     * @return static
     */
    public function without(array $offsets)
    {
        $data = $this->data;
        foreach ($offsets as $offset) {
            unset($data[$offset]);
        }

        if ($data === $this->data) {
            return $this;
        }

        return $this->newCollectionInstance($data);
    }

    /**
     * Return a new instance when needed
     *
     * @param array $data
     *
     * @return Collection
     */
    abstract protected function newCollectionInstance(array $data);

    /**
     * Returns an instance with only the specified value
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component
     *
     * @param callable $callable the list of keys to keep from the collection
     * @param int      $flag     flag to determine what argument are sent to callback
     *
     * @return static
     */
    public function filter(callable $callable, $flag = 0)
    {
        static $flags_list = [0 => 1, ARRAY_FILTER_USE_BOTH => 1, ARRAY_FILTER_USE_KEY => 1];

        if (!isset($flags_list[$flag])) {
            throw new InvalidArgumentException('Invalid or Unknown flag parameter');
        }

        return $this->newCollectionInstance(array_filter($this->data, $callable, $flag));
    }

    /**
     * Validate an Iterator or an array
     *
     * @param iterable $data
     *
     * @throws InvalidArgumentException if the value can not be converted
     *
     * @return array
     */
    protected static function validateIterator($data)
    {
        if ($data instanceof Traversable) {
            return iterator_to_array($data);
        }

        if (is_array($data)) {
            return $data;
        }

        throw new InvalidArgumentException('Data passed to the method must be an iterable');
    }
}

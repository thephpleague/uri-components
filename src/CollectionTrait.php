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

use ArrayIterator;
use InvalidArgumentException;
use Traversable;

/**
 * Common methods for Immutable Collection objects
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
trait CollectionTrait
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
     * Return a new instance when needed
     *
     * @param array $data
     *
     * @return $this
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
            throw Exception::fromInvalidFlag($flag);
        }

        return $this->newCollectionInstance(array_filter($this->data, $callable, $flag));
    }

    /**
     * Validate an Iterator or an array
     *
     * @param Traversable|array $data
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

        throw Exception::fromInvalidIterable($data);
    }
}

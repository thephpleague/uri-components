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

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * An abstract class to ease collection like object manipulation.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
abstract class AbstractHierarchicalComponent implements Countable, IteratorAggregate
{
    use ComponentTrait;

    const IS_ABSOLUTE = 1;

    const IS_RELATIVE = 0;

    /**
     * Hierarchical component separator.
     *
     * @var string
     */
    protected static $separator;

    /**
     * Is the object considered absolute.
     *
     * @var int
     */
    protected $is_absolute = self::IS_RELATIVE;
    /**
     * The component Data.
     *
     * @var array
     */
    protected $data = [];

    /**
     * new instance.
     *
     * @param string|null $data the component value
     */
    abstract public function __construct(string $data = null);

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->data);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new ArrayIterator($this->data);
    }

    /**
     * Returns whether or not the component is absolute or not.
     *
     */
    public function isAbsolute(): bool
    {
        return $this->is_absolute === self::IS_ABSOLUTE;
    }

    /**
     * {@inheritdoc}
     */
    abstract public function getContent(int $enc_type = EncodingInterface::RFC3986_ENCODING);

    /**
     * {@inheritdoc}
     */
    abstract public function withContent($value): ComponentInterface;

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
    public function getUriComponent(): string
    {
        return $this->__toString();
    }

    /**
     * Returns an instance with the modified segment.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component with the replaced data
     *
     * @param int    $offset    the label offset to remove and replace by the given component
     * @param string $component the component added
     *
     */
    protected function replace(int $offset, string $component): array
    {
        $nb_elements = count($this->data);
        $offset = filter_var(
            $offset,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => - $nb_elements, 'max_range' => $nb_elements - 1]]
        );
        if (false === $offset) {
            return $this->data;
        }

        if ($offset < 0) {
            $offset = $nb_elements + $offset;
        }

        $dest = iterator_to_array(new static($component));
        if ('' === $dest[count($dest) - 1]) {
            array_pop($dest);
        }

        $source = iterator_to_array($this);

        return array_merge(array_slice($source, 0, $offset), $dest, array_slice($source, $offset + 1));
    }

    /**
     * Returns an instance without the specified keys.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component
     *
     * @param int[] $offsets the list of keys to remove from the collection
     *
     */
    protected function delete(array $offsets): array
    {
        if (array_filter($offsets, 'is_int') !== $offsets) {
            throw new Exception('the list of keys must contain integer only values');
        }

        $data = $this->data;
        foreach ($this->filterOffsets(...$offsets) as $offset) {
            unset($data[$offset]);
        }

        return $data;
    }

    /**
     * Filter Offset list.
     *
     * @param int ...$offsets list of keys to remove from the collection
     *
     * @return int[]
     */
    protected function filterOffsets(int ...$offsets)
    {
        $nb_elements = count($this->data);
        $options = ['options' => ['min_range' => - $nb_elements, 'max_range' => $nb_elements - 1]];

        $mapper = function ($offset) use ($nb_elements, $options) {
            $offset = filter_var($offset, FILTER_VALIDATE_INT, $options);
            if ($offset < 0) {
                return $nb_elements + $offset;
            }

            return $offset;
        };

        return array_filter(array_unique(array_map($mapper, $offsets)), 'is_int');
    }
}

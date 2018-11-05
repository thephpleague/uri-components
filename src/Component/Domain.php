<?php

/**
 * League.Uri (http://uri.thephpleague.com/components).
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.0
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\Component;

use Countable;
use IteratorAggregate;
use League\Uri\Exception\InvalidKey;
use League\Uri\Exception\MalformedUriComponent;
use League\Uri\HostInterface;
use TypeError;
use function array_count_values;
use function array_filter;
use function array_keys;
use function array_reverse;
use function array_shift;
use function array_unshift;
use function count;
use function explode;
use function implode;
use function reset;
use function sprintf;

final class Domain extends Component implements Countable, HostInterface, IteratorAggregate
{
    private const SEPARATOR = '.';

    /**
     * @var HostInterface
     */
    private $host;

    /**
     * @var string[]
     */
    private $labels = [];

    /**
     * Returns a new instance from an iterable structure.
     *
     * @throws TypeError If a label is the null value
     */
    public static function createFromLabels(iterable $labels): self
    {
        $hostLabels = [];
        foreach ($labels as $label) {
            $label = self::filterComponent($label);
            if (null === $label) {
                throw new TypeError('a label can not be null');
            }
            $hostLabels[] = $label;
        }

        return new self(implode(self::SEPARATOR, array_reverse($hostLabels)));
    }

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['host']);
    }

    /**
     * {@inheritdoc}
     */
    public function __construct($host = null)
    {
        if (!$host instanceof HostInterface) {
            $host = new Host($host);
        }

        $this->host = $host;
        if (!$this->host->isDomain()) {
            throw new MalformedUriComponent(sprintf('`%s` is an invalid domain name', $host));
        }

        $content = $this->host->getContent();
        if (null === $content) {
            return;
        }

        if ('' === $content) {
            $this->labels = [''];
            return;
        }

        $this->labels = array_reverse(explode(self::SEPARATOR, $content));
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): ?string
    {
        return $this->host->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function toAscii(): ?string
    {
        return $this->host->toAscii();
    }

    /**
     * {@inheritdoc}
     */
    public function toUnicode(): ?string
    {
        return $this->host->toUnicode();
    }

    /**
     * {@inheritdoc}
     */
    public function isIp(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isDomain(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getIpVersion(): ?string
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getIp(): ?string
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->labels);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): iterable
    {
        foreach ($this->labels as $label) {
            yield $label;
        }
    }

    /**
     * Retrieves a single host label.
     *
     * If the label offset has not been set, returns the null value.
     */
    public function get(int $offset): ?string
    {
        if ($offset < 0) {
            $offset += count($this->labels);
        }

        return $this->labels[$offset] ?? null;
    }

    /**
     * Returns the associated key for a specific label.
     */
    public function keys(string $label): array
    {
        return array_keys($this->labels, $label, true);
    }

    /**
     * Tells whether the domain is absolute.
     */
    public function isAbsolute(): bool
    {
        return count($this->labels) > 1 && '' === reset($this->labels);
    }

    /**
     * Prepends a label to the host.
     */
    public function prepend($label): self
    {
        $label = $this->filterComponent($label);
        if (null === $label) {
            return $this;
        }

        return new self($label.self::SEPARATOR.$this->getContent());
    }

    /**
     * Appends a label to the host.
     */
    public function append($label): self
    {
        $label = $this->filterComponent($label);
        if (null === $label) {
            return $this;
        }

        return new self($this->getContent().self::SEPARATOR.$label);
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($content): self
    {
        $content = $this->filterComponent($content);
        if ($content === $this->host->getContent()) {
            return $this;
        }

        return new self($content);
    }

    /**
     * Returns an instance with its Root label.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2.2
     */
    public function withRootLabel(): self
    {
        if ('' === reset($this->labels)) {
            return $this;
        }

        return $this->append('');
    }

    /**
     * Returns an instance without its Root label.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2.2
     */
    public function withoutRootLabel(): self
    {
        if ('' !== reset($this->labels)) {
            return $this;
        }

        $labels = $this->labels;
        array_shift($labels);

        return self::createFromLabels($labels);
    }

    /**
     * Returns an instance with the modified label.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the new label
     *
     * If $key is non-negative, the added label will be the label at $key position from the start.
     * If $key is negative, the added label will be the label at $key position from the end.
     *
     * @throws InvalidKey If the key is invalid
     */
    public function withLabel(int $key, $label): self
    {
        $nb_labels = count($this->labels);
        if ($key < - $nb_labels - 1 || $key > $nb_labels) {
            throw new InvalidKey(sprintf('no label can be added with the submitted key : `%s`', $key));
        }

        if (0 > $key) {
            $key += $nb_labels;
        }

        if ($nb_labels === $key) {
            return $this->append($label);
        }

        if (-1 === $key) {
            return $this->prepend($label);
        }

        if (!$label instanceof HostInterface) {
            $label = new Host($label);
        }

        $label = $label->getContent();
        if ($label === $this->labels[$key]) {
            return $this;
        }

        $labels = $this->labels;
        $labels[$key] = $label;

        return new self(implode(self::SEPARATOR, array_reverse($labels)));
    }

    /**
     * Returns an instance without the specified label.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component
     *
     * If $key is non-negative, the removed label will be the label at $key position from the start.
     * If $key is negative, the removed label will be the label at $key position from the end.
     *
     * @param int ...$keys
     *
     * @throws InvalidKey If the key is invalid
     */
    public function withoutLabel(int $key, int ...$keys): self
    {
        array_unshift($keys, $key);
        $nb_labels = count($this->labels);
        foreach ($keys as &$key) {
            if (- $nb_labels > $key || $nb_labels - 1 < $key) {
                throw new InvalidKey(sprintf('no label can be removed with the submitted key : `%s`', $key));
            }

            if (0 > $key) {
                $key += $nb_labels;
            }
        }
        unset($key);

        $deleted_keys = array_keys(array_count_values($keys));
        $filter = static function ($key) use ($deleted_keys): bool {
            return !in_array($key, $deleted_keys, true);
        };

        return self::createFromLabels(array_filter($this->labels, $filter, ARRAY_FILTER_USE_KEY));
    }
}

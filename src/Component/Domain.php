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

namespace League\Uri\Component;

use Countable;
use IteratorAggregate;
use League\Uri\Exception\InvalidHostLabel;
use League\Uri\Exception\InvalidKey;
use League\Uri\Exception\MalformedUriComponent;
use League\Uri\Exception\UnknownType;
use Traversable;
use TypeError;

/**
 * Value object representing a URI Host component.
 *
 * Instances of this interface are considered immutable; all methods that
 * might change state MUST be implemented such that they retain the internal
 * state of the current instance and return an instance that contains the
 * changed state.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Component
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 * @see        https://tools.ietf.org/html/rfc3986#section-3.2.2
 */
final class Domain extends Host implements Countable, IteratorAggregate
{
    /**
     * @internal
     */
    const SEPARATOR = '.';

    /**
     * @internal
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * Domain name regular expression
     */
    const REGEXP_DOMAIN_NAME = '/(?(DEFINE)
        (?<unreserved> [a-z0-9_~\-])
        (?<sub_delims> [!$&\'()*+,;=])
        (?<encoded> %[A-F0-9]{2})
        (?<reg_name> (?:(?&unreserved)|(?&sub_delims)|(?&encoded)){1,63})
    )
    ^(?:(?&reg_name)\.){0,126}(?&reg_name)\.?$/ix';

    /**
     * @var array
     */
    private $labels = [];

    /**
     * Returns a new instance from an array or a traversable object.
     *
     * @param mixed $labels
     *
     * @throws TypeError        If $labels is not iterable
     * @throws InvalidHostLabel If the labels are malformed
     * @throws UnknownType      If the type is not recognized/supported
     *
     * @return self
     */
    public static function createFromLabels($labels): self
    {
        if ($labels instanceof Traversable) {
            $labels = iterator_to_array($labels, false);
        }

        if (!is_array($labels)) {
            throw new TypeError('the parameters must be iterable');
        }

        foreach ($labels as $label) {
            if (!is_scalar($label) && !method_exists($label, '__toString')) {
                throw new InvalidHostLabel(sprintf('The labels are malformed'));
            }
        }

        if (2 > count($labels)) {
            return new self(array_pop($labels));
        }

        return new self(implode(self::SEPARATOR, array_reverse($labels)));
    }

    /**
     * {@inheritdoc}
     */
    protected function parse(string $host = null)
    {
        $this->component = $host;

        if (null === $host) {
            $this->labels = [];
            return;
        }

        if ('' === $host) {
            $this->labels = [$host];

            return;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new MalformedUriComponent(sprintf('`%s` is an invalid domain name : this is an IPv4 host', $host));
        }

        $domain_name = rawurldecode($host);
        if (!preg_match(self::REGEXP_NON_ASCII_PATTERN, $domain_name)) {
            $domain_name = strtolower($domain_name);
        }

        if (preg_match(self::REGEXP_DOMAIN_NAME, $domain_name)) {
            $this->component = $domain_name;
            $this->labels = array_reverse(explode(self::SEPARATOR, $domain_name));
            return;
        }

        if (!preg_match(self::REGEXP_NON_ASCII_PATTERN, $domain_name) || preg_match(self::REGEXP_INVALID_HOST_CHARS, $domain_name)) {
            throw new MalformedUriComponent(sprintf('`%s` is an invalid domain name : the host contains invalid characters', $host));
        }

        self::supportIdnHost();

        $domain_name = idn_to_ascii($domain_name, 0, INTL_IDNA_VARIANT_UTS46, $arr);
        if (0 === $arr['errors']) {
            $this->component = $domain_name;
            $this->labels = array_reverse(explode(self::SEPARATOR, $domain_name));

            return;
        }

        throw new MalformedUriComponent(sprintf('`%s` is an invalid domain name : %s', $host, $this->getIDNAErrors($arr['errors'])));
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->labels);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        foreach ($this->labels as $label) {
            yield $label;
        }
    }

    /**
     * Retrieves a single host label.
     *
     * If the label offset has not been set, returns the null value.
     *
     * @param int $offset the label offset
     *
     * @return string|null
     */
    public function get(int $offset)
    {
        if ($offset < 0) {
            $offset += count($this->labels);
        }

        return $this->labels[$offset] ?? null;
    }

    /**
     * Returns the associated key for a specific label.
     *
     * @param string $label
     *
     * @return array
     */
    public function keys(string $label): array
    {
        return array_keys($this->labels, $label, true);
    }

    public function isAbsolute(): bool
    {
        return count($this->labels) > 1 && '' === reset($this->labels);
    }

    /**
     * Prepends a label to the host.
     *
     * @param mixed $label
     *
     * @return self
     */
    public function prepend($label): self
    {
        if (!$label instanceof Host) {
            $label = new Host($label);
        }

        return new self($label->getContent().self::SEPARATOR.$this->getContent());
    }

    /**
     * Appends a label to the host.
     *
     * @param mixed $label
     *
     * @return self
     */
    public function append($label): self
    {
        if (!$label instanceof Host) {
            $label = new Host($label);
        }

        return new self($this->getContent().self::SEPARATOR.$label->getContent());
    }

    /**
     * Returns an instance with its Root label.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * @return self
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
     *
     * @return self
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
     * @param int   $key
     * @param mixed $label
     *
     * @throws InvalidKey If the key is invalid
     *
     * @return self
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

        if (!$label instanceof Host) {
            $label = new Host($label);
        }

        if ($nb_labels === $key) {
            return $this->append($label);
        }

        if (-1 === $key) {
            return $this->prepend($label);
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
     * @param int $key     required key to remove
     * @param int ...$keys remaining keys to remove
     *
     * @throws InvalidKey If the key is invalid
     *
     * @return self
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
        $filter = function ($key) use ($deleted_keys): bool {
            return !in_array($key, $deleted_keys, true);
        };

        return self::createFromLabels(array_filter($this->labels, $filter, ARRAY_FILTER_USE_KEY));
    }
}

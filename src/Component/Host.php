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
use League\Uri\ComponentInterface;
use League\Uri\Exception\InvalidHostLabel;
use League\Uri\Exception\InvalidKey;
use League\Uri\Exception\InvalidUriComponent;
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
final class Host extends Component implements Countable, IteratorAggregate
{
    /**
     * @internal
     */
    const SEPARATOR = '.';

    /**
     * @internal
     */
    const HOST_TYPE = [self::IS_ABSOLUTE => 1, self::IS_RELATIVE => 1];

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
     * @internal
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * General registered name regular expression
     */
    const REGEXP_REGISTERED_NAME = '/(?(DEFINE)
        (?<unreserved>[a-z0-9_~\-])   # . is missing as it is used to separate labels
        (?<sub_delims>[!$&\'()*+,;=])
        (?<encoded>%[A-F0-9]{2})
        (?<reg_name>(?:(?&unreserved)|(?&sub_delims)|(?&encoded))*)
    )
    ^(?:(?&reg_name)\.)*(?&reg_name)\.?$/ix';

    /**
     * @internal
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * IPvFuture regular expression
     */
    const REGEXP_IP_FUTURE = '/^
        v(?<version>[A-F0-9]+)\.
        (?:
            (?<unreserved>[a-z0-9_~\-\.])|
            (?<sub_delims>[!$&\'()*+,;=:])  # also include the : character
        )+
    $/ix';

    /**
     * @internal
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * invalid characters in host regular expression
     */
    const REGEXP_INVALID_HOST_CHARS = '/
        [:\/?#\[\]@ ]  # gen-delims characters as well as the space character
    /ix';

    /**
     * @internal
     */
    const REGEXP_GEN_DELIMS = '/[:\/?#\[\]@]/';

    /**
     * @internal
     */
    const ADDRESS_BLOCK = "\xfe\x80";

    const IS_ABSOLUTE = 1;

    const IS_RELATIVE = 0;

    /**
     * @var array
     */
    private $labels = [];

    /**
     * @var string|null
     */
    private $ip_version;

    /**
     * @var bool
     */
    private $host_as_domain_name = false;

    /**
     * @var bool
     */
    private $has_zone_identifier = false;

    /**
     * @var int
     */
    private $is_absolute = self::IS_RELATIVE;

    /**
     * @codeCoverageIgnore
     */
    private static function supportIdnHost()
    {
        static $idn_support = null;
        $idn_support = $idn_support ?? function_exists('\idn_to_ascii') && defined('\INTL_IDNA_VARIANT_UTS46');
        if (!$idn_support) {
            throw new InvalidUriComponent('IDN host can not be processed. Verify that ext/intl is installed for IDN support and that ICU is at least version 4.6.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties)
    {
        return static::createFromLabels($properties['labels'], $properties['is_absolute']);
    }

    /**
     * Returns a new instance from an array or a traversable object.
     *
     * @param mixed $labels
     * @param int   $type   One of the constant IS_ABSOLUTE or IS_RELATIVE
     *
     * @throws TypeError        If $labels is not iterable
     * @throws InvalidHostLabel If the labels are malformed
     * @throws UnknownType      If the type is not recognized/supported
     *
     * @return self
     */
    public static function createFromLabels($labels, int $type = self::IS_RELATIVE): self
    {
        if (!isset(self::HOST_TYPE[$type])) {
            throw new UnknownType(sprintf('"%s" is an invalid flag', $type));
        }

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

        if ([] === $labels) {
            return new self();
        }

        if ([''] === $labels) {
            return new self('');
        }

        $host = implode(self::SEPARATOR, array_reverse($labels));
        if (self::IS_ABSOLUTE === $type) {
            return new self($host.self::SEPARATOR);
        }

        return new self($host);
    }

    /**
     * Returns a host from an IP address.
     *
     * @param string $ip
     * @param string $version
     *
     * @return self
     */
    public static function createFromIp(string $ip, string $version = ''): self
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return new self($ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return new self('['.$ip.']');
        }

        if (false !== strpos($ip, '%')) {
            list($ipv6, $zoneId) = explode('%', rawurldecode($ip), 2) + [1 => ''];
            return new self('['.$ipv6.'%25'.rawurlencode($zoneId).']');
        }

        return new self('[v'.$version.'.'.$ip.']');
    }

    /**
     * New instance.
     *
     * @param mixed $host
     */
    public function __construct($host = null)
    {
        $parsed = $this->parse($host);
        $this->labels = $parsed['data'];
        $this->ip_version = $parsed['ip_version'];
        $this->has_zone_identifier = $parsed['has_zone_identifier'];
        $this->host_as_domain_name = $parsed['host_as_domain_name'];
        $this->is_absolute = $parsed['is_absolute'];
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
    {
        return [
            'component' => $this->getContent(),
            'is_absolute' => self::IS_ABSOLUTE === $this->is_absolute,
            'ip_version' => $this->ip_version,
            'has_zone_id' => $this->has_zone_identifier,
            'labels' => $this->labels,
        ];
    }

    /**
     * Validates the submitted data.
     *
     * @param mixed $host
     *
     * @throws MalformedUriComponent If the host is invalid
     *
     * @return array
     */
    private function parse($host = null): array
    {
        $host = $this->filterComponent($host);

        if (null === $host) {
            return [
                'data' => [],
                'ip_version' => null,
                'has_zone_identifier' => false,
                'host_as_domain_name' => false,
                'is_absolute' => self::IS_RELATIVE,
            ];
        }

        if ('' === $host) {
            return [
                'data' => [''],
                'ip_version' => null,
                'has_zone_identifier' => false,
                'host_as_domain_name' => false,
                'is_absolute' => self::IS_RELATIVE,
            ];
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return [
                'data' => [$host],
                'ip_version' => '4',
                'has_zone_identifier' => false,
                'host_as_domain_name' => false,
                'is_absolute' => self::IS_RELATIVE,
            ];
        }

        $domain_name = rawurldecode($host);
        if (!preg_match(self::REGEXP_NON_ASCII_PATTERN, $domain_name)) {
            $domain_name = strtolower($domain_name);
        }

        if ($this->isValidDomain($domain_name)) {
            if (false !== strpos($domain_name, 'xn--')) {
                self::supportIdnHost();
                $domain_name = idn_to_utf8($domain_name, 0, INTL_IDNA_VARIANT_UTS46);
            }

            $is_absolute = self::IS_RELATIVE;
            if ('.' === substr($domain_name, -1, 1)) {
                $is_absolute = self::IS_ABSOLUTE;
                $domain_name = substr($domain_name, 0, -1);
            }

            return [
                'data' => array_reverse(explode('.', $domain_name)),
                'ip_version' => null,
                'has_zone_identifier' => false,
                'host_as_domain_name' => true,
                'is_absolute' => $is_absolute,
            ];
        }

        if (preg_match(self::REGEXP_REGISTERED_NAME, $domain_name)) {
            return [
                'data' => [$domain_name],
                'ip_version' => null,
                'has_zone_identifier' => false,
                'host_as_domain_name' => false,
                'is_absolute' => self::IS_RELATIVE,
            ];
        }

        if ('[' !== $host[0] || ']' !== substr($host, -1)) {
            throw new MalformedUriComponent(sprintf('The host `%s` is invalid', $host));
        }

        $ip_host = substr($host, 1, -1);
        if ($this->isValidIpv6Hostname($ip_host)) {
            return [
                'data' => [$host],
                'ip_version' => '6',
                'has_zone_identifier' =>  false !== strpos($ip_host, '%'),
                'host_as_domain_name' => false,
                'is_absolute' => self::IS_RELATIVE,
            ];
        }

        if (preg_match(self::REGEXP_IP_FUTURE, $ip_host, $matches) && !in_array($matches['version'], ['4', '6'], true)) {
            return [
                'data' => [$host],
                'ip_version' => $matches['version'],
                'is_absolute' => self::IS_RELATIVE,
                'has_zone_identifier' => false,
                'host_as_domain_name' => false,
            ];
        }

        throw new MalformedUriComponent(sprintf('The host `%s` is invalid', $host));
    }

    /**
     * Validates an Ipv6 as Host.
     *
     * @see http://tools.ietf.org/html/rfc6874#section-2
     * @see http://tools.ietf.org/html/rfc6874#section-4
     *
     * @param string $ipv6
     *
     * @return bool
     */
    private function isValidIpv6Hostname(string $ipv6): bool
    {
        if (false === ($pos = strpos($ipv6, '%'))) {
            return (bool) filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        }

        $scope = rawurldecode(substr($ipv6, $pos));
        if (preg_match(self::REGEXP_NON_ASCII_PATTERN, $scope) || preg_match(self::REGEXP_GEN_DELIMS, $scope)) {
            return false;
        }

        $ipv6 = substr($ipv6, 0, $pos);

        return filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            && (substr(inet_pton($ipv6) & self::ADDRESS_BLOCK, 0, 2)) === self::ADDRESS_BLOCK;
    }

    /**
     * Validates a domain name as host.
     *
     * @see http://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * @param string $host
     *
     * @return bool
     */
    private function isValidDomain(string $host): bool
    {
        if (preg_match(self::REGEXP_DOMAIN_NAME, $host)) {
            return true;
        }

        if (!preg_match(self::REGEXP_NON_ASCII_PATTERN, $host) || preg_match(self::REGEXP_INVALID_HOST_CHARS, $host)) {
            return false;
        }

        self::supportIdnHost();
        idn_to_ascii($host, 0, INTL_IDNA_VARIANT_UTS46, $arr);

        return 0 === $arr['errors'];
    }

    /**
     * Returns whether or not the host is an IP address.
     *
     * @return bool
     */
    public function isIp(): bool
    {
        return null !== $this->ip_version;
    }

    /**
     * Returns whether or not the host is an IPv4 address.
     *
     * @return bool
     */
    public function isIpv4(): bool
    {
        return '4' === $this->ip_version;
    }

    /**
     * Returns whether or not the host is an IPv6 address.
     *
     * @return bool
     */
    public function isIpv6(): bool
    {
        return '6' === $this->ip_version;
    }

    /**
     * Returns whether or not the host has a ZoneIdentifier.
     *
     * @return bool
     *
     * @see http://tools.ietf.org/html/rfc6874#section-4
     */
    public function hasZoneIdentifier(): bool
    {
        return $this->has_zone_identifier;
    }

    /**
     * Returns whether or not the host is an IPv6 address.
     *
     * @return bool
     */
    public function isIpFuture(): bool
    {
        return !in_array($this->ip_version, [null, '4', '6'], true);
    }

    /**
     * Returns whether or not the host is an IPv6 address.
     *
     * @return bool
     */
    public function isDomain(): bool
    {
        return $this->host_as_domain_name;
    }

    /**
     * Returns whether or not the component is absolute or not.
     *
     * @return bool
     */
    public function isAbsolute(): bool
    {
        return $this->is_absolute === self::IS_ABSOLUTE;
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

    /**
     * {@inheritdoc}
     */
    public function getContent(int $enc_type = self::RFC3986_ENCODING)
    {
        $this->filterEncoding($enc_type);
        if ([] === $this->labels) {
            return null;
        }

        if (null !== $this->ip_version) {
            return $this->labels[0];
        }

        $host = implode(self::SEPARATOR, array_reverse($this->labels));
        if ($enc_type !== self::RFC3987_ENCODING && preg_match(self::REGEXP_NON_ASCII_PATTERN, $host)) {
            self::supportIdnHost();
            $host = idn_to_ascii($host, 0, INTL_IDNA_VARIANT_UTS46);
        }

        if (self::IS_ABSOLUTE !== $this->is_absolute) {
            return $host;
        }

        return $host.self::SEPARATOR;
    }

    /**
     * {@inheritdoc}
     */
    public function getUriComponent(): string
    {
        return (string) $this->getContent();
    }

    /**
     * Retrieve the IP component If the Host is an IP adress.
     *
     * If the host is a not an IP this method will return null
     *
     * @return string|null
     */
    public function getIp()
    {
        if (null === $this->ip_version) {
            return null;
        }

        if ('4' === $this->ip_version) {
            return $this->labels[0];
        }

        $ip = substr($this->labels[0], 1, -1);

        if ('6' !== $this->ip_version) {
            return preg_replace('/^v(?<version>[A-F0-9]+)\./', '', $ip);
        }

        if (false === ($pos = strpos($ip, '%'))) {
            return $ip;
        }

        return substr($ip, 0, $pos).'%'.rawurldecode(substr($ip, $pos + 3));
    }

    /**
     * Returns the IP version.
     *
     * If the host is a not an IP this method will return null
     *
     * @return string|null
     */
    public function getIpVersion()
    {
        return $this->ip_version;
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($value)
    {
        if ($value instanceof ComponentInterface) {
            $value = $value->getContent();
        }

        if ($value === $this->getContent()) {
            return $this;
        }

        return new self($value);
    }

    /**
     * Return an host without its zone identifier according to RFC6874.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance without the host zone identifier according to RFC6874
     *
     * @see http://tools.ietf.org/html/rfc6874#section-4
     *
     * @return self
     */
    public function withoutZoneIdentifier(): self
    {
        if (!$this->has_zone_identifier) {
            return $this;
        }

        list($ipv6, ) = explode('%', substr($this->labels[0], 1, -1));

        return self::createFromIp($ipv6);
    }

    /**
     * Returns a host instance with its Root label.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * @return self
     */
    public function withRootLabel(): self
    {
        if (self::IS_ABSOLUTE === $this->is_absolute || null !== $this->ip_version) {
            return $this;
        }

        $clone = clone $this;
        $clone->is_absolute = self::IS_ABSOLUTE;

        return $clone;
    }

    /**
     * Returns a host instance without the Root label.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * @return self
     */
    public function withoutRootLabel(): self
    {
        if (self::IS_RELATIVE === $this->is_absolute || null !== $this->ip_version) {
            return $this;
        }

        $clone = clone $this;
        $clone->is_absolute = self::IS_RELATIVE;

        return $clone;
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
        if (!$label instanceof self) {
            $label = new self($label);
        }

        return $label->append($this);
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
        if (!$label instanceof self) {
            $label = new self($label);
        }

        return new self(rtrim($this->getContent(), self::SEPARATOR).self::SEPARATOR.ltrim($label->getContent(), self::SEPARATOR));
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
            throw new InvalidKey(sprintf('the given key `%s` is invalid', $key));
        }

        if (0 > $key) {
            $key += $nb_labels;
        }

        if (!$label instanceof self) {
            $label = new self($label);
        }

        if ($nb_labels === $key) {
            return $this->append($label);
        }

        if (-1 === $key) {
            return $label->append($this);
        }

        if (1 === $nb_labels) {
            $label->is_absolute = $this->is_absolute;
            return $label;
        }

        $label = trim($label->getContent(), self::SEPARATOR);
        if ($label === $this->labels[$key]) {
            return $this;
        }

        $labels = $this->labels;
        $labels[$key] = $label;

        return self::createFromLabels($labels, $this->is_absolute);
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
                throw new InvalidKey(sprintf('the key `%s` is invalid', $key));
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

        return self::createFromLabels(array_filter($this->labels, $filter, ARRAY_FILTER_USE_KEY), $this->is_absolute);
    }
}

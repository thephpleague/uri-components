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
use League\Uri\ComponentInterface;
use League\Uri\Exception;
use Traversable;

/**
 * Value object representing a URI Host component.
 *
 * Instances of this interface are considered immutable; all methods that
 * might change state MUST be implemented such that they retain the internal
 * state of the current instance and return an instance that contains the
 * changed state.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 * @see        https://tools.ietf.org/html/rfc3986#section-3.2.2
 */
final class Host implements ComponentInterface, Countable, IteratorAggregate
{
    const IS_ABSOLUTE = 1;

    const IS_RELATIVE = 0;

    /**
     * @internal
     */
    const SEPARATOR = '.';

    /**
     * @internal
     */
    const ENCODING_LIST = [
        self::RFC1738_ENCODING => 1,
        self::RFC3986_ENCODING => 1,
        self::RFC3987_ENCODING => 1,
        self::NO_ENCODING => 1,
    ];

    /**
     * The component Data
     *
     * @var array
     */
    private $labels = [];

    /**
     * Tell the host IP version used
     *
     * @var string|null
     */
    private $ip_version;

    /**
     * Tell whether the Host is a domain name
     *
     * @var bool
     */
    private $host_as_domain_name = false;

    /**
     * Tell whether the Host contains a ZoneID
     *
     * @var bool
     */
    private $has_zone_identifier = false;

    /**
     * Is the object considered absolute
     *
     * @var int
     */
    private $is_absolute = self::IS_RELATIVE;

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return static::createFromLabels($properties['labels'], $properties['is_absolute']);
    }

    /**
     * Returns a new instance from an array or a traversable object.
     *
     * @param mixed $labels
     * @param int   $type   One of the constant IS_ABSOLUTE or IS_RELATIVE
     *
     * @throws Exception If $type is not a recognized constant
     *
     * @return self
     */
    public static function createFromLabels($labels, int $type = self::IS_RELATIVE): self
    {
        static $type_list = [self::IS_ABSOLUTE => 1, self::IS_RELATIVE => 1];
        if (!isset($type_list[$type])) {
            throw new Exception(sprintf('"%s" is an invalid flag', $type));
        }

        if ($labels instanceof Traversable) {
            $labels = iterator_to_array($labels, false);
        }

        if (!is_array($labels)) {
            throw new Exception('the parameters must be iterable');
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
     * New instance
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
     * Validates the submitted data.
     *
     * @param mixed $host
     *
     * @throws Exception If the host is invalid
     *
     * @return array
     */
    private function parse($host = null): array
    {
        if ($host instanceof ComponentInterface) {
            $host = $host->getContent();
        }

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

        if (!is_scalar($host) && !method_exists($host, '__toString')) {
            throw new Exception(sprintf('Expected host to be stringable or null; received %s', gettype($host)));
        }

        static $pattern = '/[\x00-\x1f\x7f]/';
        if (preg_match($pattern, $host)) {
            throw new Exception(sprintf('Invalid fragment string: %s', $host));
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

        $reg_name = strtolower(rawurldecode($host));
        if ($this->isValidDomain($reg_name)) {
            if (false !== strpos($reg_name, 'xn--')) {
                $reg_name = idn_to_utf8($reg_name, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
            }

            $is_absolute = self::IS_RELATIVE;
            if ('.' === substr($reg_name, -1, 1)) {
                $is_absolute = self::IS_ABSOLUTE;
                $reg_name = substr($reg_name, 0, -1);
            }

            return [
                'data' => array_reverse(explode('.', $reg_name)),
                'ip_version' => null,
                'has_zone_identifier' => false,
                'host_as_domain_name' => true,
                'is_absolute' => $is_absolute,
            ];
        }

        if ($this->isValidRegisteredName($reg_name)) {
            return [
                'data' => [$reg_name],
                'ip_version' => null,
                'has_zone_identifier' => false,
                'host_as_domain_name' => false,
                'is_absolute' => self::IS_RELATIVE,
            ];
        }

        if ($this->isValidIpv6Hostname($host)) {
            return [
                'data' => [$host],
                'ip_version' => '6',
                'has_zone_identifier' =>  false !== strpos($host, '%'),
                'host_as_domain_name' => false,
                'is_absolute' => self::IS_RELATIVE,
            ];
        }

        if ($this->isValidIpFuture($host)) {
            preg_match('/^v(?<version>[A-F0-9]+)\./', substr($host, 1, -1), $matches);
            return [
                'data' => [$host],
                'ip_version' => $matches['version'],
                'is_absolute' => self::IS_RELATIVE,
                'has_zone_identifier' => false,
                'host_as_domain_name' => false,
            ];
        }

        throw new Exception(sprintf('The submitted host `%s` is invalid', $host));
    }

    /**
     * Validates an Ipv6 as Host
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
        if ('[' !== ($ipv6[0] ?? '') || ']' !== substr($ipv6, -1)) {
            return false;
        }

        $ipv6 = substr($ipv6, 1, -1);
        if (false === ($pos = strpos($ipv6, '%'))) {
            return (bool) filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        }

        $scope = rawurldecode(substr($ipv6, $pos));
        static $idn_pattern = '/[^\x20-\x7f]/';
        if (preg_match($idn_pattern, $scope)) {
            return false;
        }

        static $gen_delims = '/[:\/?#\[\]@]/';
        if (preg_match($gen_delims, $scope)) {
            return false;
        }

        $ipv6 = substr($ipv6, 0, $pos);
        if (!filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return false;
        }

        static $address_block = "\xfe\x80";

        return substr(inet_pton($ipv6) & $address_block, 0, 2) === $address_block;
    }

    /**
     * Validates an Ip future as host.
     *
     * @see http://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * @param string $ipfuture
     *
     * @return bool
     */
    private function isValidIpFuture(string $ipfuture): bool
    {
        if ('[' !== ($ipfuture[0] ?? '') || ']' !== substr($ipfuture, -1)) {
            return false;
        }

        static $pattern = '/^
            v(?<version>[A-F0-9]+)\.
            (?:
                (?<unreserved>[a-z0-9_~\-\.])|
                (?<sub_delims>[!$&\'()*+,;=:])  # also include the : character
            )+
        $/ix';

        return preg_match($pattern, substr($ipfuture, 1, -1), $matches)
            && !in_array($matches['version'], ['4', '6'], true);
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
        $host = strtolower(rawurldecode($host));
        // Note that unreserved is purposely missing . as it is used to separate labels.
        static $reg_name = '/(?(DEFINE)
                (?<unreserved> [a-z0-9_~\-])
                (?<sub_delims> [!$&\'()*+,;=])
                (?<encoded> %[A-F0-9]{2})
                (?<reg_name> (?:(?&unreserved)|(?&sub_delims)|(?&encoded)){1,63})
            )
            ^(?:(?&reg_name)\.){0,126}(?&reg_name)\.?$/ix';
        static $gen_delims = '/[:\/?#\[\]@ ]/'; // Also includes space.
        if (preg_match($reg_name, $host)) {
            return true;
        }

        if (preg_match($gen_delims, $host)) {
            return false;
        }

        $res = idn_to_ascii($host, 0, INTL_IDNA_VARIANT_UTS46, $arr);

        return 0 === $arr['errors'];
    }

    /**
     * Validates a registered name as host.
     *
     * @see http://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * @param string $host
     *
     * @return bool
     */
    private function isValidRegisteredName(string $host): bool
    {
        static $reg_name = '/^(
            (?<unreserved>[a-z0-9_~\-\.])|
            (?<sub_delims>[!$&\'()*+,;=])|
            (?<encoded>%[A-F0-9]{2})
        )+$/x';
        if (preg_match($reg_name, $host)) {
            return true;
        }

        static $gen_delims = '/[:\/?#\[\]@ ]/'; // Also includes space.
        if (preg_match($gen_delims, $host)) {
            return false;
        }

        $host = idn_to_ascii($host, 0, INTL_IDNA_VARIANT_UTS46, $arr);

        return 0 === $arr['errors'];
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
    {
        return [
            'labels' => $this->labels,
            'is_absolute' => (bool) $this->is_absolute,
        ];
    }

    /**
     * Returns whether or not the host is an IP address
     *
     * @return bool
     */
    public function isIp(): bool
    {
        return null !== $this->ip_version;
    }

    /**
     * Returns whether or not the host is an IPv4 address
     *
     * @return bool
     */
    public function isIpv4(): bool
    {
        return '4' === $this->ip_version;
    }

    /**
     * Returns whether or not the host is an IPv6 address
     *
     * @return bool
     */
    public function isIpv6(): bool
    {
        return '6' === $this->ip_version;
    }

    /**
     * Returns whether or not the host has a ZoneIdentifier
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
     * Returns whether or not the host is an IPv6 address
     *
     * @return bool
     */
    public function isIpFuture(): bool
    {
        return !in_array($this->ip_version, [null, '4', '6'], true);
    }

    /**
     * Returns whether or not the host is an IPv6 address
     *
     * @return bool
     */
    public function isDomain(): bool
    {
        return $this->host_as_domain_name;
    }

    /**
     * Returns whether or not the component is absolute or not
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
     * Returns an array representation of the Host
     *
     * @return array
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /**
     * Retrieves a single host label.
     *
     * Retrieves a single host label. If the label offset has not been set,
     * returns the default value provided.
     *
     * @param int   $offset  the label offset
     * @param mixed $default Default value to return if the offset does not exist.
     *
     * @return mixed
     */
    public function getLabel(int $offset, $default = null)
    {
        if ($offset < 0) {
            $offset += count($this->labels);
        }

        return $this->labels[$offset] ?? $default;
    }

    /**
     * Returns the associated key for each label.
     *
     * If a value is specified only the keys associated with
     * the given value will be returned
     *
     * @param string ...$args the total number of argument given to the method
     *
     * @return array
     */
    public function keys(string ...$args): array
    {
        if (empty($args)) {
            return array_keys($this->labels);
        }

        return array_keys($this->labels, $args[0], true);
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(int $enc_type = self::RFC3986_ENCODING)
    {
        if (!isset(self::ENCODING_LIST[$enc_type])) {
            throw new Exception(sprintf('Unsupported or Unknown Encoding: %s', $enc_type));
        }

        if ([] === $this->labels) {
            return null;
        }

        if (!$this->host_as_domain_name) {
            return $this->labels[0];
        }

        $host = implode(self::SEPARATOR, array_reverse($this->labels));
        static $pattern = '/[^\x20-\x7f]/';
        if ($enc_type !== self::RFC3987_ENCODING && preg_match($pattern, $host)) {
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
    public function __toString()
    {
        return (string) $this->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function getUriComponent(): string
    {
        if (empty($this->labels)) {
            return '';
        }

        return $this->getContent();
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
     * Returns the IP version
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
     * Return an host without its zone identifier according to RFC6874
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

        return new self(substr($this->labels[0], 0, strpos($this->labels[0], '%')).']');
    }

    /**
     * Returns a host instance with its Root label
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * @return self
     */
    public function withRootLabel(): self
    {
        if ($this->is_absolute == self::IS_ABSOLUTE || $this->isIp()) {
            return $this;
        }

        $clone = clone $this;
        $clone->is_absolute = self::IS_ABSOLUTE;

        return $clone;
    }

    /**
     * Returns a host instance without the Root label
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * @return self
     */
    public function withoutRootLabel(): self
    {
        if ($this->is_absolute == self::IS_RELATIVE || $this->isIp()) {
            return $this;
        }

        $clone = clone $this;
        $clone->is_absolute = self::IS_RELATIVE;

        return $clone;
    }

    /**
     * Returns an instance with the specified component prepended
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component with the prepended data
     *
     * @param string $host the component to append
     *
     * @return self
     */
    public function prepend(string $host): self
    {
        $labels = array_merge($this->labels, $this->filterComponent($host));
        if ($this->labels === $labels) {
            return $this;
        }

        return self::createFromLabels($labels, $this->is_absolute);
    }

    /**
     * Returns an instance with the specified component appended
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component with the appended data
     *
     * @param string $host the component to append
     *
     * @return self
     */
    public function append(string $host): self
    {
        $labels = array_merge($this->filterComponent($host), $this->labels);
        if ($this->labels === $labels) {
            return $this;
        }

        return self::createFromLabels($labels, $this->is_absolute);
    }

    /**
     * Filter the component to append or prepend
     *
     * @param string $component
     *
     * @return array
     */
    private function filterComponent(string $component): array
    {
        if ('' === $component) {
            return [];
        }

        if ('.' !== $component && '.' == substr($component, -1)) {
            $component = substr($component, 0, -1);
        }

        return $this->parse($component)['data'];
    }

    /**
     * Returns an instance with the modified label
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component with the replaced data
     *
     * @param int    $offset the label offset to remove and replace by the given component
     * @param string $host   the component added
     *
     * @return self
     */
    public function replaceLabel(int $offset, string $host): self
    {
        $nb_elements = count($this->labels);
        $offset = filter_var($offset, FILTER_VALIDATE_INT, ['options' => ['min_range' => - $nb_elements, 'max_range' => $nb_elements - 1]]);
        if (false === $offset) {
            return $this;
        }

        if ($offset < 0) {
            $offset = $nb_elements + $offset;
        }

        $labels = array_merge(
            array_slice($this->labels, 0, $offset),
            $this->parse($host)['data'],
            array_slice($this->labels, $offset + 1)
        );

        if ($labels === $this->labels) {
            return $this;
        }

        return self::createFromLabels($labels, $this->is_absolute);
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
    public function withoutLabels(array $offsets): self
    {
        if (array_filter($offsets, 'is_int') !== $offsets) {
            throw new Exception('the list of keys must contain integer only values');
        }

        $data = $this->labels;
        foreach ($this->filterOffsets(...$offsets) as $offset) {
            unset($data[$offset]);
        }

        if ($data === $this->labels) {
            return $this;
        }

        return self::createFromLabels($data, $this->is_absolute);
    }

    /**
     * Filter Offset list
     *
     * @param int ...$offsets list of keys to remove from the collection
     *
     * @return int[]
     */
    private function filterOffsets(int ...$offsets)
    {
        $nb_elements = count($this->labels);
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
}

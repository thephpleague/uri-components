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

use League\Uri\PublicSuffix\Cache;
use League\Uri\PublicSuffix\CurlHttpClient;
use League\Uri\PublicSuffix\ICANNSectionManager;
use League\Uri\PublicSuffix\Rules;
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
class Host extends AbstractHierarchicalComponent implements ComponentInterface
{
    /** @deprecated 1.8.0 will be removed in the next major point release */
    const LOCAL_LINK_PREFIX = '1111111010';

    const INVALID_ZONE_ID_CHARS = "?#@[]\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F\x7F";

    /** @deprecated 1.8.0 will be removed in the next major point release */
    const STARTING_LABEL_CHARS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /** @deprecated 1.8.0 will be removed in the next major point release */
    const SUB_DELIMITERS = '!$&\'()*+,;=';

    /**
     * Tell whether the Host is a domain name.
     *
     * @var bool
     */
    protected $host_as_domain_name = false;

    /**
     * Tell whether the Host is an IPv4.
     *
     * @deprecated 1.8.0 No longer used by internal code and not recommend
     *
     * @var bool
     */
    protected $host_as_ipv4 = false;

    /**
     * Tell whether the Host is an IPv6.
     *
     * @deprecated 1.8.0 No longer used by internal code and not recommend
     *
     * @var bool
     */
    protected $host_as_ipv6 = false;

    /**
     * Tell the host IP version used.
     *
     * @var string|null
     */
    protected $ip_version;

    /**
     * Tell whether the Host contains a ZoneID.
     *
     * @var bool
     */
    protected $has_zone_identifier = false;

    /**
     * Host separator.
     *
     * @var string
     */
    protected static $separator = '.';

    /**
     * Hostname public info.
     *
     * @var array
     */
    protected $hostname = [];

    /**
     * @var Rules|null
     */
    protected $resolver;

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        $host = static::createFromLabels(
            $properties['data'],
            $properties['is_absolute'],
            $properties['resolver'] ?? null
        );

        $host->hostname = $properties['hostname'];

        return $host;
    }

    /**
     * Returns a new instance from an array or a traversable object.
     *
     * @param Traversable|array $data The segments list
     * @param int               $type One of the constant IS_ABSOLUTE or IS_RELATIVE
     *
     * @throws Exception If $type is not a recognized constant
     *
     * @return static
     */
    public static function createFromLabels($data, int $type = self::IS_RELATIVE, Rules $resolver = null): self
    {
        static $type_list = [self::IS_ABSOLUTE => 1, self::IS_RELATIVE => 1];

        $data = static::filterIterable($data);
        if (!isset($type_list[$type])) {
            throw Exception::fromInvalidFlag($type);
        }

        if ([] === $data) {
            return new static(null, $resolver);
        }

        if ([''] === $data) {
            return new static('', $resolver);
        }

        $host = implode(static::$separator, array_reverse($data));
        if (self::IS_ABSOLUTE === $type) {
            return new static($host.static::$separator, $resolver);
        }

        return new static($host, $resolver);
    }

    /**
     * Returns a host from an IP address.
     *
     *
     * @return static
     */
    public static function createFromIp(string $ip, Rules $resolver = null): self
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return new static($ip, $resolver);
        }

        if (false !== strpos($ip, '%')) {
            list($ipv6, $zoneId) = explode('%', rawurldecode($ip), 2) + [1 => ''];
            $ip = $ipv6.'%25'.rawurlencode($zoneId);
        }

        return new static('['.$ip.']', $resolver);
    }

    /**
     * New instance.
     *
     */
    public function __construct(string $host = null, Rules $resolver = null)
    {
        $parsed = $this->parseHost($host);
        $this->data = $parsed['data'];
        $this->ip_version = $parsed['ip_version'];
        $this->has_zone_identifier = $parsed['has_zone_identifier'];
        $this->host_as_domain_name = $parsed['host_as_domain_name'];
        $this->is_absolute = $parsed['is_absolute'];
        $this->host_as_ipv4 = '4' === $this->ip_version;
        $this->host_as_ipv6 = '6' === $this->ip_version;
        $this->resolver = $resolver;
    }

    /**
     * Validates the submitted data.
     *
     *
     * @throws Exception If the host is invalid
     *
     */
    protected function parseHost(string $host = null): array
    {
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

        $host = $this->validateString($host);
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
     * Validates an Ipv6 as Host.
     *
     * @see http://tools.ietf.org/html/rfc6874#section-2
     * @see http://tools.ietf.org/html/rfc6874#section-4
     *
     *
     */
    protected function isValidIpv6Hostname(string $ipv6): bool
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
     *
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
     *
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
     *
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
        $this->lazyloadInfo();

        return array_merge([
            'component' => $this->getContent(),
            'labels' => $this->data,
            'is_absolute' => (bool) $this->is_absolute,
        ], $this->hostname);
    }

    /**
     * Resolve domain name information.
     */
    protected function lazyloadInfo()
    {
        if (!empty($this->hostname)) {
            return;
        }

        if (!$this->host_as_domain_name) {
            $this->hostname = $this->hostname = [
                'isPublicSuffixValid' => false,
                'publicSuffix' => '',
                'registrableDomain' => '',
                'subDomain' => '',
            ];

            return;
        }

        $host = $this->getContent();
        if ($this->isAbsolute()) {
            $host = substr($host, 0, -1);
        }

        $this->resolver = $this->resolver ?? (new ICANNSectionManager(new Cache(), new CurlHttpClient()))->getRules();
        $domain = $this->resolver->resolve($host);

        $this->hostname = [
            'isPublicSuffixValid' => $domain->isValid(),
            'publicSuffix' => (string) $domain->getPublicSuffix(),
            'registrableDomain' => (string) $domain->getRegistrableDomain(),
            'subDomain' => (string) $domain->getSubDomain(),
        ];
    }

    /**
     * Return the host public suffix.
     *
     */
    public function getPublicSuffix(): string
    {
        $this->lazyloadInfo();

        return $this->hostname['publicSuffix'];
    }

    /**
     * Return the host registrable domain.
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated 1.5.0 Typo fix in name
     * @see        Host::getRegistrableDomain
     *
     */
    public function getRegisterableDomain(): string
    {
        return $this->getRegistrableDomain();
    }

    /**
     * Return the host registrable domain.
     *
     */
    public function getRegistrableDomain(): string
    {
        $this->lazyloadInfo();

        return $this->hostname['registrableDomain'];
    }

    /**
     * Return the hostname subdomain.
     *
     */
    public function getSubDomain(): string
    {
        $this->lazyloadInfo();

        return $this->hostname['subDomain'];
    }

    /**
     * Tell whether the current public suffix is valid.
     *
     */
    public function isPublicSuffixValid(): bool
    {
        $this->lazyloadInfo();

        return $this->hostname['isPublicSuffixValid'];
    }

    /**
     * {@inheritdoc}
     */
    public function isNull(): bool
    {
        return null === $this->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        return '' == $this->getContent();
    }

    /**
     * Returns whether or not the host is an IP address.
     *
     */
    public function isIp(): bool
    {
        return null !== $this->ip_version;
    }

    /**
     * Returns whether or not the host is an IPv4 address.
     *
     */
    public function isIpv4(): bool
    {
        return '4' === $this->ip_version;
    }

    /**
     * Returns whether or not the host is an IPv6 address.
     *
     */
    public function isIpv6(): bool
    {
        return '6' === $this->ip_version;
    }

    /**
     * Returns whether or not the host has a ZoneIdentifier.
     *
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
     */
    public function isIpFuture(): bool
    {
        return !in_array($this->ip_version, [null, '4', '6'], true);
    }

    /**
     * Returns whether or not the host is an IPv6 address.
     *
     */
    public function isDomain(): bool
    {
        return $this->host_as_domain_name;
    }

    /**
     * Returns an array representation of the Host.
     *
     */
    public function getLabels(): array
    {
        return $this->data;
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
     */
    public function getLabel(int $offset, $default = null)
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

        return array_keys($this->data, $this->toIdn($this->validateString($args[0])), true);
    }

    /**
     * Convert domain name to IDNA ASCII form.
     *
     * Conversion is done only if the label contains the ACE prefix 'xn--'
     * if a '%' sub delimiter is detected the label MUST be rawurldecode prior to
     * making the conversion
     *
     *
     * @return string|false
     */
    protected function toIdn(string $label)
    {
        $label = rawurldecode($label);
        if (0 !== strpos($label, 'xn--')) {
            return $label;
        }

        return idn_to_utf8($label, 0, INTL_IDNA_VARIANT_UTS46);
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(int $enc_type = self::RFC3986_ENCODING)
    {
        $this->assertValidEncoding($enc_type);

        if ([] === $this->data) {
            return null;
        }

        if (!$this->host_as_domain_name) {
            return $this->data[0];
        }

        $host = implode(static::$separator, array_reverse($this->data));
        if ($enc_type !== self::RFC3987_ENCODING) {
            $host = $this->toAscii($host);
        }

        if (self::IS_ABSOLUTE !== $this->is_absolute) {
            return $host;
        }

        return $host.static::$separator;
    }

    /**
     * Convert a registered name label to its IDNA ASCII form.
     *
     * Conversion is done only if the label contains none valid label characters
     * if a '%' sub delimiter is detected the label MUST be rawurldecode prior to
     * making the conversion
     *
     *
     * @return string|false
     */
    protected function toAscii(string $label)
    {
        static $pattern = '/[^\x20-\x7f]/';
        if (!preg_match($pattern, $label)) {
            return $label;
        }

        return idn_to_ascii($label, 0, INTL_IDNA_VARIANT_UTS46, $arr);
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
            return $this->data[0];
        }

        $ip = substr($this->data[0], 1, -1);
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
    public function withContent($value): ComponentInterface
    {
        if ($value === $this->getContent()) {
            return $this;
        }

        $new = new static($value, $this->resolver);
        if (!empty($this->hostname)) {
            $new->lazyloadInfo();
        }

        return $new;
    }

    /**
     * Return an host without its zone identifier according to RFC6874.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance without the host zone identifier according to RFC6874
     *
     * @see http://tools.ietf.org/html/rfc6874#section-4
     *
     * @return static
     */
    public function withoutZoneIdentifier(): self
    {
        if (!$this->has_zone_identifier) {
            return $this;
        }

        $new = new static(substr($this->data[0], 0, strpos($this->data[0], '%')).']', $this->resolver);
        $new->hostname = $this->hostname;

        return $new;
    }

    /**
     * Returns a host instance with its Root label.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * @return static
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
     * Returns a host instance without the Root label.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * @return static
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
     * Returns an instance with the specified component prepended.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component with the prepended data
     *
     * @param string $host the component to append
     *
     * @return static
     */
    public function prepend(string $host): self
    {
        $labels = array_merge($this->data, $this->filterComponent($host));
        if ($this->data === $labels) {
            return $this;
        }

        $new = self::createFromLabels($labels, $this->is_absolute, $this->resolver);
        if (!empty($this->hostname)) {
            $new->lazyloadInfo();
        }

        return $new;
    }

    /**
     * Returns an instance with the specified component appended.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component with the appended data
     *
     * @param string $host the component to append
     *
     * @return static
     */
    public function append(string $host): self
    {
        $labels = array_merge($this->filterComponent($host), $this->data);
        if ($this->data === $labels) {
            return $this;
        }

        $new = self::createFromLabels($labels, $this->is_absolute, $this->resolver);
        if (!empty($this->hostname)) {
            $new->lazyloadInfo();
        }

        return $new;
    }

    /**
     * Filter the component to append or prepend.
     *
     *
     */
    protected function filterComponent(string $component): array
    {
        $component = $this->validateString($component);
        if ('' === $component) {
            return [];
        }

        if ('.' !== $component && '.' == substr($component, -1)) {
            $component = substr($component, 0, -1);
        }

        return $this->parseHost($component)['data'];
    }

    /**
     * Returns an instance with the modified label.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component with the replaced data
     *
     * @param int    $offset the label offset to remove and replace by the given component
     * @param string $host   the component added
     *
     * @return static
     */
    public function replaceLabel(int $offset, string $host): self
    {
        $labels = $this->replace($offset, $host);
        if ($labels === $this->data) {
            return $this;
        }

        $new = self::createFromLabels($labels, $this->is_absolute, $this->resolver);
        if (!empty($this->hostname)) {
            $new->lazyloadInfo();
        }

        return $new;
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
    public function withoutLabels(array $offsets): self
    {
        $data = $this->delete($offsets);
        if ($data === $this->data) {
            return $this;
        }

        $new = self::createFromLabels($data, $this->is_absolute, $this->resolver);
        if (!empty($this->hostname)) {
            $new->lazyloadInfo();
        }

        return $new;
    }

    /**
     * Returns an instance with the specified registerable domain added.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component with the new registerable domain
     *
     * @param string $host the registerable domain to add
     *
     * @return static
     */
    public function withPublicSuffix(string $host): self
    {
        if ('' === $host) {
            $host = null;
        }

        $source = $this->getContent();
        if ('' == $source) {
            return new static($host, $this->resolver);
        }

        $public_suffix = $this->getPublicSuffix();
        if ('.' === ($host[0] ?? '') || '.' === substr((string) $host, -1)) {
            throw new Exception(sprintf('The submitted host `%s` is invalid', $host));
        }

        $new = $this->parseHost($host)['data'];
        if (implode('.', array_reverse($new)) === $public_suffix) {
            return $this;
        }

        $offset = 0;
        if ('' != $public_suffix) {
            $offset = count(explode('.', $public_suffix));
        }

        $new = self::createFromLabels(
            array_merge($new, array_slice($this->data, $offset)),
            $this->is_absolute,
            $this->resolver
        );

        $new->lazyloadInfo();

        return $new;
    }

    /**
     * validate the submitted data.
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated 1.8.0 internal method not used anymore
     *
     * @codeCoverageIgnore
     *
     *
     * @throws Exception If the host is invalid
     *
     */
    protected function validate(string $host = null): array
    {
        if (null === $host) {
            return [];
        }

        if ('' === $host) {
            return [''];
        }

        if ('.' === $host[0] || '.' === substr($host, -1)) {
            throw new Exception(sprintf('The submitted host `%s` is invalid', $host));
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->host_as_ipv4 = true;

            return [$host];
        }

        if ($this->isValidIpv6Hostname($host)) {
            $this->host_as_ipv6 = true;
            $this->has_zone_identifier = false !== strpos($host, '%');

            return [$host];
        }

        if ($this->isValidIpFuture($host)) {
            return [$host];
        }

        $reg_name = strtolower(rawurldecode($host));

        if ($this->isValidDomain($reg_name)) {
            if (false !== strpos($reg_name, 'xn--')) {
                $reg_name = idn_to_utf8($reg_name, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
            }

            return array_reverse(explode('.', $reg_name));
        }

        if ($this->isValidRegisteredName($reg_name)) {
            return [$reg_name];
        }

        throw new Exception(sprintf('The submitted host `%s` is invalid', $host));
    }

    /**
     * validate the submitted data.
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated 1.8.0 internal method not used anymore
     *
     * @codeCoverageIgnore
     *
     *
     * @throws Exception If the host is invalid
     *
     */
    protected function normalizeLabels(string $host = null): array
    {
        trigger_error(
            self::class.'::'.__METHOD__.' is deprecated and will be removed in the next major point release',
            E_USER_DEPRECATED
        );

        if (null === $host) {
            return [];
        }

        if ('' === $host) {
            return [''];
        }

        if ('.' === $host[0] || '.' === substr($host, -1)) {
            throw new Exception(sprintf('The submitted host `%s` is invalid', $host));
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return [$host];
        }

        if ($this->isValidIpv6Hostname($host)) {
            return [$host];
        }

        if ($this->isValidIpFuture($host)) {
            return [$host];
        }

        $reg_name = strtolower(rawurldecode($host));

        if ($this->isValidDomain($reg_name)) {
            if (false !== strpos($reg_name, 'xn--')) {
                $reg_name = idn_to_utf8($reg_name, 0, INTL_IDNA_VARIANT_UTS46);
            }

            return array_reverse(explode('.', $reg_name));
        }

        if ($this->isValidRegisteredName($reg_name)) {
            return [$reg_name];
        }

        throw new Exception(sprintf('The submitted host `%s` is invalid', $host));
    }

    /**
     * Returns an instance with the specified registerable domain added.
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated 1.5.0 Typo fix in name
     * @see        Host::withRegistrableDomain
     *
     * @param string $host the registerable domain to add
     *
     * @return static
     */
    public function withRegisterableDomain(string $host): self
    {
        return $this->withRegistrableDomain($host);
    }

    /**
     * Returns an instance with the specified registerable domain added.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component with the new registerable domain
     *
     * @param string $host the registerable domain to add
     *
     * @return static
     */
    public function withRegistrableDomain(string $host): self
    {
        if ('' === $host) {
            $host = null;
        }

        $source = $this->getContent();
        if ('' == $source) {
            return new static($host, $this->resolver);
        }

        $registerable_domain = $this->getRegistrableDomain();

        if ('.' === ($host[0] ?? '') || '.' === substr((string) $host, -1)) {
            throw new Exception(sprintf('The submitted host `%s` is invalid', $host));
        }
        $new = $this->parseHost($host)['data'];
        if (implode('.', array_reverse($new)) === $registerable_domain) {
            return $this;
        }

        $offset = 0;
        if ('' != $registerable_domain) {
            $offset = count(explode('.', $registerable_domain));
        }

        $new = self::createFromLabels(
            array_merge($new, array_slice($this->data, $offset)),
            $this->is_absolute,
            $this->resolver
        );
        $new->lazyloadInfo();

        return $new;
    }

    /**
     * Returns an instance with the specified sub domain added.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component with the new sud domain
     *
     * @param string $host the subdomain to add
     *
     * @return static
     */
    public function withSubDomain(string $host): self
    {
        if ('' === $host) {
            $host = null;
        }

        $source = $this->getContent();
        if ('' == $source) {
            return new static($host, $this->resolver);
        }

        $subdomain = $this->getSubDomain();
        if ('.' === ($host[0] ?? '') || '.' === substr((string) $host, -1)) {
            throw new Exception(sprintf('The submitted host `%s` is invalid', $host));
        }

        $new = $this->parseHost($host)['data'];
        if (implode('.', array_reverse($new)) === $subdomain) {
            return $this;
        }

        $offset = count($this->data);
        if ('' != $subdomain) {
            $offset -= count(explode('.', $subdomain));
        }

        $new = self::createFromLabels(
            array_merge(array_slice($this->data, 0, $offset), $new),
            $this->is_absolute,
            $this->resolver
        );
        $new->lazyloadInfo();

        return $new;
    }

    /**
     * Returns an instance with a different domain resolver.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains a different domain resolver, and update the
     * host domain information.
     *
     *
     * @return static
     */
    public function withDomainResolver(Rules $resolver = null): self
    {
        if ($resolver == $this->resolver) {
            return $this;
        }

        $clone = clone $this;
        $clone->resolver = $resolver;
        if (!empty($this->hostname)) {
            $clone->lazyloadInfo();
        }

        return $clone;
    }

    /**
     * Returns whether the hostname is valid.
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated 1.8.0 No longer used by internal code and not recommend
     *
     * @codeCoverageIgnore
     *
     *
     * A valid registered name MUST:
     *
     * - contains at most 127 subdomains deep
     * - be limited to 255 octets in length
     *
     * @see https://en.wikipedia.org/wiki/Subdomain
     * @see https://tools.ietf.org/html/rfc1035#section-2.3.4
     * @see https://blogs.msdn.microsoft.com/oldnewthing/20120412-00/?p=7873/
     *
     *
     */
    protected function isValidHostname(string $host): bool
    {
        $labels = array_map([$this, 'toAscii'], explode('.', $host));

        return 127 > count($labels)
            && 253 > strlen(implode('.', $labels))
            && $labels === array_filter($labels, [$this, 'isValidLabel']);
    }

    /**
     * Returns whether the registered name label is valid.
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated 1.8.0 No longer used by internal code and not recommend
     *
     * @codeCoverageIgnore
     *
     * A valid registered name label MUST:
     *
     * - not be empty
     * - contain 63 characters or less
     * - conform to the following ABNF
     *
     * reg-name = *( unreserved / pct-encoded / sub-delims )
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * @param string $label
     *
     */
    protected function isValidLabel($label): bool
    {
        return is_string($label)
            && '' != $label
            && 63 >= strlen($label)
            && strlen($label) == strspn($label, self::STARTING_LABEL_CHARS.'-_~'.self::SUB_DELIMITERS);
    }

    /**
     * Set the FQDN property.
     *
     * @deprecated 1.8.0 internal method no longer in use
     *
     * @codeCoverageIgnore
     *
     *
     * @return string|null
     */
    protected function setIsAbsolute(string $str = null)
    {
        if (null === $str) {
            return $str;
        }

        $this->is_absolute = self::IS_RELATIVE;
        if ('.' === substr($str, -1, 1)) {
            $this->is_absolute = self::IS_ABSOLUTE;
            return substr($str, 0, -1);
        }

        return $str;
    }

    /**
     * Returns a formatted host string.
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated 1.8.0 No longer used by internal code and not recommend
     *
     * @codeCoverageIgnore
     *
     * @param array $data The segments list
     *
     */
    protected static function format(array $data, int $type): string
    {
        $hostname = implode(static::$separator, array_reverse($data));
        if (self::IS_ABSOLUTE === $type) {
            return $hostname.static::$separator;
        }

        return $hostname;
    }
}

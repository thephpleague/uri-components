<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.7.0
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
    const LOCAL_LINK_PREFIX = '1111111010';

    const INVALID_ZONE_ID_CHARS = "?#@[]\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F\x7F";

    const STARTING_LABEL_CHARS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    const SUB_DELIMITERS = '!$&\'()*+,;=';

    /**
     * Tell whether the Host is an IPv4
     *
     * @var bool
     */
    protected $host_as_ipv4 = false;

    /**
     * Tell whether the Host is an IPv6
     *
     * @var bool
     */
    protected $host_as_ipv6 = false;

    /**
     * Tell whether the Host contains a ZoneID
     *
     * @var bool
     */
    protected $has_zone_identifier = false;

    /**
     * Host separator
     *
     * @var string
     */
    protected static $separator = '.';

    /**
     * Hostname public info
     *
     * @var array
     */
    protected $hostname;

    /**
     * @var Rules
     */
    protected $resolver;

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return static::createFromLabels(
            $properties['data'],
            $properties['is_absolute'],
            $properties['resolver'] ?? self::getResolver()
        );
    }

    /**
     * return a new instance from an array or a traversable object
     *
     * @param Traversable|array $data     The segments list
     * @param int               $type     One of the constant IS_ABSOLUTE or IS_RELATIVE
     * @param null|Rules        $resolver
     *
     * @throws Exception If $type is not a recognized constant
     *
     * @return static
     */
    public static function createFromLabels($data, int $type = self::IS_RELATIVE, Rules $resolver = null): self
    {
        static $type_list = [self::IS_ABSOLUTE => 1, self::IS_RELATIVE => 1];

        $resolver = $resolver ?? self::getResolver();

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

        return new static(static::format($data, $type), $resolver);
    }

    /**
     * Get the default Domain Resolver
     *
     * @return Rules
     */
    protected static function getResolver(): Rules
    {
        return (new ICANNSectionManager(new Cache(), new CurlHttpClient()))->getRules();
    }

    /**
     * Return a formatted host string
     *
     * @param array $data The segments list
     * @param int   $type
     *
     * @return string
     */
    protected static function format(array $data, int $type): string
    {
        $hostname = implode(static::$separator, array_reverse($data));
        if (self::IS_ABSOLUTE === $type) {
            return $hostname.static::$separator;
        }

        return $hostname;
    }

    /**
     * Return a host from an IP address
     *
     * @param string     $ip
     * @param null|Rules $resolver
     *
     * @throws Exception If the IP is invalid or unrecognized
     *
     * @return static
     */
    public static function createFromIp(string $ip, Rules $resolver = null): self
    {
        $resolver = $resolver ?? self::getResolver();
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return new static($ip, $resolver);
        }

        if (false !== strpos($ip, '%')) {
            list($ipv6, $zoneId) = explode('%', rawurldecode($ip), 2) + ['', null];
            $ip = $ipv6.'%25'.rawurlencode((string) $zoneId);

            return new static('['.$ip.']', $resolver);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return new static('['.$ip.']', $resolver);
        }

        throw new Exception(sprintf('Please verify the submitted IP: %s', $ip));
    }

    /**
     * New instance
     *
     * @param null|string $host
     * @param null|Rules  $resolver
     */
    public function __construct(string $host = null, Rules $resolver = null)
    {
        $this->data = $this->validate($this->setIsAbsolute($host));
        $this->resolver = $resolver ?? self::getResolver();
        $this->hostname = $this->getDomainInfo();
    }

    /**
     * set the FQDN property
     *
     * @param string $str
     *
     * @return string|null
     */
    protected function setIsAbsolute(string $str = null)
    {
        if (in_array($str, [null, '.'], true)) {
            return $str;
        }

        $str = $this->validateString($str);
        $this->is_absolute = self::IS_RELATIVE;
        if ('.' === mb_substr($str, -1, 1, 'UTF-8')) {
            $this->is_absolute = self::IS_ABSOLUTE;
            return mb_substr($str, 0, -1, 'UTF-8');
        }

        return $str;
    }

    /**
     * validate the submitted data
     *
     * @param string|null $host
     *
     * @throws Exception If the host is invalid
     *
     * @return array
     */
    protected function validate(string $host = null): array
    {
        if (null === $host) {
            return [];
        }

        if ('' === $host) {
            return [''];
        }

        if ('.' === $host[0]) {
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

        if ($this->isValidHostname($host)) {
            return array_reverse(array_map([$this, 'toIdn'], explode('.', mb_strtolower($host, 'UTF-8'))));
        }

        throw new Exception(sprintf('The submitted host `%s` is invalid', $host));
    }

    /**
     * Resolve domain name information
     */
    protected function getDomainInfo()
    {
        $host = (string) $this;
        if ($this->isAbsolute()) {
            $host = substr($host, 0, -1);
        }

        $domain = $this->resolver->resolve($host);

        return [
            'isPublicSuffixValid' => $domain->isValid(),
            'publicSuffix' => (string) $domain->getPublicSuffix(),
            'registrableDomain' => (string) $domain->getRegistrableDomain(),
            'subDomain' => (string) $domain->getSubDomain(),
        ];
    }

    /**
     * validate an Ipv6 Hostname
     *
     * @see http://tools.ietf.org/html/rfc6874#section-2
     * @see http://tools.ietf.org/html/rfc6874#section-4
     *
     * @param string $ipv6
     *
     * @return bool
     */
    protected function isValidIpv6Hostname(string $ipv6): bool
    {
        if (false === strpos($ipv6, '[') || false === strpos($ipv6, ']')) {
            return false;
        }

        $ipv6 = substr($ipv6, 1, -1);
        if (false === ($pos = strpos($ipv6, '%'))) {
            return (bool) filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        }

        $scope_raw = substr($ipv6, $pos);
        if (strlen($scope_raw) !== mb_strlen($scope_raw)) {
            return false;
        }

        $scope = rawurldecode($scope_raw);
        if (strlen($scope) !== strcspn($scope, self::INVALID_ZONE_ID_CHARS)) {
            return false;
        }

        $ipv6 = substr($ipv6, 0, $pos);
        if (!filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return false;
        }

        $reducer = function ($carry, $char) {
            return $carry.str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        };

        $res = array_reduce(str_split(unpack('A16', inet_pton($ipv6))[1]), $reducer, '');

        return substr($res, 0, 10) === self::LOCAL_LINK_PREFIX;
    }

    /**
     * Returns whether the hostname is valid
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
     * @param string $host
     *
     * @return bool
     */
    protected function isValidHostname(string $host): bool
    {
        $labels = array_map([$this, 'toAscii'], explode('.', $host));

        return 127 > count($labels)
            && 253 > strlen(implode('.', $labels))
            && $labels === array_filter($labels, [$this, 'isValidLabel']);
    }

    /**
     * Convert a registered name label to its IDNA ASCII form.
     *
     * Conversion is done only if the label contains none valid label characters
     * if a '%' sub delimiter is detected the label MUST be rawurldecode prior to
     * making the conversion
     *
     * @param string $label
     *
     * @return string|false
     */
    protected function toAscii(string $label)
    {
        if (false !== strpos($label, '%')) {
            $label = rawurldecode($label);
        }

        if (strlen($label) === strspn($label, static::STARTING_LABEL_CHARS.'-')) {
            return $label;
        }

        return idn_to_ascii($label, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
    }

    /**
     * Convert domain name to IDNA ASCII form.
     *
     * Conversion is done only if the label contains the ACE prefix 'xn--'
     * if a '%' sub delimiter is detected the label MUST be rawurldecode prior to
     * making the conversion
     *
     * @param string $label
     *
     * @return string|false
     */
    protected function toIdn(string $label)
    {
        if (false !== strpos($label, '%')) {
            $label = rawurldecode($label);
        }

        if (0 !== stripos($label, 'xn--')) {
            return $label;
        }

        return idn_to_utf8($label, IDNA_NONTRANSITIONAL_TO_UNICODE, INTL_IDNA_VARIANT_UTS46);
    }

    /**
     * Returns whether the registered name label is valid
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
     * @return bool
     */
    protected function isValidLabel($label): bool
    {
        return is_string($label)
            && '' != $label
            && 63 >= strlen($label)
            && strlen($label) == strspn($label, self::STARTING_LABEL_CHARS.'-_~'.self::SUB_DELIMITERS);
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
    {
        return array_merge([
            'component' => $this->getContent(),
            'labels' => $this->data,
            'is_absolute' => (bool) $this->is_absolute,
        ], $this->hostname);
    }

    /**
     * Return the host public suffix
     *
     * @return string
     */
    public function getPublicSuffix(): string
    {
        return $this->hostname['publicSuffix'];
    }

    /**
     * Return the host registrable domain.
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated deprecated since version 1.5.0
     * @see        Host::getRegistrableDomain
     *
     * @return string
     */
    public function getRegisterableDomain(): string
    {
        return $this->getRegistrableDomain();
    }

    /**
     * Return the host registrable domain
     *
     * @return string
     */
    public function getRegistrableDomain(): string
    {
        return $this->hostname['registrableDomain'];
    }

    /**
     * Return the hostname subdomain
     *
     * @return string
     */
    public function getSubDomain(): string
    {
        return $this->hostname['subDomain'];
    }

    /**
     * Tell whether the current public suffix is valid
     *
     * @return bool
     */
    public function isPublicSuffixValid(): bool
    {
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
     * Returns whether or not the host is an IP address
     *
     * @return bool
     */
    public function isIp(): bool
    {
        return $this->host_as_ipv4 || $this->host_as_ipv6;
    }

    /**
     * Returns whether or not the host is an IPv4 address
     *
     * @return bool
     */
    public function isIpv4(): bool
    {
        return $this->host_as_ipv4;
    }

    /**
     * Returns whether or not the host is an IPv6 address
     *
     * @return bool
     */
    public function isIpv6(): bool
    {
        return $this->host_as_ipv6;
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
     * Returns an array representation of the Host
     *
     * @return array
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
     * @return mixed
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
     * @return array
     */
    public function keys(...$args): array
    {
        if (empty($args)) {
            return array_keys($this->data);
        }

        return array_keys($this->data, $this->toIdn($this->validateString($args[0])), true);
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

        if ($this->isIp()) {
            return $this->data[0];
        }

        if ($enc_type != self::RFC3987_ENCODING) {
            return $this->format(array_map([$this, 'toAscii'], $this->data), $this->is_absolute);
        }

        return $this->format($this->data, $this->is_absolute);
    }

    /**
     * Retrieve the IP component If the Host is an IP adress.
     *
     * If the host is a domain name this method will return null
     *
     * @return string|null
     */
    public function getIp()
    {
        if ($this->host_as_ipv4) {
            return $this->data[0];
        }

        if (!$this->host_as_ipv6) {
            return null;
        }

        $ip = substr($this->data[0], 1, -1);
        if (false === ($pos = strpos($ip, '%'))) {
            return $ip;
        }

        return substr($ip, 0, $pos).'%'.rawurldecode(substr($ip, $pos + 3));
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($value): ComponentInterface
    {
        if ($value === $this->getContent()) {
            return $this;
        }

        return new static($value, $this->resolver);
    }

    /**
     * Return an host without its zone identifier according to RFC6874
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

        return new static(substr($this->data[0], 0, strpos($this->data[0], '%')).']', $this->resolver);
    }

    /**
     * Returns a host instance with its Root label
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
     * Returns a host instance without the Root label
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * @return static
     */
    public function withoutRootlabel(): self
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
     * @return static
     */
    public function prepend(string $host): self
    {
        $labels = array_merge($this->data, $this->filterComponent($host));
        if ($this->data === $labels) {
            return $this;
        }

        return static::createFromLabels($labels, $this->is_absolute, $this->resolver);
    }

    /**
     * Returns an instance with the specified component appended
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

        return static::createFromLabels($labels, $this->is_absolute, $this->resolver);
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
        if ('' === $component) {
            return [];
        }

        if ('.' !== $component && '.' == mb_substr($component, -1, 1, 'UTF-8')) {
            $component = substr($component, 0, -1);
        }

        return $this->validate($component);
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
     * @return static
     */
    public function replaceLabel(int $offset, string $host): self
    {
        $data = $this->replace($offset, $host);
        if ($data === $this->data) {
            return $this;
        }

        return self::createFromLabels($data, $this->is_absolute, $this->resolver);
    }

    /**
     * Returns an instance without the specified keys
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

        return self::createFromLabels($data, $this->is_absolute, $this->resolver);
    }

    /**
     * Returns an instance with the specified registerable domain added
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

        $new = $this->validate($host);
        $public_suffix = $this->getPublicSuffix();
        if (implode('.', array_reverse($new)) === $public_suffix) {
            return $this;
        }

        $offset = 0;
        if ('' != $public_suffix) {
            $offset = count(explode('.', $public_suffix));
        }

        return self::createFromLabels(
            array_merge($new, array_slice($this->data, $offset)),
            $this->is_absolute,
            $this->resolver
        );
    }

    /**
     * Returns an instance with the specified registerable domain added
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated deprecated since version 1.5.0
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
     * Returns an instance with the specified registerable domain added
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

        $new = $this->validate($host);
        $registerable_domain = $this->getRegistrableDomain();
        if (implode('.', array_reverse($new)) === $registerable_domain) {
            return $this;
        }

        $offset = 0;
        if ('' != $registerable_domain) {
            $offset = count(explode('.', $registerable_domain));
        }

        return self::createFromLabels(
            array_merge($new, array_slice($this->data, $offset)),
            $this->is_absolute,
            $this->resolver
        );
    }

    /**
     * Returns an instance with the specified sub domain added
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

        $new = $this->validate($host);
        $subdomain = $this->getSubDomain();
        if (implode('.', array_reverse($new)) === $subdomain) {
            return $this;
        }

        $offset = count($this->data);
        if ('' != $subdomain) {
            $offset -= count(explode('.', $subdomain));
        }

        return self::createFromLabels(
            array_merge(array_slice($this->data, 0, $offset), $new),
            $this->is_absolute,
            $this->resolver
        );
    }

    /**
     * Returns an instance with a different domain resolver
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains a different domain resolver, and update the
     * host domain information.
     *
     * @param Rules $resolver
     *
     * @return static
     */
    public function withDomainResolver(Rules $resolver): self
    {
        $clone = clone $this;
        $clone->resolver = $resolver;
        $clone->getDomainInfo();

        return $clone;
    }
}

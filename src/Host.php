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
declare(strict_types=1);

namespace League\Uri\Components;

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
class Host extends AbstractHierarchicalComponent
{
    use HostInfoTrait;

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
     * HierarchicalComponent delimiter
     *
     * @var string
     */
    protected static $separator = '.';

    /**
     * Host literal representation
     *
     * @var string
     */
    protected $host;

    /**
     *  This static method is called for classes exported by var_export()
     *
     * @param array $properties
     *
     * @return static
     */
    public static function __set_state(array $properties): self
    {
        $host = static::createFromLabels($properties['data'], $properties['is_absolute']);
        $host->hostnameInfoLoaded = $properties['hostnameInfoLoaded'];
        $host->hostnameInfo = $properties['hostnameInfo'];

        return $host;
    }

    /**
     * return a new instance from an array or a traversable object
     *
     * @param Traversable|string[] $data The segments list
     * @param int                  $type one of the constant IS_ABSOLUTE or IS_RELATIVE
     *
     * @throws Exception If $type is not a recognized constant
     *
     * @return static
     */
    public static function createFromLabels($data, int $type = self::IS_RELATIVE): self
    {
        static $type_list = [self::IS_ABSOLUTE => 1, self::IS_RELATIVE => 1];

        $data = static::filterIterable($data);
        if (!isset($type_list[$type])) {
            throw Exception::fromInvalidFlag($type);
        }

        if ([] === $data) {
            return new static();
        }

        if ([''] === $data) {
            return new static('');
        }

        return new static(static::format($data, $type));
    }

    /**
     * Return a formatted host string
     *
     * @param string[] $data The segments list
     * @param int      $type
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
     * @param string $ip
     *
     * @throws Exception If the IP is invalid or unrecognized
     *
     * @return static
     */
    public static function createFromIp(string $ip): self
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return new static($ip);
        }

        if (false !== strpos($ip, '%')) {
            $parts = explode('%', rawurldecode($ip));
            $ip = array_shift($parts).'%25'.rawurlencode((string) array_shift($parts));

            return new static('['.$ip.']');
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return new static('['.$ip.']');
        }

        throw new Exception(sprintf('Please verify the submitted IP: %s', $ip));
    }

    /**
     * New instance
     *
     * @param null|string $host
     */
    public function __construct(string $host = null)
    {
        $host = $this->setIsAbsolute($host);
        $this->data = $this->validate($host);
    }

    /**
     * set the FQDN property
     *
     * @param string $str
     *
     * @return string
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

        if ($this->isValidHostnameIpv6($host)) {
            $this->host_as_ipv6 = true;
            $this->has_zone_identifier = false !== strpos($host, '%');

            return [$host];
        }

        if ($this->isValidHostname($host)) {
            return array_reverse(array_map(
                function ($value) {
                    return idn_to_utf8($value, 0, INTL_IDNA_VARIANT_UTS46);
                },
                explode('.', strtolower($host))
            ));
        }

        throw new Exception(sprintf('The submitted host `%s` is invalid', $host));
    }

    /**
     * Called by var_dump() when dumping The object
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            'component' => $this->getContent(),
            'labels' => $this->data,
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
     * @return array
     */
    public function keys(): array
    {
        if (0 === func_num_args()) {
            return array_keys($this->data);
        }

        return array_keys(
            $this->data,
            idn_to_utf8($this->validateString(func_get_arg(0)), 0, INTL_IDNA_VARIANT_UTS46),
            true
        );
    }

    /**
     * Returns the instance content encoded in RFC3986 or RFC3987.
     *
     * If the instance is defined, the value returned MUST be percent-encoded,
     * but MUST NOT double-encode any characters depending on the encoding type selected.
     *
     * To determine what characters to encode, please refer to RFC 3986, Sections 2 and 3.
     * or RFC 3987 Section 3.
     *
     * By default the content is encoded according to RFC3986
     *
     * If the instance is not defined null is returned
     *
     * @param int $enc_type
     *
     * @return string|null
     */
    public function getContent(int $enc_type = ComponentInterface::RFC3986_ENCODING)
    {
        $this->assertValidEncoding($enc_type);

        if ([] === $this->data) {
            return null;
        }

        if ($this->isIp()) {
            return $this->data[0];
        }

        if ($enc_type != ComponentInterface::RFC3987_ENCODING) {
            return $this->format(array_map(function ($value) {
                return idn_to_ascii($value, 0, INTL_IDNA_VARIANT_UTS46);
            }, $this->data), $this->is_absolute);
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

        return $this->withContent(substr($this->data[0], 0, strpos($this->data[0], '%')).']');
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

        return static::createFromLabels($labels, $this->is_absolute);
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

        return static::createFromLabels($labels, $this->is_absolute);
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

        return self::createFromLabels($data, $this->is_absolute);
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

        return self::createFromLabels($data, $this->is_absolute);
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
    public function withRegisterableDomain(string $host): self
    {
        if ('' === $host) {
            $host = null;
        }

        $source = $this->getContent();
        if ('' == $source) {
            return $this->withContent($host);
        }

        $new = $this->validate($host);
        $registerable_domain = $this->getRegisterableDomain();
        if (implode('.', array_reverse($new)) === $registerable_domain) {
            return $this;
        }

        $offset = 0;
        if ('' != $registerable_domain) {
            $offset = count(explode('.', $registerable_domain));
        }

        return self::createFromLabels(array_merge($new, array_slice($this->data, $offset)), $this->is_absolute);
    }

    /**
     * Returns an instance with the specified sub domain added
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the modified component with the new sud domain
     *
     * @param string|null $host the subdomain to add
     *
     * @return static
     */
    public function withSubdomain(string $host): self
    {
        if ('' === $host) {
            $host = null;
        }

        $source = $this->getContent();
        if ('' == $source) {
            return $this->withContent($host);
        }

        $new = $this->validate($host);
        $subdomain = $this->getSubdomain();
        if (implode('.', array_reverse($new)) === $subdomain) {
            return $this;
        }

        $offset = count($this->data);
        if ('' != $subdomain) {
            $offset -= count(explode('.', $subdomain));
        }

        return self::createFromLabels(array_merge(array_slice($this->data, 0, $offset), $new), $this->is_absolute);
    }
}

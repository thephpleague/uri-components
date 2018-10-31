<?php

/**
 * League.Uri (http://uri.thephpleague.com/components).
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

use League\Uri\Exception\MalformedUriComponent;
use function explode;
use function filter_var;
use function in_array;
use function rawurldecode;
use function rawurlencode;
use function sprintf;
use function strpos;
use function substr;
use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;

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
final class IpAddress extends Host
{
    /**
     * @var bool
     */
    private $has_zone_identifier = false;

    /**
     * Returns a host from an IP address.
     *
     * @throws MalformedUriComponent If the $ip can not be converted into a Host
     */
    public static function createFromIp(string $ip, string $version = ''): self
    {
        if ('' !== $version) {
            return new self('[v'.$version.'.'.$ip.']');
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return new self($ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return new self('['.$ip.']');
        }

        if (false !== strpos($ip, '%')) {
            [$ipv6, $zoneId] = explode('%', rawurldecode($ip), 2) + [1 => ''];

            return new self('['.$ipv6.'%25'.rawurlencode($zoneId).']');
        }

        throw new MalformedUriComponent(sprintf('`%s` is an invalid IP Host', $ip));
    }

    /**
     * {@inheritdoc}
     */
    protected function parse(string $host = null): void
    {
        parent::parse($host);
        if (null === $this->ip_version || null === $host || '' === $host) {
            throw new MalformedUriComponent(sprintf('`%s` is an invalid IP Host', $host));
        }

        if ('6' === $this->ip_version) {
            $this->has_zone_identifier = false !== strpos(substr($host, 1, -1), '%');
        }
    }

    /**
     * Returns the IP version.
     *
     * If the host is a not an IP this method will return null
     */
    public function getIpVersion(): ?string
    {
        return $this->ip_version;
    }

    /**
     * Returns whether or not the host is an IP address.
     */
    public function isIp(): bool
    {
        return null !== $this->ip_version;
    }

    /**
     * Returns whether or not the host is an IPv4 address.
     */
    public function isIpv4(): bool
    {
        return '4' === $this->ip_version;
    }

    /**
     * Returns whether or not the host is an IPv6 address.
     */
    public function isIpv6(): bool
    {
        return '6' === $this->ip_version;
    }

    /**
     * Returns whether or not the host is an IPv6 address.
     */
    public function isIpFuture(): bool
    {
        return !in_array($this->ip_version, [null, '4', '6'], true);
    }

    /**
     * Returns whether or not the host has a ZoneIdentifier.
     *
     * @see http://tools.ietf.org/html/rfc6874#section-4
     */
    public function hasZoneIdentifier(): bool
    {
        return $this->has_zone_identifier;
    }

    /**
     * Retrieve the IP component If the Host is an IP adress.
     *
     * If the host is a not an IP this method will return null
     */
    public function getIp(): string
    {
        $component = (string) $this->component;
        if ('4' === $this->ip_version) {
            return $component;
        }

        $ip = substr($component, 1, -1);
        if ('6' !== $this->ip_version) {
            return substr($ip, strpos($ip, '.') + 1);
        }

        $pos = strpos($ip, '%');
        if (false === $pos) {
            return $ip;
        }

        return substr($ip, 0, $pos).'%'.rawurldecode(substr($ip, $pos + 3));
    }

    /**
     * Returns an host without its zone identifier according to RFC6874.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance without the host zone identifier according to RFC6874
     *
     * @see http://tools.ietf.org/html/rfc6874#section-4
     */
    public function withoutZoneIdentifier(): self
    {
        if (!$this->has_zone_identifier) {
            return $this;
        }

        [$ipv6, ] = explode('%', substr((string) $this->component, 1, -1));

        return self::createFromIp($ipv6);
    }
}

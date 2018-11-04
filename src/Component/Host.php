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

use League\Uri\Exception\InvalidUriComponent;
use League\Uri\Exception\MalformedUriComponent;
use League\Uri\HostInterface;
use function defined;
use function explode;
use function filter_var;
use function function_exists;
use function idn_to_ascii;
use function idn_to_utf8;
use function implode;
use function in_array;
use function inet_pton;
use function preg_match;
use function rawurldecode;
use function sprintf;
use function strpos;
use function strtolower;
use function substr;
use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;
use const IDNA_ERROR_BIDI;
use const IDNA_ERROR_CONTEXTJ;
use const IDNA_ERROR_DISALLOWED;
use const IDNA_ERROR_DOMAIN_NAME_TOO_LONG;
use const IDNA_ERROR_EMPTY_LABEL;
use const IDNA_ERROR_HYPHEN_3_4;
use const IDNA_ERROR_INVALID_ACE_LABEL;
use const IDNA_ERROR_LABEL_HAS_DOT;
use const IDNA_ERROR_LABEL_TOO_LONG;
use const IDNA_ERROR_LEADING_COMBINING_MARK;
use const IDNA_ERROR_LEADING_HYPHEN;
use const IDNA_ERROR_PUNYCODE;
use const IDNA_ERROR_TRAILING_HYPHEN;
use const INTL_IDNA_VARIANT_UTS46;

final class Host extends Component implements HostInterface
{
    /**
     * @see https://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * invalid characters in host regular expression
     */
    private const REGEXP_INVALID_HOST_CHARS = '/
        [:\/?#\[\]@ ]  # gen-delims characters as well as the space character
    /ix';

    /**
     * @see https://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * General registered name regular expression
     */
    private const REGEXP_REGISTERED_NAME = '/(?(DEFINE)
        (?<unreserved>[a-z0-9_~\-])   # . is missing as it is used to separate labels
        (?<sub_delims>[!$&\'()*+,;=])
        (?<encoded>%[A-F0-9]{2})
        (?<reg_name>(?:(?&unreserved)|(?&sub_delims)|(?&encoded))*)
    )
    ^(?:(?&reg_name)\.)*(?&reg_name)\.?$/ix';

    /**
     * @see https://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * IPvFuture regular expression
     */
    private const REGEXP_IP_FUTURE = '/^
        v(?<version>[A-F0-9]+)\.
        (?:
            (?<unreserved>[a-z0-9_~\-\.])|
            (?<sub_delims>[!$&\'()*+,;=:])  # also include the : character
        )+
    $/ix';

    private const REGEXP_GEN_DELIMS = '/[:\/?#\[\]@]/';

    private const ADDRESS_BLOCK = "\xfe\x80";

    /**
     * @var string|null
     */
    private $component;

    /**
     * @var string|null
     */
    private $ip_version;

    /**
     * @var bool
     */
    private $has_zone_identifier = false;

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['component']);
    }

    /**
     * @codeCoverageIgnore
     */
    private static function supportIdnHost(): void
    {
        static $idn_support = null;
        $idn_support = $idn_support ?? function_exists('\idn_to_ascii') && defined('\INTL_IDNA_VARIANT_UTS46');
        if (!$idn_support) {
            throw new InvalidUriComponent('IDN host can not be processed. Verify that ext/intl is installed for IDN support and that ICU is at least version 4.6.');
        }
    }

    /**
     * Returns a host from an IP address.
     *
     * @throws MalformedUriComponent If the $ip can not be converted into a Host
     *
     * @return static
     */
    public static function createFromIp(string $ip, string $version = '')
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
     * New instance.
     *
     * @param null|mixed $host
     */
    public function __construct($host = null)
    {
        $host = $this->filterComponent($host);
        $this->parse($host);
    }

    /**
     * Validates the submitted data.
     *
     * @throws MalformedUriComponent If the host is invalid
     */
    private function parse(string $host = null): void
    {
        $this->ip_version = null;
        $this->component = $host;
        if (null === $host || '' === $host) {
            return;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->ip_version = '4';

            return;
        }

        if ('[' === $host[0] && ']' === substr($host, -1)) {
            $ip_host = substr($host, 1, -1);
            if ($this->isValidIpv6Hostname($ip_host)) {
                $this->ip_version = '6';
                $this->has_zone_identifier = false !== strpos($ip_host, '%');

                return;
            }

            if (preg_match(self::REGEXP_IP_FUTURE, $ip_host, $matches) && !in_array($matches['version'], ['4', '6'], true)) {
                $this->ip_version = $matches['version'];

                return;
            }

            throw new MalformedUriComponent(sprintf('`%s` is an invalid IP literal format', $host));
        }

        $domain_name = rawurldecode($host);
        if (!preg_match(self::REGEXP_NON_ASCII_PATTERN, $domain_name)) {
            $domain_name = strtolower($domain_name);
        }

        $this->component = $domain_name;
        if (preg_match(self::REGEXP_REGISTERED_NAME, $domain_name)) {
            return;
        }

        if (!preg_match(self::REGEXP_NON_ASCII_PATTERN, $domain_name) || preg_match(self::REGEXP_INVALID_HOST_CHARS, $domain_name)) {
            throw new MalformedUriComponent(sprintf('`%s` is an invalid domain name : the host contains invalid characters', $host));
        }

        self::supportIdnHost();

        $domain_name = idn_to_ascii($domain_name, 0, INTL_IDNA_VARIANT_UTS46, $arr);
        if (false === $domain_name || 0 !== $arr['errors']) {
            throw new MalformedUriComponent(sprintf('`%s` is an invalid domain name : %s', $host, $this->getIDNAErrors($arr['errors'])));
        }

        if (false !== strpos($domain_name, '%')) {
            throw new MalformedUriComponent(sprintf('`%s` is an invalid domain name', $host));
        }

        $this->component = $domain_name;
    }

    /**
     * Retrieves and format IDNA conversion error message.
     *
     * @see http://icu-project.org/apiref/icu4j/com/ibm/icu/text/IDNA.Error.html
     */
    private function getIDNAErrors(int $error_byte): string
    {
        /**
         * IDNA errors.
         */
        static $idn_errors = [
            IDNA_ERROR_EMPTY_LABEL => 'a non-final domain name label (or the whole domain name) is empty',
            IDNA_ERROR_LABEL_TOO_LONG => 'a domain name label is longer than 63 bytes',
            IDNA_ERROR_DOMAIN_NAME_TOO_LONG => 'a domain name is longer than 255 bytes in its storage form',
            IDNA_ERROR_LEADING_HYPHEN => 'a label starts with a hyphen-minus ("-")',
            IDNA_ERROR_TRAILING_HYPHEN => 'a label ends with a hyphen-minus ("-")',
            IDNA_ERROR_HYPHEN_3_4 => 'a label contains hyphen-minus ("-") in the third and fourth positions',
            IDNA_ERROR_LEADING_COMBINING_MARK => 'a label starts with a combining mark',
            IDNA_ERROR_DISALLOWED => 'a label or domain name contains disallowed characters',
            IDNA_ERROR_PUNYCODE => 'a label starts with "xn--" but does not contain valid Punycode',
            IDNA_ERROR_LABEL_HAS_DOT => 'a label contains a dot=full stop',
            IDNA_ERROR_INVALID_ACE_LABEL => 'An ACE label does not contain a valid label string',
            IDNA_ERROR_BIDI => 'a label does not meet the IDNA BiDi requirements (for right-to-left characters)',
            IDNA_ERROR_CONTEXTJ => 'a label does not meet the IDNA CONTEXTJ requirements',
        ];

        $res = [];
        foreach ($idn_errors as $error => $reason) {
            if ($error_byte & $error) {
                $res[] = $reason;
            }
        }

        return [] === $res ? 'Unknown IDNA conversion error.' : implode(', ', $res).'.';
    }

    /**
     * Validates an Ipv6 as Host.
     *
     * @see http://tools.ietf.org/html/rfc6874#section-2
     * @see http://tools.ietf.org/html/rfc6874#section-4
     */
    private function isValidIpv6Hostname(string $host): bool
    {
        [$ipv6, $scope] = explode('%', $host, 2) + [1 => null];
        if (null === $scope) {
            return (bool) filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        }

        $scope = rawurldecode('%'.$scope);

        return !preg_match(self::REGEXP_NON_ASCII_PATTERN, $scope)
            && !preg_match(self::REGEXP_GEN_DELIMS, $scope)
            && filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            && 0 === strpos((string) inet_pton((string) $ipv6), self::ADDRESS_BLOCK);
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): ?string
    {
        return $this->component;
    }

    /**
     * {@inheritdoc}
     */
    public function toAscii(): ?string
    {
        return $this->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function toUnicode(): ?string
    {
        if (null !== $this->ip_version
            || null === $this->component
            || false === strpos($this->component, 'xn--')
        ) {
            return $this->component;
        }

        return (string) idn_to_utf8($this->component, 0, INTL_IDNA_VARIANT_UTS46);
    }

    /**
     * {@inheritdoc}
     */
    public function getIpVersion(): ?string
    {
        return $this->ip_version;
    }

    /**
     * {@inheritdoc}
     */
    public function getIp(): ?string
    {
        if (null === $this->ip_version) {
            return null;
        }

        if ('4' === $this->ip_version) {
            return $this->component;
        }

        $ip = substr((string) $this->component, 1, -1);
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
     * {@inheritdoc}
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
     * Returns an host without its zone identifier according to RFC6874.
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

        [$ipv6, ] = explode('%', substr((string) $this->component, 1, -1));

        return static::createFromIp($ipv6);
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($content): self
    {
        $content = $this->filterComponent($content);
        if ($content === $this->getContent()) {
            return $this;
        }

        return new self($content);
    }
}

<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.0.3
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace League\Uri\Components;

use Pdp\Parser;
use Pdp\PublicSuffixListManager;

/**
 * Value object representing a URI host component.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
trait HostInfoTrait
{
    /**
     * Pdp Parser
     *
     * @var Parser
     */
    protected static $pdp_parser;

    /**
     * Hostname public info
     *
     * @var array
     */
    protected $hostnameInfo = [
        'isPublicSuffixValid' => false,
        'publicSuffix' => '',
        'registerableDomain' => '',
        'subdomain' => '',
    ];

    /**
     * is the Hostname Info loaded
     *
     * @var bool
     */
    protected $hostnameInfoLoaded = false;

    /**
     * Valid Start Label characters
     *
     * @var string
     */
    protected static $starting_label_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * IPv6 Local Link Prefix
     *
     * @var string
     */
    protected static $local_link_prefix = '1111111010';

    /**
     * Invalid Zone Identifier characters
     *
     * @var string
     */
    protected static $invalid_uri_chars = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F\x7F";

    /**
     * Load the hostname info
     *
     * @param string $key hostname info key
     *
     * @return mixed
     */
    protected function getHostnameInfo(string $key)
    {
        $this->loadHostnameInfo();
        return $this->hostnameInfo[$key];
    }

    /**
     * parse and save the Hostname information from the Parser
     */
    protected function loadHostnameInfo()
    {
        if ($this->isIp() || $this->hostnameInfoLoaded) {
            return;
        }

        $host = $this->__toString();
        if ($this->isAbsolute()) {
            $host = substr($host, 0, -1);
        }

        $this->hostnameInfo = array_map(
            'sprintf',
            $this->getPdpParser()->parseHost($host)->toArray()
        ) + $this->hostnameInfo;

        if ('' !== $this->hostnameInfo['publicSuffix']) {
            $this->hostnameInfo['isPublicSuffixValid'] = $this->getPdpParser()->isSuffixValid($host);
        }

        $this->hostnameInfoLoaded = true;
    }

    /**
     * Initialize and access the Parser object
     *
     * @return Parser
     */
    protected function getPdpParser(): Parser
    {
        if (!static::$pdp_parser instanceof Parser) {
            static::$pdp_parser = new Parser((new PublicSuffixListManager())->getList());
        }

        return static::$pdp_parser;
    }

    /**
     * Return the host public suffix
     *
     * @return string
     */
    public function getPublicSuffix(): string
    {
        return $this->getHostnameInfo('publicSuffix');
    }

    /**
     * Return the host registrable domain
     *
     * @return string
     */
    public function getRegisterableDomain(): string
    {
        return $this->getHostnameInfo('registerableDomain');
    }

    /**
     * Return the hostname subdomain
     *
     * @return string
     */
    public function getSubdomain(): string
    {
        return $this->getHostnameInfo('subdomain');
    }

    /**
     * Tell whether the current public suffix is valid
     *
     * @return bool
     */
    public function isPublicSuffixValid(): bool
    {
        return $this->getHostnameInfo('isPublicSuffixValid');
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
        if (strlen($scope) !== strcspn($scope, '?#@[]'.self::$invalid_uri_chars)) {
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

        return substr($res, 0, 10) === self::$local_link_prefix;
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
     * Convert domain name to IDNA ASCII form.
     *
     * Conversion is done only if the label contains none valid label characters
     *
     * @param string $label
     *
     * @return string|false
     */
    protected function toAscii(string $label)
    {
        if (strlen($label) === strspn($label, static::$starting_label_chars.'-')) {
            return $label;
        }

        return idn_to_ascii($label, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
    }

    /**
     * Convert domain name to IDNA ASCII form.
     *
     * Conversion is done only if the label contains the ACE prefix 'xn--'
     *
     * @param string $label
     *
     * @return string|false
     */
    protected function toIdn(string $label)
    {
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
     * - contain alphanumeric ASCII character or a hyphen
     * - start and end whith an alpha numeric ASCII character
     *
     * @see https://tools.ietf.org/html/rfc1034#section-3.5
     *
     * @param string $label
     *
     * @return bool
     */
    protected function isValidLabel($label): bool
    {
        if (!is_string($label) || '' == $label || 63 < strlen($label)) {
            return false;
        }

        return 2 === strspn($label[0].substr($label, -1, 1), static::$starting_label_chars)
            && strlen($label) === strspn($label, static::$starting_label_chars.'-');
    }

    /**
     * Returns the instance string representation; If the
     * instance is not defined an empty string is returned
     *
     * @return string
     */
    abstract public function __toString(): string;

    /**
     * Returns whether or not the host is an IP address
     *
     * @return bool
     */
    abstract public function isIp(): bool;

    /**
     * Returns whether or not the host is a full qualified domain name
     *
     * @return bool
     */
    abstract public function isAbsolute(): bool;
}

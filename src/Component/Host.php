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

use League\Uri\Exception\InvalidUriComponent;
use League\Uri\Exception\MalformedUriComponent;

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
class Host extends Component
{
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
     */
    const REGEXP_GEN_DELIMS = '/[:\/?#\[\]@]/';

    /**
     * @internal
     */
    const ADDRESS_BLOCK = "\xfe\x80";

    /**
     * @var string|null
     */
    protected $component;

    /**
     * @var string|null
     */
    protected $ip_version;

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties)
    {
        return new static($properties['component']);
    }

    /**
     * @codeCoverageIgnore
     */
    protected static function supportIdnHost()
    {
        static $idn_support = null;
        $idn_support = $idn_support ?? function_exists('\idn_to_ascii') && defined('\INTL_IDNA_VARIANT_UTS46');
        if (!$idn_support) {
            throw new InvalidUriComponent('IDN host can not be processed. Verify that ext/intl is installed for IDN support and that ICU is at least version 4.6.');
        }
    }

    /**
     * New instance.
     *
     * @param mixed $host
     */
    public function __construct($host = null)
    {
        $host = $this->filterComponent($host);
        $this->parse($host);
    }

    /**
     * Validates the submitted data.
     *
     * @param null|string $host
     *
     * @throws MalformedUriComponent If the host is invalid
     */
    protected function parse(string $host = null)
    {
        $this->ip_version = null;

        if (null === $host || '' === $host) {
            $this->component = $host;
            return;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->component = $host;
            $this->ip_version = '4';

            return;
        }

        if ('[' === $host[0] && ']' === substr($host, -1)) {
            $ip_host = substr($host, 1, -1);
            if ($this->isValidIpv6Hostname($ip_host)) {
                $this->component = $host;
                $this->ip_version = '6';

                return;
            }

            if (preg_match(self::REGEXP_IP_FUTURE, $ip_host, $matches) && !in_array($matches['version'], ['4', '6'], true)) {
                $this->component = $host;
                $this->ip_version = $matches['version'];

                return;
            }

            throw new MalformedUriComponent(sprintf('`%s` is an invalid IP literal format', $host));
        }

        $domain_name = rawurldecode($host);
        if (!preg_match(self::REGEXP_NON_ASCII_PATTERN, $domain_name)) {
            $domain_name = strtolower($domain_name);
        }

        if (preg_match(self::REGEXP_REGISTERED_NAME, $domain_name)) {
            $this->component = $domain_name;

            return;
        }

        if (!preg_match(self::REGEXP_NON_ASCII_PATTERN, $domain_name) || preg_match(self::REGEXP_INVALID_HOST_CHARS, $domain_name)) {
            throw new MalformedUriComponent(sprintf('`%s` is an invalid domain name : the host contains invalid characters', $host));
        }

        self::supportIdnHost();

        $domain_name = idn_to_ascii($host, 0, INTL_IDNA_VARIANT_UTS46, $arr);
        if (0 === $arr['errors']) {
            $this->component = $domain_name;

            return;
        }

        throw new MalformedUriComponent(sprintf('`%s` is an invalid domain name : %s', $host, $this->getIDNAErrors($arr['errors'])));
    }

    /**
     * Retrieves and format IDNA conversion error message.
     *
     * @see http://icu-project.org/apiref/icu4j/com/ibm/icu/text/IDNA.Error.html
     *
     * @param int $error_byte
     *
     * @return string
     */
    protected function getIDNAErrors(int $error_byte): string
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

        return empty($res) ? 'Unknown IDNA conversion error.' : implode(', ', $res).'.';
    }

    /**
     * Validates an Ipv6 as Host.
     *
     * @see http://tools.ietf.org/html/rfc6874#section-2
     * @see http://tools.ietf.org/html/rfc6874#section-4
     *
     * @param string $host
     *
     * @return bool
     */
    protected function isValidIpv6Hostname(string $host): bool
    {
        list($ipv6, $scope) = explode('%', $host, 2) + [1 => null];
        if (null === $scope) {
            return (bool) filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        }

        $scope = rawurldecode('%'.$scope);

        return !preg_match(self::REGEXP_NON_ASCII_PATTERN, $scope)
            && !preg_match(self::REGEXP_GEN_DELIMS, $scope)
            && filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            && substr(inet_pton($ipv6) & self::ADDRESS_BLOCK, 0, 2) === self::ADDRESS_BLOCK;
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(int $enc_type = self::RFC3986_ENCODING)
    {
        $this->filterEncoding($enc_type);

        if (self::RFC3986_ENCODING === $enc_type
            || null !== $this->ip_version
            || null === $this->component
            || false === strpos($this->component, 'xn--')
        ) {
            return $this->component;
        }

        return idn_to_utf8($this->component, 0, INTL_IDNA_VARIANT_UTS46);
    }

    /**
     * {@inheritdoc}
     */
    public function getUriComponent(): string
    {
        return (string) $this->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($content)
    {
        $content = $this->filterComponent($content);
        if ($content === $this->getContent()) {
            return $this;
        }

        return new self($content);
    }
}

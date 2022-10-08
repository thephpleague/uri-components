<?php

/**
 * League.Uri (https://uri.thephpleague.com/components/2.0/)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\Components;

use League\Uri\Contracts\AuthorityInterface;
use League\Uri\Contracts\IpHostInterface;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\IdnaConversionFailed;
use League\Uri\Exceptions\IPv4CalculatorMissing;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Idna\Idna;
use League\Uri\IPv4Normalizer;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use function compact;
use function explode;
use function filter_var;
use function in_array;
use function inet_pton;
use function preg_match;
use function rawurldecode;
use function rawurlencode;
use function sprintf;
use function strpos;
use function strtolower;
use function substr;
use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;

final class Host extends Component implements IpHostInterface
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
     * General registered name regular expression.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2.2
     * @see https://regex101.com/r/fptU8V/1
     */
    private const REGEXP_REGISTERED_NAME = '/
    (?(DEFINE)
        (?<unreserved>[a-z0-9_~\-])   # . is missing as it is used to separate labels
        (?<sub_delims>[!$&\'()*+,;=])
        (?<encoded>%[A-F0-9]{2})
        (?<reg_name>(?:(?&unreserved)|(?&sub_delims)|(?&encoded))*)
    )
        ^(?:(?&reg_name)\.)*(?&reg_name)\.?$
    /ix';

    /**
     * Domain name regular expression.
     *
     * Everything but the domain name length is validated
     *
     * @see https://tools.ietf.org/html/rfc1034#section-3.5
     * @see https://tools.ietf.org/html/rfc1123#section-2.1
     * @see https://regex101.com/r/71j6rt/1
     */
    private const REGEXP_DOMAIN_NAME = '/
    (?(DEFINE)
        (?<let_dig> [a-z0-9])                         # alpha digit
        (?<let_dig_hyp> [a-z0-9-])                    # alpha digit and hyphen
        (?<ldh_str> (?&let_dig_hyp){0,61}(?&let_dig)) # domain label end
        (?<label> (?&let_dig)((?&ldh_str))?)          # domain label
        (?<domain> (?&label)(\.(?&label)){0,126}\.?)  # domain name
    )
        ^(?&domain)$
    /ix';

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

    private ?string $host;
    private bool $is_domain;
    private ?string $ip_version;
    private bool $has_zone_identifier;

    public function __construct(UriComponentInterface|Stringable|float|int|string|bool|null $host = null)
    {
        [
            'host' => $this->host,
            'is_domain' => $this->is_domain,
            'ip_version' => $this->ip_version,
            'has_zone_identifier' => $this->has_zone_identifier,
        ] = $this->parse($host);
    }

    /**
     *
     * @return array{host:string|null, is_domain:bool, ip_version:string|null, has_zone_identifier:bool}
     */
    private function parse(float|UriComponentInterface|Stringable|bool|int|string|null $host): array
    {
        $host = self::filterComponent($host);
        $is_domain = false;
        $ip_version = null;
        $has_zone_identifier = false;
        if (null === $host || '' === $host) {
            return compact('host', 'is_domain', 'ip_version', 'has_zone_identifier');
        }

        static $inMemoryCache = [];
        if (isset($inMemoryCache[$host])) {
            return $inMemoryCache[$host];
        }

        if (100 < count($inMemoryCache)) {
            unset($inMemoryCache[array_key_first($inMemoryCache)]);
        }

        if (false !== filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip_version = '4';

            return $inMemoryCache[$host] = compact('host', 'is_domain', 'ip_version', 'has_zone_identifier');
        }

        if ('[' === $host[0] && str_ends_with($host, ']')) {
            $ip_host = substr($host, 1, -1);
            if ($this->isValidIpv6Hostname($ip_host)) {
                $ip_version = '6';
                $has_zone_identifier = str_contains($ip_host, '%');

                return $inMemoryCache[$host] = compact('host', 'is_domain', 'ip_version', 'has_zone_identifier');
            }

            if (1 === preg_match(self::REGEXP_IP_FUTURE, $ip_host, $matches) && !in_array($matches['version'], ['4', '6'], true)) {
                $ip_version = $matches['version'];

                return $inMemoryCache[$host] = compact('host', 'is_domain', 'ip_version', 'has_zone_identifier');
            }

            throw new SyntaxError(sprintf('`%s` is an invalid IP literal format.', $host));
        }

        $domain_name = rawurldecode($host);
        $is_ascii = false;
        if (1 !== preg_match(self::REGEXP_NON_ASCII_PATTERN, $domain_name)) {
            $domain_name = strtolower($domain_name);
            $is_ascii = true;
        }

        if (1 === preg_match(self::REGEXP_REGISTERED_NAME, $domain_name)) {
            $host = $domain_name;
            $is_domain = $this->isValidDomain($domain_name);
            if (str_contains($host, 'xn--')) {
                Idna::toUnicode($host, Idna::IDNA2008_UNICODE)->result();
            }

            return $inMemoryCache[$host] = compact('host', 'is_domain', 'ip_version', 'has_zone_identifier');
        }

        if ($is_ascii || 1 === preg_match(self::REGEXP_INVALID_HOST_CHARS, $domain_name)) {
            throw new SyntaxError(sprintf('`%s` is an invalid domain name : the host contains invalid characters.', $host));
        }

        $info = Idna::toAscii($domain_name, Idna::IDNA2008_ASCII);
        if (0 !== $info->errors()) {
            throw IdnaConversionFailed::dueToIDNAError($domain_name, $info);
        }

        $unicodeHost = $host;
        $host = $info->result();
        $is_domain = $this->isValidDomain($host);

        return $inMemoryCache[$unicodeHost] = compact('host', 'is_domain', 'ip_version', 'has_zone_identifier');
    }

    /**
     * Tells whether the registered name is a valid domain name according to RFC1123.
     *
     * @see http://man7.org/linux/man-pages/man7/hostname.7.html
     * @see https://tools.ietf.org/html/rfc1123#section-2.1
     */
    private function isValidDomain(string $hostname): bool
    {
        $domainMaxLength = str_ends_with($hostname, '.') ? 254 : 253;

        return !isset($hostname[$domainMaxLength])
            && 1 === preg_match(self::REGEXP_DOMAIN_NAME, $hostname);
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

        return 1 !== preg_match(self::REGEXP_NON_ASCII_PATTERN, $scope)
            && 1 !== preg_match(self::REGEXP_GEN_DELIMS, $scope)
            && false !== filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            && str_starts_with((string)inet_pton((string)$ipv6), self::ADDRESS_BLOCK);
    }

    public static function __set_state(array $properties): self
    {
        return new self($properties['host']);
    }

    public static function createFromNull(): self
    {
        return new self(null);
    }

    public static function createFromString(Stringable|string $host): self
    {
        return new self((string) $host);
    }

    /**
     * Returns a host from an IP address.
     *
     * @throws IPv4CalculatorMissing If detecting IPv4 is not possible
     * @throws SyntaxError           If the $ip can not be converted into a Host
     */
    public static function createFromIp(string $ip, string $version = '', ?IPv4Normalizer $normalizer = null): self
    {
        if ('' !== $version) {
            return new self('[v'.$version.'.'.$ip.']');
        }

        if (false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return new self('['.$ip.']');
        }

        if (str_contains($ip, '%')) {
            [$ipv6, $zoneId] = explode('%', rawurldecode($ip), 2) + [1 => ''];

            return new self('['.$ipv6.'%25'.rawurlencode($zoneId).']');
        }

        $normalizer = $normalizer ?? IPv4Normalizer::createFromServer();
        /** @var Host $host */
        $host = $normalizer->normalizeHost(new self($ip));
        if ($host->isIpv4()) {
            return $host;
        }

        throw new SyntaxError(sprintf('`%s` is an invalid IP Host.', $ip));
    }

    /**
     * Create a new instance from a URI object.
     */
    public static function createFromUri(Psr7UriInterface|UriInterface $uri): self
    {
        if ($uri instanceof UriInterface) {
            return new self($uri->getHost());
        }

        $component = $uri->getHost();
        if ('' === $component) {
            return new self();
        }

        return new self($component);
    }

    /**
     * Create a new instance from an Authority object.
     */
    public static function createFromAuthority(AuthorityInterface $authority): self
    {
        return new self($authority->getHost());
    }

    public function getContent(): ?string
    {
        return $this->host;
    }

    public function getUriComponent(): string
    {
        return (string) $this->getContent();
    }

    public function toAscii(): ?string
    {
        return $this->getContent();
    }

    public function toUnicode(): ?string
    {
        if (null !== $this->ip_version || null === $this->host || !str_contains($this->host, 'xn--')) {
            return $this->host;
        }

        return Idna::toUnicode($this->host, Idna::IDNA2008_UNICODE)->result();
    }

    public function getIpVersion(): ?string
    {
        return $this->ip_version;
    }

    public function getIp(): ?string
    {
        if (null === $this->ip_version) {
            return null;
        }

        if ('4' === $this->ip_version) {
            return $this->host;
        }

        $ip = substr((string) $this->host, 1, -1);
        if ('6' !== $this->ip_version) {
            return substr($ip, (int) strpos($ip, '.') + 1);
        }

        $pos = strpos($ip, '%');
        if (false === $pos) {
            return $ip;
        }

        return substr($ip, 0, $pos).'%'.rawurldecode(substr($ip, $pos + 3));
    }

    public function isDomain(): bool
    {
        return $this->is_domain;
    }

    public function isIp(): bool
    {
        return null !== $this->ip_version;
    }

    public function isIpv4(): bool
    {
        return '4' === $this->ip_version;
    }

    public function isIpv6(): bool
    {
        return '6' === $this->ip_version;
    }

    public function isIpFuture(): bool
    {
        return !in_array($this->ip_version, [null, '4', '6'], true);
    }

    public function hasZoneIdentifier(): bool
    {
        return $this->has_zone_identifier;
    }

    public function withoutZoneIdentifier(): IpHostInterface
    {
        if (!$this->has_zone_identifier) {
            return $this;
        }

        [$ipv6] = explode('%', substr((string) $this->host, 1, -1));

        return self::createFromIp($ipv6);
    }

    public function withContent($content): UriComponentInterface
    {
        $content = self::filterComponent($content);
        if ($content === $this->getContent()) {
            return $this;
        }

        return new self($content);
    }
}

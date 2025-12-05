<?php

/**
 * League.Uri (https://uri.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\Components;

use Deprecated;
use League\Uri\Contracts\AuthorityInterface;
use League\Uri\Contracts\IpHostInterface;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriException;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\MissingFeature;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\HostRecord;
use League\Uri\HostType;
use League\Uri\Idna\Converter as IdnConverter;
use League\Uri\IPv4\Converter as IPv4Converter;
use League\Uri\IPv4Normalizer;
use League\Uri\UriString;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use Uri\Rfc3986\Uri as Rfc3986Uri;
use Uri\WhatWg\Url as WhatWgUrl;

use function explode;
use function filter_var;
use function is_string;
use function preg_replace_callback;
use function rawurldecode;
use function rawurlencode;
use function sprintf;
use function strtolower;
use function strtoupper;
use function substr;

use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;

final class Host extends Component implements IpHostInterface
{
    private readonly ?string $value;
    private readonly HostRecord $host;

    private function __construct(Stringable|string|null $host)
    {
        $this->host = HostRecord::from($host);
        $this->value = $this->host->toAscii();
    }

    public static function new(Stringable|string|null $value = null): self
    {
        return new self($value);
    }

    /**
     * Create a new instance from a string.or a stringable structure or returns null on failure.
     */
    public static function tryNew(Stringable|string|null $uri = null): ?self
    {
        try {
            return self::new($uri);
        } catch (UriException) {
            return null;
        }
    }

    /**
     * Returns a host from an IP address.
     *
     * @throws MissingFeature If detecting IPv4 is not possible
     * @throws SyntaxError If the $ip cannot be converted into a Host
     */
    public static function fromIp(Stringable|string $ip, string $version = ''): self
    {
        if ('' !== $version) {
            return new self('[v'.$version.'.'.$ip.']');
        }

        $ip = (string) $ip;
        if (false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return new self('['.$ip.']');
        }

        if (str_contains($ip, '%')) {
            [$ipv6, $zoneId] = explode('%', rawurldecode($ip), 2) + [1 => ''];

            return new self('['.$ipv6.'%25'.rawurlencode($zoneId).']');
        }

        $host = IPv4Converter::fromEnvironment()->toDecimal($ip);
        if (null === $host) {
            throw new SyntaxError(sprintf('`%s` is an invalid IP Host.', $ip));
        }

        return new self($host);
    }

    /**
     * Create a new instance from a URI object.
     */
    public static function fromUri(WhatWgUrl|Rfc3986Uri|Stringable|string $uri): self
    {
        $uri = self::filterUri($uri);

        return match (true) {
            $uri instanceof Rfc3986Uri => new self($uri->getRawHost()),
            $uri instanceof WhatWgUrl => new self($uri->getAsciiHost()),
            $uri instanceof Psr7UriInterface => new self(UriString::parse($uri)['host']),
            default => new self($uri->getHost()),
        };
    }

    /**
     * Create a new instance from an Authority object.
     */
    public static function fromAuthority(Stringable|string $authority): self
    {
        return match (true) {
            $authority instanceof AuthorityInterface => new self($authority->getHost()),
            default => new self(Authority::new($authority)->getHost()),
        };
    }

    public function value(): ?string
    {
        return $this->value;
    }

    public function equals(mixed $value): bool
    {
        if (!$value instanceof Stringable && !is_string($value) && null !== $value) {
            return false;
        }

        if (!$value instanceof UriComponentInterface) {
            $value = self::tryNew($value);
            if (null === $value) {
                return false;
            }
        }

        return $value->getUriComponent() === $this->getUriComponent();
    }

    public function toAscii(): ?string
    {
        return $this->value();
    }

    public function toUnicode(): ?string
    {
        return $this->host->toUnicode();
    }

    public function encoded(): ?string
    {
        if (null === $this->value || '' === $this->value || HostType::RegisteredName !== $this->host->type) {
            return $this->value;
        }

        return (string) preg_replace_callback(
            '/%[0-9A-F]{2}/i',
            fn (array $matches): string => strtoupper($matches[0]),
            strtolower(rawurlencode(IdnConverter::toUnicode($this->value)->domain()))
        );
    }

    public function getIpVersion(): ?string
    {
        return $this->host->ipVersion();
    }

    public function getIp(): ?string
    {
        return $this->host->ipValue();
    }

    public function isRegisteredName(): bool
    {
        return HostType::RegisteredName === $this->host->type;
    }

    public function isDomain(): bool
    {
        return $this->host->isDomainType();
    }

    public function isIp(): bool
    {
        return HostType::RegisteredName !== $this->host->type;
    }

    public function isIpv4(): bool
    {
        return HostType::Ipv4 === $this->host->type;
    }

    public function isIpv6(): bool
    {
        return HostType::Ipv6 === $this->host->type;
    }

    public function isIpFuture(): bool
    {
        return HostType::IpvFuture === $this->host->type;
    }

    public function hasZoneIdentifier(): bool
    {
        return $this->host->hasZoneIdentifier();
    }

    public function withoutZoneIdentifier(): IpHostInterface
    {
        if (!$this->host->hasZoneIdentifier()) {
            return $this;
        }

        [$ipv6] = explode('%', substr((string) $this->value, 1, -1));

        return self::fromIp($ipv6);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Host::new()
     *
     * @codeCoverageIgnore
     */
    #[Deprecated(message:'use League\Uri\Components\Host::new() instead', since:'league/uri-components:7.0.0')]
    public static function createFromString(Stringable|string|null $host): self
    {
        return self::new($host);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Host::new()
     *
     * @codeCoverageIgnore
     *
     * Returns a new instance from null.
     */
    #[Deprecated(message:'use League\Uri\Components\Host::new() instead', since:'league/uri-components:7.0.0')]
    public static function createFromNull(): self
    {
        return self::new();
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @throws MissingFeature If detecting IPv4 is not possible
     * @throws SyntaxError If the $ip cannot be converted into a Host
     * @deprecated Since version 7.0.0
     * @see Host::fromIp()
     *
     * @codeCoverageIgnore
     *
     * Returns a host from an IP address.
     *
     */
    #[Deprecated(message:'use League\Uri\Components\Host::fromIp() instead', since:'league/uri-components:7.0.0')]
    public static function createFromIp(string $ip, string $version = '', ?IPv4Normalizer $normalizer = null): self
    {
        return self::fromIp($ip, $version);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Host::fromUri()
     *
     * @codeCoverageIgnore
     *
     * Create a new instance from a URI object.
     */
    #[Deprecated(message:'use League\Uri\Components\Host::fromUri() instead', since:'league/uri-components:7.0.0')]
    public static function createFromUri(Psr7UriInterface|UriInterface $uri): self
    {
        return self::fromUri($uri);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Host::fromAuthority()
     *
     * @codeCoverageIgnore
     *
     * Create a new instance from an Authority object.
     */
    #[Deprecated(message:'use League\Uri\Components\Host::fromAuthority() instead', since:'league/uri-components:7.0.0')]
    public static function createFromAuthority(Stringable|string $authority): self
    {
        return self::fromAuthority($authority);
    }
}

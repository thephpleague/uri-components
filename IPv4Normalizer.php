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

namespace League\Uri;

use League\Uri\Components\Authority;
use League\Uri\Components\Host;
use League\Uri\Contracts\AuthorityInterface;
use League\Uri\Contracts\HostInterface;
use League\Uri\Contracts\UriAccess;
use League\Uri\Contracts\UriInterface;
use League\Uri\IPv4Calculators\IPv4Calculator;
use League\Uri\IPv4Calculators\MissingIPv4Calculator;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;

/**
 * DEPRECATION WARNING! This class will be removed in the next major point release.
 *
 * @deprecated Since version 7.0.0
 * @see IPv4Converter
 *
 * @codeCoverageIgnore
 */
final class IPv4Normalizer
{
    private readonly IPv4Converter $converter;

    public function __construct(
        IPv4Converter|IPv4Calculator $calculator
    ) {
        if (!$calculator instanceof IPv4Converter) {
            $calculator = new IPv4Converter($calculator);
        }

        $this->converter = $calculator;
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see IPv4Converter::normalize()
     *
     * @codeCoverageIgnore
     *
     * Normalizes the host content to a IPv4 dot-decimal notation if possible
     * otherwise returns the Host instance unchanged.
     *
     * @see https://url.spec.whatwg.org/#concept-ipv4-parser
     */
    public function normalize(Stringable|string|null $host): ?string
    {
        return $this->converter->normalize($host);
    }

    /**
     * Returns an instance using a GMP calculator.
     */
    public static function createFromGMP(): self
    {
        return new self(IPv4Converter::fromGMP());
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see IPv4Converter::fromBCMath()
     *
     * @codeCoverageIgnore
     *
     * Returns an instance using a Bcmath calculator.
     */
    public static function createFromBCMath(): self
    {
        return new self(IPv4Converter::fromBCMath());
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see IPv4Converter::fromNative()
     *
     * @codeCoverageIgnore
     *
     * Returns an instance using a PHP native calculator (requires 64bits PHP).
     */
    public static function createFromNative(): self
    {
        return new self(IPv4Converter::fromNative());
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see IPv4Converter::fromEnvironment()
     *
     * @codeCoverageIgnore
     *
     * Returns an instance using a detected calculator depending on the PHP environment.
     *
     * @throws MissingIPv4Calculator If no IPv4Calculator implementing object can be used
     *                               on the platform
     *
     * @codeCoverageIgnore
     */
    public static function createFromServer(): self
    {
        return new self(IPv4Converter::fromEnvironment());
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Modifier::normalizeIPv4()
     *
     * @codeCoverageIgnore
     *
     * Normalizes the URI host content to a IPv4 dot-decimal notation if possible
     * otherwise returns the uri instance unchanged.
     *
     * @see https://url.spec.whatwg.org/#concept-ipv4-parser
     */
    public function normalizeUri(Stringable|string $uri): UriInterface|Psr7UriInterface
    {
        $uri = match (true) {
            $uri instanceof UriAccess => $uri->getUri(),
            $uri instanceof UriInterface, $uri instanceof Psr7UriInterface => $uri,
            default => Uri::new($uri),
        };

        $host = Host::fromUri($uri);
        $normalizedHostString = $this->converter->normalize($host);

        return match (true) {
            null === $normalizedHostString,
            $normalizedHostString === $host->value() => $uri,
            default => $uri->withHost($normalizedHostString),
        };
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Modifier::normalizeIPv4()
     *
     * @codeCoverageIgnore
     *
     * Normalizes the authority host content to a IPv4 dot-decimal notation if possible
     * otherwise returns the uri instance unchanged.
     *
     * @see https://url.spec.whatwg.org/#concept-ipv4-parser
     */
    public function normalizeAuthority(Stringable|string $authority): AuthorityInterface
    {
        if (!$authority instanceof AuthorityInterface) {
            $authority = Authority::new($authority);
        }

        $host = Host::fromAuthority($authority);
        $normalizedHostString = $this->converter->normalize($host);

        return match (true) {
            null === $normalizedHostString,
            $normalizedHostString === $host->value() => $authority,
            default => $authority->withHost($normalizedHostString),
        };
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Modifier::normalizeIPv4()
     *
     * @codeCoverageIgnore
     *
     * Normalizes the host content to a IPv4 dot-decimal notation if possible
     * otherwise returns the Host instance unchanged.
     *
     * @see https://url.spec.whatwg.org/#concept-ipv4-parser
     */
    public function normalizeHost(Stringable|string|null $host): HostInterface
    {
        $convertedHost = $this->converter->normalize($host);

        return match (true) {
            null === $convertedHost => $host instanceof HostInterface ? $host : Host::new($host),
            default => Host::new($convertedHost),
        };
    }
}

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
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\UriString;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use Throwable;
use Uri\Rfc3986\Uri as Rfc3986Uri;
use Uri\WhatWg\Url as WhatWgUrl;

use function in_array;
use function is_string;
use function preg_match;
use function sprintf;
use function strtolower;

final class Scheme extends Component
{
    /**
     * Supported schemes and corresponding default port.
     * @see https://github.com/python-hyper/hyperlink/blob/master/src/hyperlink/_url.py for the curating list definition
     * @see https://www.iana.org/assignments/uri-schemes/uri-schemes.xhtml
     * @see https://www.iana.org/assignments/service-names-port-numbers/service-names-port-numbers.xhtml
     *
     * @var array<string, int|null>
     */
    private const SCHEME_DEFAULT_PORT = [
        'data' => null,
        'file' => null,
        'ftp' => 21,
        'gopher' => 70,
        'http' => 80,
        'https' => 443,
        'ws' => 80,
        'wss' => 443,
        'imap' => 143,
        'ipp' => 631,
        'ipps' => 631,
        'irc' => 194,
        'ircs' => 6697,
        'ldap' => 389,
        'ldaps' => 636,
        'mms' => 1755,
        'msrp' => 2855,
        'msrps' => null,
        'mtqp' => 1038,
        'nfs' => 111,
        'nntp' => 119,
        'nntps' => 563,
        'pop' => 110,
        'prospero' => 1525,
        'redis' => 6379,
        'rsync' => 873,
        'rtsp' => 554,
        'rtsps' => 322,
        'rtspu' => 5005,
        'sftp' => 22,
        'smb' => 445,
        'snmp' => 161,
        'ssh' => 22,
        'steam' => null,
        'svn' => 3690,
        'telnet' => 23,
        'tn3270' => 23,
        'ventrilo' => 3784,
        'vnc' => 5900,
        'wais' => 210,
        'xmpp' => null,
        'acap' => 674,
        'afp' => 548,
        'dict' => 2628,
        'dns' => 53,
    ];

    private const REGEXP_SCHEME = ',^[a-z]([-a-z0-9+.]+)?$,i';

    private readonly ?string $scheme;

    private function __construct(Stringable|string|null $scheme)
    {
        $this->scheme = $this->validate($scheme);
    }

    public function isWebsocket(): bool
    {
        return in_array($this->scheme, ['ws', 'wss'], true);
    }

    public function isHttp(): bool
    {
        return in_array($this->scheme, ['http', 'https'], true);
    }

    public function isSsl(): bool
    {
        return in_array($this->scheme, ['https', 'wss'], true);
    }

    public function isSpecial(): bool
    {
        return in_array($this->scheme, [
            'data',
            'file',
            'ftp',
            'gopher',
            'http',
            'https',
            'ws',
            'wss',
        ], true);
    }

    public function defaultPort(): Port
    {
        return Port::new(self::SCHEME_DEFAULT_PORT[$this->scheme] ?? null);
    }

    /**
     * Validate a scheme.
     *
     * @throws SyntaxError if the scheme is invalid
     */
    private function validate(Stringable|string|null $scheme): ?string
    {
        $scheme = self::filterComponent($scheme);
        if (null === $scheme) {
            return null;
        }

        $fScheme = strtolower($scheme);

        /** @var array<string> $inMemoryCache */
        static $inMemoryCache = [];
        if (isset($inMemoryCache[$fScheme])) {
            return $fScheme;
        }

        if (1 !== preg_match(self::REGEXP_SCHEME, $fScheme)) {
            throw new SyntaxError(sprintf("The scheme '%s' is invalid.", $scheme));
        }

        if (100 < count($inMemoryCache)) {
            unset($inMemoryCache[array_key_first($inMemoryCache)]);
        }
        $inMemoryCache[$fScheme] = 1;

        return $fScheme;
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
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Create a new instance from a URI object.
     */
    public static function fromUri(WhatWgUrl|Rfc3986Uri|Stringable|string $uri): self
    {
        $uri = self::filterUri($uri);

        return new self(
            $uri instanceof Psr7UriInterface
            ? UriString::parse($uri)['scheme']
            : $uri->getScheme()
        );
    }

    public function value(): ?string
    {
        return $this->scheme;
    }

    public function getUriComponent(): string
    {
        return $this->value().(null === $this->scheme ? '' : ':');
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

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Scheme::new()
     *
     * @codeCoverageIgnore
     */
    #[Deprecated(message:'use League\Uri\Components\Scheme::new() instead', since:'league/uri-components:7.0.0')]
    public static function createFromString(Stringable|string $scheme): self
    {
        return self::new($scheme);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Scheme::fromUri()
     *
     * @codeCoverageIgnore
     *
     * Create a new instance from a URI object.
     */
    #[Deprecated(message:'use League\Uri\Components\Scheme::fromUri() instead', since:'league/uri-components:7.0.0')]
    public static function createFromUri(Psr7UriInterface|UriInterface $uri): self
    {
        return self::fromUri($uri);
    }
}

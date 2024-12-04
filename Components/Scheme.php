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
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Uri;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;

use function array_key_exists;
use function in_array;
use function preg_match;
use function sprintf;
use function strtolower;

final class Scheme extends Component
{
    /**
     * Supported schemes and corresponding default port.
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
        return null !== $this->scheme
            && array_key_exists($this->scheme, self::SCHEME_DEFAULT_PORT);
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
     * Create a new instance from a URI object.
     */
    public static function fromUri(Stringable|string $uri): self
    {
        $uri = self::filterUri($uri);

        return match (true) {
            $uri instanceof UriInterface => new self($uri->getScheme()),
            default => new self(Uri::new($uri)->getScheme()),
        };
    }

    public function value(): ?string
    {
        return $this->scheme;
    }

    public function getUriComponent(): string
    {
        return $this->value().(null === $this->scheme ? '' : ':');
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

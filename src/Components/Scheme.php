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

use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use function preg_match;
use function sprintf;
use function strtolower;

final class Scheme extends Component
{
    private const REGEXP_SCHEME = ',^[a-z]([-a-z0-9+.]+)?$,i';

    private readonly ?string $scheme;

    public function __construct(UriComponentInterface|Stringable|float|int|string|bool|null $scheme = null)
    {
        $this->scheme = $this->validate($scheme);
    }

    /**
     * Validate a scheme.
     *
     * @throws SyntaxError if the scheme is invalid
     */
    private function validate(float|int|Stringable|string|bool|null $scheme): ?string
    {
        $scheme = self::filterComponent($scheme);
        if (null === $scheme) {
            return null;
        }

        static $inMemoryCache = [];
        if (isset($inMemoryCache[$scheme])) {
            return $inMemoryCache[$scheme];
        }

        if (1 !== preg_match(self::REGEXP_SCHEME, $scheme)) {
            throw new SyntaxError(sprintf("The scheme '%s' is invalid.", $scheme));
        }

        if (100 < count($inMemoryCache)) {
            unset($inMemoryCache[array_key_first($inMemoryCache)]);
        }

        return $inMemoryCache[$scheme] = strtolower($scheme);
    }

    public static function __set_state(array $properties): self
    {
        return new self($properties['scheme']);
    }

    /**
     * Create a new instance from a URI object.
     */
    public static function createFromUri(Psr7UriInterface|UriInterface $uri): self
    {
        if ($uri instanceof UriInterface) {
            return new self($uri->getScheme());
        }

        $component = $uri->getScheme();
        if ('' === $component) {
            return new self();
        }

        return new self($component);
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
     * Filter the input component.
     *
     * @throws SyntaxError If the component can not be converted to a string or null
     */
    protected static function filterComponent(UriComponentInterface|Stringable|float|int|string|bool|null $component): ?string
    {
        $component = parent::filterComponent($component);
        if (null !== $component) {
            return strtolower($component);
        }

        return $component;
    }
}

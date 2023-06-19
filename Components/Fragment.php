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

use League\Uri\Contracts\FragmentInterface;
use League\Uri\Contracts\UriInterface;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;

final class Fragment extends Component implements FragmentInterface
{
    private const REGEXP_FRAGMENT_ENCODING = '/[^A-Za-z0-9_\-.~!$&\'()*+,;=%:\/@?]+|%(?![A-Fa-f0-9]{2})/';

    private readonly ?string $fragment;

    /**
     * New instance.
     */
    private function __construct(Stringable|string|null $fragment)
    {
        $this->fragment = $this->validateComponent($fragment);
    }

    public static function new(Stringable|string|null $value = null): self
    {
        return new self($value);
    }

    /**
     * Create a new instance from a URI object.
     */
    public static function fromUri(Psr7UriInterface|UriInterface $uri): self
    {
        if ($uri instanceof UriInterface) {
            return new self($uri->getFragment());
        }

        $component = $uri->getFragment();
        if ('' === $component) {
            return new self(null);
        }

        return new self($component);
    }

    public function value(): ?string
    {
        return $this->encodeComponent($this->fragment, self::REGEXP_FRAGMENT_ENCODING);
    }

    public function getUriComponent(): string
    {
        return (null === $this->fragment ? '' : '#').$this->value();
    }

    /**
     * Returns the decoded fragment.
     */
    public function decoded(): ?string
    {
        return $this->fragment;
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Fragment::new()
     *
     * @codeCoverageIgnore
     */
    public static function createFromString(Stringable|string $fragment): self
    {
        return self::new($fragment);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Fragment::fromUri()
     *
     * @codeCoverageIgnore
     *
     * Create a new instance from a URI object.
     */
    public static function createFromUri(Psr7UriInterface|UriInterface $uri): self
    {
        return self::fromUri($uri);
    }
}

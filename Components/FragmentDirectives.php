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

use Countable;
use IteratorAggregate;
use League\Uri\Components\Directives\Directive;
use League\Uri\Components\Directives\GenericDirective;
use League\Uri\Components\Directives\TextDirective;
use League\Uri\Contracts\FragmentInterface;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriInterface;
use League\Uri\Encoder;
use League\Uri\Exceptions\OffsetOutOfBounds;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Modifier;
use League\Uri\Uri;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use Throwable;
use Traversable;
use Uri\Rfc3986\Uri as Rfc3986Uri;
use Uri\WhatWg\Url as WhatWgUrl;

use function array_count_values;
use function array_filter;
use function array_keys;
use function array_map;
use function array_slice;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_bool;
use function is_string;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;

use const ARRAY_FILTER_USE_BOTH;

/**
 * @implements IteratorAggregate<int, Directive>
 */
final class FragmentDirectives implements FragmentInterface, IteratorAggregate, Countable
{
    public const DELIMITER = ':~:';
    public const SEPARATOR = '&';

    /** @var list<Directive> */
    private array $directives;

    public function __construct(Directive|Stringable|string ...$directives)
    {
        $this->directives = array_values(array_map(self::filterDirective(...), $directives));
    }

    /**
     * Create a new instance.
     *
     * @throws SyntaxError
     */
    public static function new(Stringable|string|null $value): self
    {
        if (null === $value) {
            return new self();
        }

        $value = (string) $value;
        str_starts_with($value, self::DELIMITER) || throw new SyntaxError('The value "'.$value.'" is not a valid fragment directive.');

        return new self(...array_map(
            self::filterDirective(...),
            explode(
                self::SEPARATOR,
                substr($value, strlen(self::DELIMITER))
            )
        ));
    }

    private static function filterDirective(Directive|Stringable|string $directive): Directive
    {
        if ($directive instanceof Directive) {
            return $directive;
        }

        $directive = (string) $directive;

        return str_starts_with($directive, 'text=') ? TextDirective::fromString($directive) : GenericDirective::fromString($directive);
    }

    public static function tryNew(Stringable|string|null $value): ?self
    {
        try {
            return self::new($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     *  Create a new instance from a URI string or object.
     */
    public static function fromUri(WhatWgUrl|Rfc3986Uri|Stringable|string $uri): self
    {
        if ($uri instanceof Modifier) {
            $uri = $uri->uri();
        }

        if ($uri instanceof Rfc3986Uri) {
            return self::new($uri->getRawFragment());
        }

        if ($uri instanceof Psr7UriInterface) {
            $fragment = $uri->getFragment();

            return self::new('' === $fragment ? null : $fragment);
        }

        if (!$uri instanceof UriInterface && !$uri instanceof WhatWgUrl) {
            $uri = Uri::new($uri);
        }

        return self::new($uri->getFragment());
    }

    public function count(): int
    {
        return count($this->directives);
    }

    public function getIterator(): Traversable
    {
        yield from $this->directives;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    public function value(): ?string
    {
        if ([] === $this->directives) {
            return null;
        }

        return self::DELIMITER.implode(self::SEPARATOR, array_map(fn (Directive $directive): string => $directive->toString(), $this->directives));
    }

    public function toString(): string
    {
        return (string) $this->value();
    }

    public function getUriComponent(): string
    {
        $fragment = $this->value();

        return (null === $fragment ? '' : '#').$fragment;
    }

    public function decoded(): ?string
    {
        if ([] === $this->directives) {
            return null;
        }

        return  str_replace('%20', ' ', (string) Encoder::decodeFragment($this->toString()));
    }

    /**
     * Returns the Directive at a specified offset or null if none is defined.
     *
     * Negative offsets are supported.
     */
    public function nth(int $offset): ?Directive
    {
        if ($offset < 0) {
            $offset += count($this->directives);
        }

        return $this->directives[$offset] ?? null;
    }

    /**
     * The first Directive defined on the fragment or null if none are defined.
     */
    public function first(): ?Directive
    {
        return $this->nth(0);
    }

    /**
     * The last Directive defined on the fragment or null if none are defined.
     */
    public function last(): ?Directive
    {
        return $this->nth(-1);
    }

    /**
     * Tells whether all the submitted keys are present in the collection.
     *
     * Negative offsets are supported.
     */
    public function has(int ...$offsets): bool
    {
        $nbDirectives = count($this->directives);
        foreach ($offsets as $offset) {
            if ($offset < 0) {
                $offset += $nbDirectives;
            }

            if (! isset($this->directives[$offset])) {
                return false;
            }
        }

        return [] !== $offsets;
    }

    public function isEmpty(): bool
    {
        return [] === $this->directives;
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

    public function indexOf(Directive|Stringable|string $directive): ?int
    {
        $directive = self::filterDirective($directive);
        foreach ($this->directives as $offset => $innerDirective) {
            if ($innerDirective->equals($directive)) {
                return $offset;
            }
        }

        return null;
    }

    public function contains(Directive|Stringable|string $directive): bool
    {
        return null !== $this->indexOf($directive);
    }

    /**
     * Append one or more Directives to the fragment.
     */
    public function append(FragmentDirectives|Directive|Stringable|string ...$directives): self
    {
        $items = [];
        foreach ($directives as $directive) {
            if ($directive instanceof FragmentDirectives) {
                $items = [...$items, ...$directive];
                continue;
            }
            $items[] = $directive;
        }

        if ([] === $items) {
            return $this;
        }

        return new self(...$this->directives, ...array_map(self::filterDirective(...), $items));
    }

    /**
     * Prepend one or more Directives to the fragment.
     */
    public function prepend(FragmentDirectives|Directive|Stringable|string ...$directives): self
    {
        $items = [];
        foreach ($directives as $directive) {
            if ($directive instanceof FragmentDirectives) {
                $items = [...$items, ...$directive];
                continue;
            }
            $items[] = $directive;
        }

        if ([] === $items) {
            return $this;
        }

        return new self(...array_map(self::filterDirective(...), $items), ...$this->directives);
    }

    /**
     * Removes one or more Directives by offset from the fragment.
     */
    public function remove(int ...$keys): self
    {
        if ([] === $keys) {
            return $this;
        }

        $nbDirectives = count($this->directives);
        $deletedKeys = [];
        foreach ($keys as $key) {
            $value = $key;
            if ($value < 0) {
                $value += $nbDirectives;
            }

            isset($this->directives[$value]) || throw new OffsetOutOfBounds(sprintf('The key `%s` is invalid.', $key));
            $deletedKeys[] = $value;
        }

        $deletedKeys = array_keys(array_count_values($deletedKeys));

        return $this->filter(fn (Directive $directive, int $offset): bool => !in_array($offset, $deletedKeys, true)); /* @phpstan-ignore-line */
    }

    /**
     * Slices the fragment to remove Directives portions.
     */
    public function slice(int $offset, ?int $length = null): self
    {
        $nbDirectives = count($this->directives);
        ($offset >= -$nbDirectives && $offset <= $nbDirectives) || throw new OffsetOutOfBounds(sprintf('No diretive can be found at : `%s`.', $offset));

        $directives = array_slice($this->directives, $offset, $length);

        if ($directives === $this->directives) {
            return $this;
        }

        return new self(...$directives);
    }

    /**
     * Filter the Directives to return a new instance based on the callback.
     *
     * @param callable(Directive, int=): bool $callback
     */
    public function filter(callable $callback): self
    {
        $directives = array_filter($this->directives, $callback, ARRAY_FILTER_USE_BOTH);

        if ($directives === $this->directives) {
            return $this;
        }

        return new self(...$directives);
    }

    /**
     * Replace the Directive define at a specific offset.
     * Negative offsets are supported.
     *
     * If no Directive is found to the specified offset, an exception is thrown
     */
    public function replace(int $offset, Directive|Stringable|string $directive): self
    {
        $currentDirective = $this->nth($offset);
        null !== $currentDirective || throw new OffsetOutOfBounds(sprintf('The key `%s` is invalid.', $offset));

        $directive = self::filterDirective($directive);
        if ($directive::class === $currentDirective::class && $currentDirective->equals($directive)) {
            return $this;
        }

        if ($offset < 0) {
            $offset += count($this->directives);
        }

        $directives = $this->directives;
        $directives[$offset] = $directive;

        return new self(...$directives);
    }

    public function when(callable|bool $condition, callable $onSuccess, ?callable $onFail = null): self
    {
        if (!is_bool($condition)) {
            $condition = $condition($this);
        }

        return match (true) {
            $condition => $onSuccess($this),
            null !== $onFail => $onFail($this),
            default => $this,
        } ?? $this;
    }
}

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

use BackedEnum;
use Deprecated;
use Iterator;
use League\Uri\Contracts\AuthorityInterface;
use League\Uri\Contracts\DomainHostInterface;
use League\Uri\Contracts\HostInterface;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriException;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\OffsetOutOfBounds;
use League\Uri\Exceptions\SyntaxError;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use TypeError;
use Uri\Rfc3986\Uri as Rfc3986Uri;
use Uri\WhatWg\Url as WhatWgUrl;

use function array_count_values;
use function array_filter;
use function array_keys;
use function array_reverse;
use function array_shift;
use function count;
use function explode;
use function implode;
use function sprintf;
use function str_ends_with;

final class Domain extends Component implements DomainHostInterface
{
    private const SEPARATOR = '.';

    private readonly HostInterface $host;
    /** @var string[] */
    private readonly array $labels;

    private function __construct(BackedEnum|Stringable|string|null $host)
    {
        $host = match (true) {
            $host instanceof HostInterface => $host,
            $host instanceof UriComponentInterface => Host::new($host->value()),
            default => Host::new($host),
        };

        if (!$host->isDomain()) {
            throw new SyntaxError(sprintf('`%s` is an invalid domain name.', $host->value() ?? 'null'));
        }

        $this->host = $host;
        $this->labels = array_reverse(explode(self::SEPARATOR, $this->host->value() ?? ''));
    }

    /**
     * Returns a new instance from a string or a stringable object.
     */
    public static function new(BackedEnum|Stringable|string|null $value = null): self
    {
        return new self($value);
    }

    /**
     * Create a new instance from a string.or a stringable structure or returns null on failure.
     */
    public static function tryNew(BackedEnum|Stringable|string|null $uri = null): ?self
    {
        try {
            return self::new($uri);
        } catch (UriException) {
            return null;
        }
    }

    /**
     * Returns a new instance from an iterable structure.
     */
    public static function fromLabels(BackedEnum|Stringable|string ...$labels): self
    {
        return new self(match ([]) {
            $labels => null,
            default => implode(self::SEPARATOR, array_reverse(array_map(
                fn ($label) => self::filterComponent($label),
                $labels
            ))),
        });
    }

    /**
     * Create a new instance from a URI object.
     */
    public static function fromUri(WhatWgUrl|Rfc3986Uri|BackedEnum|Stringable|string $uri): self
    {
        return self::new(Host::fromUri($uri));
    }

    /**
     * Create a new instance from an Authority object.
     */
    public static function fromAuthority(BackedEnum|Stringable|string $authority): self
    {
        return self::new(Host::fromAuthority($authority));
    }

    public function value(): ?string
    {
        return $this->host->value();
    }

    public function equals(mixed $value): bool
    {
        return $this->host->equals($value);
    }

    public function toAscii(): ?string
    {
        return $this->host->toAscii();
    }

    public function toUnicode(): ?string
    {
        return $this->host->toUnicode();
    }

    public function encoded(): ?string
    {
        return $this->host->encoded();
    }

    public function isIp(): bool
    {
        return false;
    }

    public function isDomain(): bool
    {
        return true;
    }

    public function isRegisteredName(): bool
    {
        return true;
    }

    public function getIpVersion(): ?string
    {
        return null;
    }

    public function getIp(): ?string
    {
        return null;
    }

    public function count(): int
    {
        return count($this->labels);
    }

    public function getIterator(): Iterator
    {
        yield from $this->labels;
    }

    public function first(): ?string
    {
        return $this->get(0);
    }

    public function last(): ?string
    {
        return $this->get(-1);
    }

    public function indexOf(BackedEnum|Stringable|string $label): ?int
    {
        return $this->keys($label)[0] ?? null;
    }

    public function lastIndexOf(BackedEnum|Stringable|string $label): ?int
    {
        $res = $this->keys($label);

        return $res[count($res) - 1] ?? null;
    }

    public function contains(BackedEnum|Stringable|string $label): bool
    {
        return [] !== $this->keys($label);
    }

    public function isEmpty(): bool
    {
        return null === $this->host->value();
    }

    public function get(int $offset): ?string
    {
        if ($offset < 0) {
            $offset += count($this->labels);
        }

        return $this->labels[$offset] ?? null;
    }

    public function keys(BackedEnum|Stringable|string|null $label = null): array
    {
        if ($label instanceof BackedEnum) {
            $label = (string) $label->value;
        }

        return match (null) {
            $label => array_keys($this->labels),
            default => array_keys($this->labels, $label, true),
        };
    }

    public function isAbsolute(): bool
    {
        return count($this->labels) > 1 && '' === $this->labels[array_key_first($this->labels)];
    }

    public function isSubdomainOf(BackedEnum|Stringable|string|null $parentHost): bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        if (!$parentHost instanceof self) {
            $parentHost = self::tryNew($parentHost);
        }

        return null !== $parentHost
            && !$parentHost->isEmpty()
            && count($this) > count($parentHost)
            && str_ends_with(''.$this->withoutRootLabel()->toAscii(), '.'.$parentHost->withoutRootLabel()->toAscii());
    }

    public function hasSubdomain(BackedEnum|Stringable|string|null $childHost): bool
    {
        if (!$childHost instanceof self) {
            $childHost = self::tryNew($childHost);
        }

        return ($childHost?->isSubdomainOf($this)) ?? false;
    }

    public function isSiblingOf(BackedEnum|Stringable|string|null $siblingHost): bool
    {
        if (!$siblingHost instanceof self) {
            $siblingHost = self::tryNew($siblingHost);
        }

        return null !== $siblingHost
            && !$this->isEmpty()
            && !$siblingHost->isEmpty()
            && !$this->equals($siblingHost)
            && $this->parentHost()->equals($siblingHost->parentHost());
    }

    public function parentHost(): DomainHostInterface
    {
        return $this->withoutRootLabel()->slice(0, -1);
    }

    public function commonAncestorWith(BackedEnum|Stringable|string|null $other): DomainHostInterface
    {
        if (!$other instanceof self) {
            $other = self::tryNew($other);
        }

        if (null === $other) {
            return Domain::new();
        }

        $other = $other->withoutRootLabel();
        $current = $this->withoutRootLabel();
        $labels = [];
        /** @var int $offset */
        foreach ($current as $offset => $label) {
            if ($label !== $other->get($offset)) {
                break;
            }

            $labels[] = $label;
        }

        return Domain::fromLabels(...$labels);
    }

    public function prepend(BackedEnum|Stringable|string|int|null $label): DomainHostInterface
    {
        $label = self::filterComponent($label);
        $value = $this->value();

        return match (true) {
            null === $label => $this,
            null === $value => new self($label),
            str_ends_with($label, self::SEPARATOR) => new self($label.$value),
            default => new self($label.self::SEPARATOR.$value),
        };
    }

    public function append(BackedEnum|Stringable|string|int|null $label): DomainHostInterface
    {
        $label = self::filterComponent($label);
        $value = $this->value();

        return match (true) {
            null === $label => $this,
            null === $value => new self($label),
            !$this->isAbsolute() => new self($value.self::SEPARATOR.$label),
            str_ends_with($label, self::SEPARATOR) => new self($value.$label),
            default => new self($value.$label.self::SEPARATOR),
        };
    }

    public function withRootLabel(): DomainHostInterface
    {
        $key = array_key_first($this->labels);

        return match ($this->labels[$key]) {
            '' => $this,
            default => $this->append(''),
        };
    }

    public function slice(int $offset, ?int $length = null): self
    {
        $nbLabels = count($this->labels);
        if ($offset < -$nbLabels || $offset > $nbLabels) {
            throw new OffsetOutOfBounds(sprintf('No label can be found with at : `%s`.', $offset));
        }

        $labels = array_slice($this->labels, $offset, $length, true);

        return match ($labels) {
            $this->labels => $this,
            default => self::fromLabels(...$labels),
        };
    }

    public function withoutRootLabel(): DomainHostInterface
    {
        $key = array_key_first($this->labels);
        if ('' !== $this->labels[$key]) {
            return $this;
        }

        $labels = $this->labels;
        array_shift($labels);

        return self::fromLabels(...$labels);
    }

    /**
     * @throws OffsetOutOfBounds
     */
    public function withLabel(int $key, BackedEnum|Stringable|string|int|null $label): DomainHostInterface
    {
        $nbLabels = count($this->labels);
        if ($key < - $nbLabels - 1 || $key > $nbLabels) {
            throw new OffsetOutOfBounds(sprintf('No label can be added with the submitted key : `%s`.', $key));
        }

        if (0 > $key) {
            $key += $nbLabels;
        }

        if ($nbLabels === $key) {
            return $this->append($label);
        }

        if (-1 === $key) {
            return $this->prepend($label);
        }

        if (!$label instanceof HostInterface && null !== $label) {
            if (is_int($label)) {
                $label = (string) $label;
            }

            $label = Host::new($label)->value();
        }

        if ($label === $this->labels[$key]) {
            return $this;
        }

        $labels = $this->labels;
        $labels[$key] = $label;

        return new self(implode(self::SEPARATOR, array_reverse($labels)));
    }

    public function withoutLabel(int ...$keys): DomainHostInterface
    {
        if ([] === $keys) {
            return $this;
        }

        $nb_labels = count($this->labels);
        foreach ($keys as &$offset) {
            if (- $nb_labels > $offset || $nb_labels - 1 < $offset) {
                throw new OffsetOutOfBounds(sprintf('No label can be removed with the submitted key : `%s`.', $offset));
            }

            if (0 > $offset) {
                $offset += $nb_labels;
            }
        }
        unset($offset);

        $deleted_keys = array_keys(array_count_values($keys));
        $filter = static fn ($key): bool => !in_array($key, $deleted_keys, true);

        return self::fromLabels(...array_filter($this->labels, $filter, ARRAY_FILTER_USE_KEY));
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Domain::getIterator()
     *
     * @codeCoverageIgnore
     *
     * Returns a new instance from a string or a stringable object.
     */
    #[Deprecated(message:'use League\Uri\Components\Domain::getIterator() instead', since:'league/uri-components:7.0.0')]
    public function labels(): array
    {
        return $this->labels;
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Domain::new()
     *
     * @codeCoverageIgnore
     *
     * Returns a new instance from a string or a stringable object.
     */
    #[Deprecated(message:'use League\Uri\Components\Domain::new() instead', since:'league/uri-components:7.0.0')]
    public static function createFromString(Stringable|string $host): self
    {
        return self::new($host);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Domain::fromLabels()
     *
     * @codeCoverageIgnore
     *
     * Returns a new instance from an iterable structure.
     *
     * @throws TypeError If a label is the null value
     */
    #[Deprecated(message:'use League\Uri\Components\Domain::fromLabels() instead', since:'league/uri-components:7.0.0')]
    public static function createFromLabels(iterable $labels): self
    {
        return self::fromLabels(...$labels);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Domain::fromUri()
     *
     * @codeCoverageIgnore
     *
     * Create a new instance from a URI object.
     */
    #[Deprecated(message:'use League\Uri\Components\Domain::fromUri() instead', since:'league/uri-components:7.0.0')]
    public static function createFromUri(Psr7UriInterface|UriInterface $uri): self
    {
        return self::fromUri($uri);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Domain::fromAuthority()
     *
     * @codeCoverageIgnore
     *
     * Create a new instance from an Authority object.
     */
    #[Deprecated(message:'use League\Uri\Components\Domain::fromAuthority() instead', since:'league/uri-components:7.0.0')]
    public static function createFromAuthority(AuthorityInterface|Stringable|string $authority): self
    {
        return self::fromAuthority($authority);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.0.0
     * @see Domain::new()
     *
     * @codeCoverageIgnore
     *
     * Returns a new instance from an iterable structure.
     */
    #[Deprecated(message:'use League\Uri\Components\Domain::new() instead', since:'league/uri-components:7.0.0')]
    public static function createFromHost(HostInterface $host): self
    {
        return self::new($host);
    }
}

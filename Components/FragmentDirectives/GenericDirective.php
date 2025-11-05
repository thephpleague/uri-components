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

namespace League\Uri\Components\FragmentDirectives;

use League\Uri\Contracts\FragmentDirective;
use League\Uri\Encoder;
use League\Uri\Exceptions\SyntaxError;
use Stringable;
use Throwable;

use function explode;
use function str_replace;

final class GenericDirective implements FragmentDirective
{
    /**
     * @param non-empty-string $name
     */
    private function __construct(
        private readonly string $name,
        private readonly ?string $value = null,
    ) {
    }

    /**
     * Create a new instance from a string without the Directive delimiter (:~:) or a separator (&).
     */
    public static function fromString(Stringable|string $value): self
    {
        [$name, $value] = explode('=', (string) $value, 2) + [1 => null];
        (null !== $name && '' !== $name && !str_contains($name, '&')) || throw new SyntaxError('The submitted text is not a valid directive.');

        return new self($name, $value);
    }

    private static function decode(?string $value): ?string
    {
        return null !== $value ? str_replace('%20', ' ', (string) Encoder::decodeFragment($value)) : null;
    }

    public function name(): string
    {
        /** @var non-empty-string $name */
        $name = (string) self::decode($this->name);

        return $name;
    }

    public function value(): ?string
    {
        return self::decode($this->value);
    }

    public function toString(): string
    {
        $str = $this->name;
        if (null === $this->value) {
            return $str;
        }

        return $str.'='.$this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function equals(mixed $directive): bool
    {
        if (!$directive instanceof Stringable && !is_string($directive)) {
            return false;
        }

        if (!$directive instanceof FragmentDirective) {
            try {
                $directive = self::fromString($directive);
            } catch (Throwable) {
                return false;
            }
        }

        return $directive->toString() === $this->toString();
    }
}

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
use function is_string;
use function preg_match;
use function str_replace;

final class TextDirective implements FragmentDirective
{
    private const NAME = 'text';

    private const REGEXP_PATTERN = '/^
        (?:(?<prefix>.+?)-,)?    # optional prefix up to first "-,"
        (?<start>[^,]+?)         # required start (up to "," or end)
        (?:,(?<end>[^,-]*),?)?   # optional end, stop before ",-" if present
        (?:,-(?<suffix>.+))?     # optional suffix (to end)
    $/x';

    /**
     * @param non-empty-string $start
     * @param ?non-empty-string $end
     * @param ?non-empty-string $prefix
     * @param ?non-empty-string $suffix
     */
    public function __construct(
        public readonly string $start,
        public readonly ?string $end = null,
        public readonly ?string $prefix = null,
        public readonly ?string $suffix = null,
    ) {
        ('' !== $this->start && '' !== $this->end && '' !== $this->prefix && '' !== $this->suffix)
        || throw new SyntaxError('The start part can not be the empty string.');
    }

    /**
     * Create a new instance from a string without the Directive delimiter (:~:) or a separator (&).
     */
    public static function fromString(Stringable|string $value): self
    {
        [$name, $value] = explode('=', (string) $value, 2) + [1 => ''];
        self::NAME === $name || throw new SyntaxError('The submitted text is not a text directive.');

        return self::fromValue($value);
    }

    /**
     * Create a new instance from a string without the Directive name and the separator (=).
     */
    public static function fromValue(Stringable|string $text): self
    {
        '' !== $text || throw new SyntaxError('The text directive value can not be the empty string.');
        1 === preg_match(self::REGEXP_PATTERN, (string) $text, $matches) || throw new SyntaxError('The text directive is malformed.');
        if ('' === $matches['prefix']) {
            $matches['prefix'] = null;
        }

        /** @var non-empty-string $start */
        $start = (string) self::decode($matches['start']);
        /** @var ?non-empty-string $prefix */
        $prefix = self::decode($matches['prefix']);
        /** @var ?non-empty-string $suffix */
        $suffix = self::decode($matches['suffix'] ?? null);
        $matches['end'] ??= null;
        if ('' === $matches['end']) {
            $matches['end'] = null;
        }
        /** @var ?non-empty-string $end */
        $end = self::decode($matches['end']);

        return new self($start, $end, $prefix, $suffix);
    }

    private static function encode(?string $value): ?string
    {
        return null !== $value ? strtr((string) Encoder::encodeQueryOrFragment($value), ['-' => '%2D', ',' => '%2C', '&' => '%26']) : null;
    }

    private static function decode(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return str_replace('%20', ' ', (string) Encoder::decodeFragment($value));
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function value(): string
    {
        $str = $this->start;
        if (null !== $this->prefix) {
            $str = $this->prefix.'-,'.$str;
        }

        if (null !== $this->end) {
            $str .= ','.$this->end;
        }

        if (null !== $this->suffix) {
            $str .= ',-'.$this->suffix;
        }

        return $str;
    }

    public function toString(): string
    {
        $encodedValue = (string) self::encode($this->start);

        $prefix = self::encode($this->prefix);
        if (null !== $prefix) {
            $encodedValue = $prefix.'-,'.$encodedValue;
        }

        $end = self::encode($this->end);
        if (null !== $end) {
            $encodedValue .= ','.$end;
        }

        $suffix = self::encode($this->suffix);
        if (null !== $suffix) {
            $encodedValue .= ',-'.$suffix;
        }

        return self::NAME.'='.$encodedValue;
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

    /**
     * Returns a new instance with a new start portion.
     *
     * The submitted string must be in its decoded form
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the new start portion.
     *
     * @param non-empty-string $text
     */
    public function startsWith(string $text): self
    {
        if ($this->start === $text) {
            return $this;
        }

        return new self($text, $this->end, $this->prefix, $this->suffix);
    }

    /**
     * Returns a new instance with a new end portion.
     *
     * The submitted string must be in its decoded form
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the new end portion.
     *
     * @param ?non-empty-string $text
     */
    public function endsWith(?string $text): self
    {
        if ($this->end === $text) {
            return $this;
        }

        return new self($this->start, $text, $this->prefix, $this->suffix);
    }

    /**
     * Returns a new instance with a new suffix portion.
     *
     * The submitted string must be in its decoded form
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the new suffix portion.
     *
     * @param ?non-empty-string $text
     */
    public function followedBy(?string $text): self
    {
        if ($this->suffix === $text) {
            return $this;
        }

        return new self($this->start, $this->end, $this->prefix, $text);
    }

    /**
     * Returns a new instance with a new prefix portion.
     *
     *  This method MUST retain the state of the current instance, and return
     *  an instance that contains the new prefix portion.
     *
     * @param ?non-empty-string $text
     */
    public function precededBy(?string $text): self
    {
        if ($this->prefix === $text) {
            return $this;
        }

        return new self($this->start, $this->end, $text, $this->suffix);
    }
}

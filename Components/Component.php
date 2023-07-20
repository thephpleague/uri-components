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

use League\Uri\BaseUri;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Uri;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use function preg_match;
use function preg_replace_callback;
use function rawurldecode;
use function rawurlencode;
use function sprintf;
use function strtoupper;

abstract class Component implements UriComponentInterface
{
    protected const REGEXP_ENCODED_CHARS = ',%[A-Fa-f0-9]{2},';
    protected const REGEXP_INVALID_URI_CHARS = '/[\x00-\x1f\x7f]/';
    protected const REGEXP_NO_ENCODING = '/[^A-Za-z0-9_\-.~]/';
    protected const REGEXP_NON_ASCII_PATTERN = '/[^\x20-\x7f]/';
    protected const REGEXP_PREVENTS_DECODING = ',%
     	2[A-F|1-2|4-9]|
        3[0-9|B|D]|
        4[1-9|A-F]|
        5[0-9|A|F]|
        6[1-9|A-F]|
        7[0-9|E]
    ,ix';

    abstract public function value(): ?string;

    public function jsonSerialize(): ?string
    {
        return $this->value();
    }

    public function toString(): string
    {
        return $this->value() ?? '';
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function getUriComponent(): string
    {
        return $this->toString();
    }

    final protected static function filterUri(Stringable|string $uri): UriInterface|Psr7UriInterface
    {
        return match (true) {
            $uri instanceof BaseUri => $uri->getUri(),
            $uri instanceof Psr7UriInterface, $uri instanceof UriInterface => $uri,
            default => Uri::new($uri),
        };
    }

    /**
     * Validate the component content.
     */
    protected function validateComponent(Stringable|int|string|null $component): ?string
    {
        return $this->decodeComponent(self::filterComponent($component));
    }

    /**
     * Filter the input component.
     *
     * @throws SyntaxError If the component can not be converted to a string or null
     */
    final protected static function filterComponent(Stringable|int|string|null $component): ?string
    {
        return match (true) {
            $component instanceof UriComponentInterface => $component->value(),
            null === $component => null,
            1 === preg_match(self::REGEXP_INVALID_URI_CHARS, (string) $component) => throw new SyntaxError(sprintf('Invalid component string: %s.', $component)),
            default => (string) $component,
        };
    }

    /**
     * Filter the URI password component.
     */
    protected function decodeComponent(?string $str): ?string
    {
        return match (true) {
            null === $str => null,
            default => preg_replace_callback(self::REGEXP_ENCODED_CHARS, $this->decodeMatches(...), $str),
        };
    }

    /**
     * Decodes Matches sequence.
     */
    protected function decodeMatches(array $matches): string
    {
        return match (true) {
            1 === preg_match(static::REGEXP_PREVENTS_DECODING, $matches[0]) => strtoupper($matches[0]),
            default => rawurldecode($matches[0]),
        };
    }

    /**
     * Returns the component as converted for RFC3986.
     */
    protected function encodeComponent(?string $str, string $regexp): ?string
    {
        return match (true) {
            null === $str || 1 !== preg_match(self::REGEXP_NO_ENCODING, $str) => $str,
            default => preg_replace_callback($regexp, $this->encodeMatches(...), $str) ?? rawurlencode($str),
        };
    }

    /**
     * Encode Matches sequence.
     */
    protected function encodeMatches(array $matches): string
    {
        return rawurlencode($matches[0]);
    }
}

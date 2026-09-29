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

use BackedEnum;
use League\Uri\Exceptions\SyntaxError;
use Stringable;

use function preg_match;
use function preg_replace_callback;
use function rawurldecode;
use function rawurlencode;
use function str_replace;

final class Encoder
{
    private const REGEXP_CHARS_INVALID = ',[\x00-\x1f\x7f],';
    private const REGEXP_CHARS_NOT_UNRESERVED = '/[^A-Za-z0-9_\-.~]/';
    private const REGEXP_CHARS_ENCODED = ',%[A-Fa-f0-9]{2},';
    private const REGEXP_CHARS_TO_ENCODE = '/[^A-Za-z0-9_\-.~!$&\'()*+,;=%:@\/?]+|%(?![A-Fa-f0-9]{2})/';

    private function __construct()
    {
    }

    public static function encode(BackedEnum|Stringable|string|null $value): ?string
    {
        $value = self::normalizeInput($value);
        if (null === $value || '' === $value) {
            return $value;
        }

        $encoder = static fn (array $matches): string => 1 === preg_match(self::REGEXP_CHARS_NOT_UNRESERVED, rawurldecode($matches[0]))
            ? rawurlencode($matches[0])
            : $matches[0];

        $encoded = (string) preg_replace_callback(self::REGEXP_CHARS_TO_ENCODE, $encoder, $value);

        return strtr($encoded, ['-' => '%2D', ',' => '%2C', '&' => '%26']);
    }

    /**
     * @throws SyntaxError if the value contains invalid encoded characters
     */
    public static function decode(BackedEnum|Stringable|string|null $value): ?string
    {
        $value = self::normalizeInput($value);
        if (null === $value || '' === $value) {
            return $value;
        }

        1 !== preg_match(self::REGEXP_CHARS_INVALID, $value) || throw new SyntaxError('The value contains invalid encoded characters; "'.$value.'".');

        $decoder = static fn (array $matches): string => match ($matches[0]) {
            '%20', '%2F' => $matches[0],
            default => rawurldecode($matches[0]),
        };

        $decoded = (string) preg_replace_callback(self::REGEXP_CHARS_ENCODED, $decoder, $value);

        return str_replace('%20', ' ', $decoded);
    }

    private static function normalizeInput(BackedEnum|Stringable|string|null $value): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof BackedEnum => (string) $value->value,
            default => (string) $value,
        };
    }
}

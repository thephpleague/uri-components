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
use Stringable;

use function preg_match;

final class DirectiveString
{
    /**
     * Parse a Directive string representation.
     *
     * A Directive syntax is name[=value] where the
     * separator `=` is not present when no value
     * is attached to it
     */
    public static function resolve(Stringable|string $directive): FragmentDirective
    {
        $directive = (string) $directive;

        return match (true) {
            1 === preg_match('/^text(?:=|$)/i', $directive) => TextDirective::fromString($directive),
            default => GenericDirective::fromString($directive),
        };
    }
}

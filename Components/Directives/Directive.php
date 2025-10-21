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

namespace League\Uri\Components\Directives;

use Stringable;

/**
 * @see https://wicg.github.io/scroll-to-text-fragment/#the-fragment-directive
 */
interface Directive extends Stringable
{
    /**
     * The Directive name decoded.
     *
     * @return non-empty-string
     */
    public function name(): string;

    /**
     * The Directive value decoded.
     */
    public function value(): ?string;

    /**
     * The encoded string representation of the fragment.
     */
    public function toString(): string;

    /**
     * The encoded string representation of the fragment using
     * the Stringable interface.
     *
     * @see Directive::toString()
     */
    public function __toString(): string;
}

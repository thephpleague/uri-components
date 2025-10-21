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

namespace Components\Directives;

use League\Uri\Components\Directives\GenericDirective;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GenericDirective::class)]
final class GenericDirectiveTest extends TestCase
{
    public function test_it_can_access_its_properties(): void
    {
        $directive = GenericDirective::fromString('text=prefix-,st%26art,-suffix');

        self::assertSame('text=prefix-,st%26art,-suffix', $directive->toString());
        self::assertSame('prefix-,st&art,-suffix', $directive->value());
        self::assertSame('text', $directive->name());
    }

    public function test_it_can_access_its_properties_with_no_value(): void
    {
        $directive = GenericDirective::fromString('unknownDirective');

        self::assertSame('unknownDirective', (string) $directive);
        self::assertNull($directive->value());
        self::assertSame('unknownDirective', $directive->name());
    }
}

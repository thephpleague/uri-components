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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(TextDirective::class)]
final class TextDirectiveTest extends TestCase
{
    #[DataProvider('provideValidFragmentTextDirectives')]
    public function testToString(TextDirective $fragmentTextDirective, string $expected): void
    {
        self::assertSame($expected, (string) $fragmentTextDirective);
    }

    #[DataProvider('provideValidFragmentTextDirectives')]
    public function test_it_can_be_created_from_string(TextDirective $fragmentTextDirective, string $expected): void
    {
        self::assertEquals($fragmentTextDirective, TextDirective::fromString($expected));
    }

    public function testToStringEncodesSpecialCharacters(): void
    {
        $fragmentTextDirective = new TextDirective('st&rt', 'e,nd', 'prefix-', '-&suffix');

        self::assertSame('text=prefix%2D-,st%26rt,e%2Cnd,-%2D%26suffix', (string) $fragmentTextDirective);
    }

    public static function provideValidFragmentTextDirectives(): iterable
    {
        yield [new TextDirective('start'), 'text=start'];
        yield [new TextDirective('start', 'end'), 'text=start,end'];
        yield [new TextDirective('start', 'end', 'prefix'), 'text=prefix-,start,end'];
        yield [new TextDirective('start', 'end', 'prefix', 'suffix'), 'text=prefix-,start,end,-suffix'];
        yield [new TextDirective('start', prefix: 'prefix', suffix: 'suffix'), 'text=prefix-,start,-suffix'];
        yield [new TextDirective('start', suffix: 'suffix'), 'text=start,-suffix'];
        yield [new TextDirective('start', prefix: 'prefix'), 'text=prefix-,start'];
    }

    public static function testClasswithers(): void
    {
        $directive = (new TextDirective('foo'))
            ->startsWith('start')
            ->startsWith('start')
            ->endsWith('end')
            ->endsWith('end')
            ->followedBy('suffix')
            ->followedBy('suffix')
            ->precededBy('prefix')
            ->precededBy('prefix');

        self::assertSame('start', $directive->start);
        self::assertSame('end', $directive->end);
        self::assertSame('prefix', $directive->prefix);
        self::assertSame('suffix', $directive->suffix);
    }

    public function test_it_can_return_the_default_properties(): void
    {
        $directive = new TextDirective('st&art', prefix: 'prefix', suffix: 'suffix');
        self::assertSame('text', $directive->name());
        self::assertSame('st&art', $directive->start);
        self::assertSame('prefix', $directive->prefix);
        self::assertSame('suffix', $directive->suffix);
        self::assertNull($directive->end);
        self::assertSame('prefix-,st&art,-suffix', $directive->value());
        self::assertSame('text=prefix-,st%26art,-suffix', $directive->toString());
    }

    public function test_it_can_tell_if_its_value_are_identical(): void
    {
        $directive = new TextDirective('st&art', prefix: 'prefix', suffix: 'suffix');
        $inputText = $directive->toString();

        self::assertTrue($directive->equals($inputText));
        self::assertTrue($directive->equals(TextDirective::fromString($inputText)));
        self::assertTrue($directive->equals(GenericDirective::fromString($inputText)));
        self::assertFalse($directive->equals('unknownDirective'));
        self::assertFalse($directive->equals(new stdClass()));
    }
}

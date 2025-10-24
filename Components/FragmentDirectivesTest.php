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

use League\Uri\Components\FragmentDirectives\Directive;
use League\Uri\Components\FragmentDirectives\GenericDirective;
use League\Uri\Components\FragmentDirectives\TextDirective;
use PHPUnit\Framework\TestCase;
use stdClass;

use function PHPUnit\Framework\assertInstanceOf;

final class FragmentDirectivesTest extends TestCase
{
    public function test_it_can_be_instantiated_with_the_constructor(): void
    {
        $fragment = new FragmentDirectives(
            new TextDirective(start:'linked URL', end:"-'s format"),
            new TextDirective(start: 'attributes', end: 'attribute', prefix: 'Deprecated'),
            'mydirectives=bbrown',
            'mydirection=maitreGims'
        );

        self::assertCount(4, $fragment);
        self::assertCount(2, $fragment->filter(fn (Directive $directive): bool =>  $directive instanceof GenericDirective));
        self::assertInstanceOf(Directive::class, $fragment->last());
        self::assertSame('maitreGims', $fragment->last()->value());
        self::assertInstanceOf(Directive::class, $fragment->first());
        self::assertSame('text', $fragment->first()->name());
        self::assertSame(
            "#:~:text=linked%20URL,%2D's%20format&text=Deprecated-,attributes,attribute&mydirectives=bbrown&mydirection=maitreGims",
            $fragment->getUriComponent()
        );

        self::assertTrue($fragment->contains($fragment->last()));
        self::assertTrue($fragment->contains('mydirection=maitreGims'));
        self::assertFalse($fragment->contains('mydirection=bbrown'));
        self::assertTrue($fragment->has(0, 2));
        self::assertFalse($fragment->has(0, 2, 42));

        $removedFragment = $fragment->remove(0, 2);

        self::assertCount(2, $removedFragment);
        assertInstanceOf(Directive::class, $fragment->nth(1));
        self::assertTrue($removedFragment->contains($fragment->nth(1)));
        self::assertFalse($removedFragment->contains($fragment->first()));
        self::assertSame($fragment->last(), $removedFragment->nth(1));

        $fragmentBis = FragmentDirectives::new(":~:text=linked%20URL,%2D's%20format&text=Deprecated-,attributes,attribute&mydirectives=bbrown&mydirection=maitreGims");
        self::assertSame($fragmentBis->value(), $fragment->value());

        self::assertNull(FragmentDirectives::tryNew('foobar'));
    }

    public function test_it_can_tell_if_its_value_are_identical(): void
    {
        $inputText = ':~:text=linked%20URL,%2Ds%20format';
        $fragment =  FragmentDirectives::new($inputText);

        self::assertTrue($fragment->equals($inputText));
        self::assertFalse($fragment->equals(':~:unknownDirective'));
        self::assertFalse($fragment->equals('invalid fragment'));
        self::assertFalse($fragment->equals(new stdClass()));
    }
}

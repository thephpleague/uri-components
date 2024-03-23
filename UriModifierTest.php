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

namespace League\Uri;

use PHPUnit\Framework\TestCase;

final class UriModifierTest extends TestCase
{
    /** @test */
    public function it_will_remove_empty_pairs_fix_issue_133(): void
    {
        $removeEmptyPairs = fn (string $str): ?string => UriModifier::removeEmptyPairs(Http::createFromString($str))->getQuery(); /* @phpstan-ignore-line */

        self::assertSame('', $removeEmptyPairs('https://a.b/c?d='));
        self::assertSame('', $removeEmptyPairs('https://a.b/c?=d'));
        self::assertSame('', $removeEmptyPairs('https://a.b/c?='));
    }
}

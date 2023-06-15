<?php

/**
 * League.Uri (https://uri.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Uri;

use PHPUnit\Framework\TestCase;

/**
 * @group query
 * @group resolution
 * @coversDefaultClass \League\Uri\UriModifier
 */
final class QueryModifierTest extends TestCase
{
    private string $uri;

    protected function setUp(): void
    {
        $this->uri = 'http://www.example.com/path/to/the/sky.php?kingkong=toto&foo=bar%20baz#doc3';
    }

    /**
     * @dataProvider validMergeQueryProvider
     */
    public function testMergeQuery(string $query, string $expected): void
    {
        self::assertSame($expected, UriModifier::mergeQuery($this->uri, $query)->getQuery());
    }

    public static function validMergeQueryProvider(): array
    {
        return [
            ['toto', 'kingkong=toto&foo=bar%20baz&toto'],
            ['kingkong=ape', 'kingkong=ape&foo=bar%20baz'],
        ];
    }

    /**
     * @dataProvider validAppendQueryProvider
     */
    public function testAppendQuery(string $query, string $expected): void
    {
        self::assertSame($expected, UriModifier::appendQuery($this->uri, $query)->getQuery());
    }

    public static function validAppendQueryProvider(): array
    {
        return [
            ['toto', 'kingkong=toto&foo=bar%20baz&toto'],
            ['kingkong=ape', 'kingkong=toto&foo=bar%20baz&kingkong=ape'],
        ];
    }

    public function testKsortQuery(): void
    {
        $uri = Http::fromString('http://example.com/?kingkong=toto&foo=bar%20baz&kingkong=ape');
        self::assertSame('kingkong=toto&kingkong=ape&foo=bar%20baz', UriModifier::sortQuery($uri)->getQuery());
    }

    /**
     * @dataProvider validWithoutQueryValuesProvider
     */
    public function testWithoutQueryValuesProcess(array $input, string $expected): void
    {
        self::assertSame($expected, UriModifier::removePairs($this->uri, ...$input)->getQuery());
    }

    public static function validWithoutQueryValuesProvider(): array
    {
        return [
            [['1'], 'kingkong=toto&foo=bar%20baz'],
            [['kingkong'], 'foo=bar%20baz'],
        ];
    }

    /**
     * @dataProvider removeParamsProvider
     */
    public function testWithoutQueryParams(string $uri, array $input, ?string $expected): void
    {
        self::assertSame($expected, UriModifier::removeParams(Uri::fromBaseUri($uri), ...$input)->getQuery());
    }

    public static function removeParamsProvider(): array
    {
        return [
            [
                'uri' => 'http://example.com',
                'input' => ['foo'],
                'expected' => null,
            ],
            [
                'uri' => 'http://example.com?foo=bar&bar=baz',
                'input' => ['foo'],
                'expected' => 'bar=baz',
            ],
            [
                'uri' => 'http://example.com?fo.o=bar&fo_o=baz',
                'input' => ['fo_o'],
                'expected' => 'fo.o=bar',
            ],
        ];
    }

    /**
     * @dataProvider removeEmptyPairsProvider
     */
    public function testRemoveEmptyPairs(string $uri, ?string $expected): void
    {
        self::assertSame($expected, UriModifier::removeEmptyPairs(Uri::fromBaseUri($uri))->__toString());
        self::assertSame($expected, UriModifier::removeEmptyPairs(Http::fromBaseUri($uri))->__toString());
    }

    public static function removeEmptyPairsProvider(): iterable
    {
        return [
            'null query component' => [
                'uri' => 'http://example.com',
                'expected' => 'http://example.com',
            ],
            'empty query component' => [
                'uri' => 'http://example.com?',
                'expected' => 'http://example.com',
            ],
            'no empty pair query component' => [
                'uri' => 'http://example.com?foo=bar',
                'expected' => 'http://example.com?foo=bar',
            ],
            'with empty pair as last pair' => [
                'uri' => 'http://example.com?foo=bar&',
                'expected' => 'http://example.com?foo=bar',
            ],
            'with empty pair as first pair' => [
                'uri' => 'http://example.com?&foo=bar',
                'expected' => 'http://example.com?foo=bar',
            ],
            'with empty pair inside the component' => [
                'uri' => 'http://example.com?foo=bar&&&&bar=baz',
                'expected' => 'http://example.com?foo=bar&bar=baz',
            ],
        ];
    }
}

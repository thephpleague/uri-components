<?php

/**
 * League.Uri (https://uri.thephpleague.com/components/2.0/)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @link       https://github.com/thephpleague/uri-components
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
    /**
     * @var Http
     */
    private $uri;

    protected function setUp(): void
    {
        $this->uri = Http::createFromString(
            'http://www.example.com/path/to/the/sky.php?kingkong=toto&foo=bar%20baz#doc3'
        );
    }

    /**
     * @covers ::mergeQuery
     * @covers ::normalizeComponent
     *
     * @dataProvider validMergeQueryProvider
     */
    public function testMergeQuery(string $query, string $expected): void
    {
        self::assertSame($expected, UriModifier::mergeQuery($this->uri, $query)->getQuery());
    }

    public function validMergeQueryProvider(): array
    {
        return [
            ['toto', 'kingkong=toto&foo=bar%20baz&toto'],
            ['kingkong=ape', 'kingkong=ape&foo=bar%20baz'],
        ];
    }

    /**
     * @covers ::appendQuery
     * @covers ::normalizeComponent
     *
     * @dataProvider validAppendQueryProvider
     */
    public function testAppendQuery(string $query, string $expected): void
    {
        self::assertSame($expected, UriModifier::appendQuery($this->uri, $query)->getQuery());
    }

    public function validAppendQueryProvider(): array
    {
        return [
            ['toto', 'kingkong=toto&foo=bar%20baz&toto'],
            ['kingkong=ape', 'kingkong=toto&foo=bar%20baz&kingkong=ape'],
        ];
    }

    /**
     * @covers ::sortQuery
     * @covers ::normalizeComponent
     */
    public function testKsortQuery(): void
    {
        $uri = Http::createFromString('http://example.com/?kingkong=toto&foo=bar%20baz&kingkong=ape');
        self::assertSame('kingkong=toto&kingkong=ape&foo=bar%20baz', UriModifier::sortQuery($uri)->getQuery());
    }

    /**
     * @dataProvider validWithoutQueryValuesProvider
     *
     * @covers ::removePairs
     * @covers ::normalizeComponent
     */
    public function testWithoutQueryValuesProcess(array $input, string $expected): void
    {
        self::assertSame($expected, UriModifier::removePairs($this->uri, ...$input)->getQuery());
    }

    public function validWithoutQueryValuesProvider(): array
    {
        return [
            [['1'], 'kingkong=toto&foo=bar%20baz'],
            [['kingkong'], 'foo=bar%20baz'],
        ];
    }

    /**
     * @dataProvider removeParamsProvider
     *
     * @covers ::removeParams
     * @covers ::normalizeComponent
     *
     * @param ?string $expected
     */
    public function testWithoutQueryParams(string $uri, array $input, ?string $expected): void
    {
        self::assertSame($expected, UriModifier::removeParams(Uri::createFromBaseUri($uri), ...$input)->getQuery());
    }

    public function removeParamsProvider(): array
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
     *
     * @covers ::removeEmptyPairs
     * @covers ::normalizeComponent
     *
     * @param ?string $expected
     */
    public function testRemoveEmptyPairs(string $uri, ?string $expected): void
    {
        self::assertSame($expected, UriModifier::removeEmptyPairs(Uri::createFromBaseUri($uri))->__toString());
        self::assertSame($expected, UriModifier::removeEmptyPairs(Http::createFromBaseUri($uri))->__toString());
    }

    public function removeEmptyPairsProvider(): iterable
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

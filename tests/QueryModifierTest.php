<?php

/**
 * League.Uri (http://uri.thephpleague.com/components).
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.0
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Uri;

use League\Uri\Component\Query;
use League\Uri\Http;
use League\Uri\Resolution;
use PHPUnit\Framework\TestCase;
use function League\Uri\create;

/**
 * @group query
 * @group resolution
 * @coversDefaultClass \League\Uri\Resolution
 */
class QueryModifierTest extends TestCase
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
     *
     * @dataProvider validMergeQueryProvider
     *
     */
    public function testMergeQuery(string $query, string $expected): void
    {
        self::assertSame($expected, Resolution::mergeQuery($this->uri, $query)->getQuery());
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
     *
     * @dataProvider validAppendQueryProvider
     *
     */
    public function testAppendQuery(string $query, string $expected): void
    {
        self::assertSame($expected, Resolution::appendQuery($this->uri, $query)->getQuery());
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
     */
    public function testKsortQuery(): void
    {
        $uri = Http::createFromString('http://example.com/?kingkong=toto&foo=bar%20baz&kingkong=ape');
        self::assertSame('kingkong=toto&kingkong=ape&foo=bar%20baz', Resolution::sortQuery($uri)->getQuery());
    }

    /**
     * @dataProvider validWithoutQueryValuesProvider
     *
     * @covers ::removePairs
     *
     */
    public function testWithoutQueryValuesProcess(array $input, string $expected): void
    {
        self::assertSame($expected, Resolution::removePairs($this->uri, ...$input)->getQuery());
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
     */
    public function testWithoutQueryParams(string $uri, array $input, string $expected): void
    {
        self::assertSame($expected, Resolution::removeParams(create($uri), ...$input)->getQuery());
    }

    public function removeParamsProvider(): array
    {
        return [
            [
                'uri' => 'http://example.com',
                'input' => ['foo'],
                'expected' => '',
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
}

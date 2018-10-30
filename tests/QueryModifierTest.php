<?php

/**
 * League.Uri (http://uri.thephpleague.com/components).
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.0
 * @link       https://github.com/thephpleague/uri-schemes
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Uri;

use League\Uri\Component\Query;
use League\Uri\Http;
use PHPUnit\Framework\TestCase;
use function League\Uri\append_query;
use function League\Uri\create;
use function League\Uri\merge_query;
use function League\Uri\remove_pairs;
use function League\Uri\remove_params;
use function League\Uri\sort_query;

/**
 * @group query
 */
class QueryModifierTest extends TestCase
{
    /**
     * @var Http
     */
    private $uri;

    protected function setUp()
    {
        $this->uri = Http::createFromString(
            'http://www.example.com/path/to/the/sky.php?kingkong=toto&foo=bar%20baz#doc3'
        );
    }

    /**
     * @covers \League\Uri\merge_query
     *
     * @dataProvider validMergeQueryProvider
     *
     * @param string $query
     * @param string $expected
     */
    public function testMergeQuery(string $query, string $expected)
    {
        self::assertSame($expected, merge_query($this->uri, $query)->getQuery());
    }

    public function validMergeQueryProvider()
    {
        return [
            ['toto', 'kingkong=toto&foo=bar%20baz&toto'],
            ['kingkong=ape', 'kingkong=ape&foo=bar%20baz'],
        ];
    }

    /**
     * @covers \League\Uri\append_query
     *
     * @dataProvider validAppendQueryProvider
     *
     * @param string $query
     * @param string $expected
     */
    public function testAppendQuery(string $query, string $expected)
    {
        self::assertSame($expected, append_query($this->uri, $query)->getQuery());
    }

    public function validAppendQueryProvider()
    {
        return [
            ['toto', 'kingkong=toto&foo=bar%20baz&toto'],
            ['kingkong=ape', 'kingkong=toto&foo=bar%20baz&kingkong=ape'],
        ];
    }

    /**
     * @covers \League\Uri\sort_query
     */
    public function testKsortQuery()
    {
        $uri = Http::createFromString('http://example.com/?kingkong=toto&foo=bar%20baz&kingkong=ape');
        self::assertSame('kingkong=toto&kingkong=ape&foo=bar%20baz', sort_query($uri)->getQuery());
    }

    /**
     * @covers \League\Uri\remove_pairs
     *
     * @dataProvider validWithoutQueryValuesProvider
     *
     * @param array  $input
     * @param string $expected
     */
    public function testWithoutQueryValuesProcess(array $input, $expected)
    {
        self::assertSame($expected, remove_pairs($this->uri, $input)->getQuery());
    }

    public function validWithoutQueryValuesProvider()
    {
        return [
            [['1'], 'kingkong=toto&foo=bar%20baz'],
            [['kingkong'], 'foo=bar%20baz'],
        ];
    }

    /**
     * @covers \League\Uri\remove_params
     *
     * @dataProvider removeParamsProvider
     * @param string $uri
     * @param array  $input
     * @param string $expected
     */
    public function testWithoutQueryParams(string $uri, array $input, string $expected)
    {
        self::assertSame($expected, remove_params(create($uri), $input)->getQuery());
    }

    public function removeParamsProvider()
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

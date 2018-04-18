<?php

namespace LeagueTest\Uri;

use League\Uri;
use League\Uri\Components\Query;
use League\Uri\Schemes\Http;
use PHPUnit\Framework\TestCase;

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
        $this->assertSame($expected, Uri\merge_query($this->uri, $query)->getQuery());
    }

    public function validMergeQueryProvider()
    {
        return [
            ['toto', 'kingkong=toto&foo=bar%20baz&toto'],
            ['kingkong=ape', 'foo=bar%20baz&kingkong=ape'],
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
        $this->assertSame($expected, Uri\append_query($this->uri, $query)->getQuery());
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
        $this->assertSame('kingkong=toto&kingkong=ape&foo=bar%20baz', Uri\sort_query($uri)->getQuery());
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
        $this->assertSame($expected, Uri\remove_pairs($this->uri, $input)->getQuery());
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
        $this->assertSame($expected, Uri\remove_params(Uri\create($uri), $input)->getQuery());
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

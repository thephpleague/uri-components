<?php

namespace LeagueTest\Uri\Components;

use League\Uri;
use League\Uri\Components\Query;
use PHPUnit\Framework\TestCase;

/**
 * @group function
 */
class FunctionsTest extends TestCase
{
    /**
     * @dataProvider parseQueryProvider
     * @param string $query
     * @param string $separator
     * @param array  $expected
     * @param int    $encoding
     */
    public function testParse($query, $separator, $expected, $encoding)
    {
        $this->assertSame($expected, Uri\parse_query($query, $separator, $encoding));
    }

    public function parseQueryProvider()
    {
        return [
            'empty string' => [
                '',
                '&',
                [],
                Query::RFC3986_ENCODING,
            ],
            'identical keys' => [
                'a=1&a=2',
                '&',
                ['a' => ['1', '2']],
                Query::RFC3986_ENCODING,
            ],
            'no value' => [
                'a&b',
                '&',
                ['a' => null, 'b' => null],
                Query::RFC3986_ENCODING,
            ],
            'empty value' => [
                'a=&b=',
                '&',
                ['a' => '', 'b' => ''],
                Query::RFC3986_ENCODING,
            ],
            'php array' => [
                'a[]=1&a[]=2',
                '&',
                ['a[]' => ['1', '2']],
                Query::RFC3986_ENCODING,
            ],
            'preserve dot' => [
                'a.b=3',
                '&',
                ['a.b' => '3'],
                Query::RFC3986_ENCODING,
            ],
            'decode' => [
                'a%20b=c%20d',
                '&',
                ['a b' => 'c d'],
                Query::RFC3986_ENCODING,
            ],
            'no key stripping' => [
                'a=&b',
                '&',
                ['a' => '', 'b' => null],
                Query::RFC3986_ENCODING,
            ],
            'no value stripping' => [
                'a=b=',
                '&',
                ['a' => 'b='],
                Query::RFC3986_ENCODING,
            ],
            'key only' => [
                'a',
                '&',
                ['a' => null],
                Query::RFC3986_ENCODING,
            ],
            'preserve falsey 1' => [
                '0',
                '&',
                ['0' => null],
                Query::RFC3986_ENCODING,
            ],
            'preserve falsey 2' => [
                '0=',
                '&',
                ['0' => ''],
                Query::RFC3986_ENCODING,
            ],
            'preserve falsey 3' => [
                'a=0',
                '&',
                ['a' => '0'],
                Query::RFC3986_ENCODING,
            ],
            'different separator' => [
                'a=0;b=0&c=4',
                ';',
                ['a' => '0', 'b' => '0&c=4'],
                Query::RFC3986_ENCODING,
            ],
            'numeric key only' => [
                '42',
                '&',
                ['42' => null],
                Query::RFC3986_ENCODING,
            ],
            'numeric key' => [
                '42=l33t',
                '&',
                ['42' => 'l33t'],
                Query::RFC3986_ENCODING,
            ],
            'rfc1738' => [
                '42=l3+3t',
                '&',
                ['42' => 'l3 3t'],
                Query::RFC1738_ENCODING,
            ],
        ];
    }

    /**
     * @dataProvider extractQueryProvider
     *
     * @param string $query
     * @param array  $expectedData
     */
    public function testExtractQuery($query, $expectedData)
    {
        $this->assertSame($expectedData, Uri\extract_params($query));
    }

    public function extractQueryProvider()
    {
        return [
            [
                'query' => '&&',
                'expected' => [],
            ],
            [
                'query' => 'arr[1=sid&arr[4][2=fred',
                'expected' => [
                    'arr[1' => 'sid',
                    'arr' => ['4' => 'fred'],
                ],
            ],
            [
                'query' => 'arr1]=sid&arr[4]2]=fred',
                'expected' => [
                    'arr1]' => 'sid',
                    'arr' => ['4' => 'fred'],
                ],
            ],
            [
                'query' => 'arr[one=sid&arr[4][two=fred',
                'expected' => [
                    'arr[one' => 'sid',
                    'arr' => ['4' => 'fred'],
                ],
            ],
            [
                'query' => 'first=%41&second=%a&third=%b',
                'expected' => [
                    'first' => 'A',
                    'second' => '%a',
                    'third' => '%b',
                ],
            ],
            [
                'query' => 'arr.test[1]=sid&arr test[4][two]=fred',
                'expected' => [
                    'arr.test' => ['1' => 'sid'],
                    'arr test' => ['4' => ['two' => 'fred']],
                ],
            ],
            [
                'query' => 'foo&bar=&baz=bar&fo.o',
                'expected' => [
                    'foo' => '',
                    'bar' => '',
                    'baz' => 'bar',
                    'fo.o' => '',
                ],
            ],
            [
                'query' => 'foo[]=bar&foo[]=baz',
                'expected' => [
                    'foo' => ['bar', 'baz'],
                ],
            ],
        ];
    }
}

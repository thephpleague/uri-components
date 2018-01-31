<?php

namespace LeagueTest\Uri;

use ArrayIterator;
use League\Uri;
use League\Uri\Components\Exception;
use League\Uri\Components\Query;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * @group function
 * @group parser
 */
class FunctionsTest extends TestCase
{
    public function testEncodingThrowsExceptionWithQueryParser()
    {
        $this->expectException(Exception::class);
        Uri\parse_query('foo=bar', '&', 42);
    }

    public function testEncodingThrowsExceptionWithQueryBuilder()
    {
        $this->expectException(Exception::class);
        Uri\build_query(['foo' => 'bar'], '&', 42);
    }

    public function testQuerParserConvert()
    {
        $expected = ['a' => ['1', '2', 'false']];
        $pairs = new ArrayIterator(['a[]' => [1, '2', false]]);
        $this->assertSame($expected, (new Uri\QueryParser())->convert($pairs));
    }

    /**
     * @dataProvider invalidPairsProvider
     *
     * @param mixed $pairs
     */
    public function testQueryParserConvertThrowsTypeError($pairs)
    {
        $this->expectException(TypeError::class);
        (new Uri\QueryParser())->convert($pairs);
    }

    public function invalidPairsProvider()
    {
        return [
            'pairs must be iterable' => [date_create()],
            'pairs value must be null or scalar' => ['a[]' => [date_create(), '2']],
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
        $this->assertSame($expectedData, Uri\extract_query($query));
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

    /**
     * @dataProvider parserProvider
     * @param string $query
     * @param string $separator
     * @param array  $expected
     * @param int    $encoding
     */
    public function testParse($query, $separator, $expected, $encoding)
    {
        $this->assertSame($expected, Uri\parse_query($query, $separator, $encoding));
    }

    public function parserProvider()
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
     * @dataProvider buildProvider
     * @param array  $pairs
     * @param string $expected_rfc1738
     * @param string $expected_rfc3986
     * @param string $expected_rfc3987
     * @param string $expected_no_encoding
     */
    public function testBuild(
        $pairs,
        $expected_rfc1738,
        $expected_rfc3986,
        $expected_rfc3987,
        $expected_no_encoding
    ) {
        $this->assertSame($expected_rfc1738, Uri\build_query($pairs, '&', PHP_QUERY_RFC1738));
        $this->assertSame($expected_rfc3986, Uri\build_query($pairs, '&', PHP_QUERY_RFC3986));
        $this->assertSame($expected_rfc3987, Uri\build_query($pairs, '&', Query::RFC3987_ENCODING));
        $this->assertSame($expected_no_encoding, Uri\build_query($pairs, '&', Query::NO_ENCODING));
    }

    public function buildProvider()
    {
        return [
            'empty string' => [
                'pairs' => [],
                'expected_rfc1738' => '',
                'expected_rfc3986' => '',
                'expected_rfc3987' => '',
                'expected_no_encoding' => '',
            ],
            'identical keys' => [
                'pairs' => new ArrayIterator(['a' => ['1', '2']]),
                'expected_rfc1738' => 'a=1&a=2',
                'expected_rfc3986' => 'a=1&a=2',
                'expected_rfc3987' => 'a=1&a=2',
                'expected_no_encoding' => 'a=1&a=2',
            ],
            'no value' => [
                'pairs' => ['a' => null, 'b' => null],
                'expected_rfc1738' => 'a&b',
                'expected_rfc3986' => 'a&b',
                'expected_rfc3987' => 'a&b',
                'expected_no_encoding' => 'a&b',
            ],
            'empty value' => [
                'pairs' => ['a' => '', 'b' => ''],
                'expected_rfc1738' => 'a=&b=',
                'expected_rfc3986' => 'a=&b=',
                'expected_rfc3987' => 'a=&b=',
                'expected_no_encoding' => 'a=&b=',
            ],
            'php array' => [
                'pairs' => ['a[]' => ['1', '2']],
                'expected_rfc1738' => 'a%5B%5D=1&a%5B%5D=2',
                'expected_rfc3986' => 'a%5B%5D=1&a%5B%5D=2',
                'expected_rfc3987' => 'a[]=1&a[]=2',
                'expected_no_encoding' => 'a[]=1&a[]=2',
            ],
            'preserve dot' => [
                'pairs' => ['a.b' => '3'],
                'expected_rfc1738' => 'a.b=3',
                'expected_rfc3986' => 'a.b=3',
                'expected_rfc3987' => 'a.b=3',
                'expected_no_encoding' => 'a.b=3',
            ],
            'no key stripping' => [
                'pairs' => ['a' => '', 'b' => null],
                'expected_rfc1738' => 'a=&b',
                'expected_rfc3986' => 'a=&b',
                'expected_rfc3987' => 'a=&b',
                'expected_no_encoding' => 'a=&b',
            ],
            'no value stripping' => [
                'pairs' => ['a' => 'b='],
                'expected_rfc1738' => 'a=b=',
                'expected_rfc3986' => 'a=b=',
                'expected_rfc3987' => 'a=b=',
                'expected_no_encoding' => 'a=b=',
            ],
            'key only' => [
                'pairs' => ['a' => null],
                'expected_rfc1738' => 'a',
                'expected_rfc3986' => 'a',
                'expected_rfc3987' => 'a',
                'expected_no_encoding' => 'a',
            ],
            'preserve falsey 1' => [
                'pairs' => ['0' => null],
                'expected_rfc1738' => '0',
                'expected_rfc3986' => '0',
                'expected_rfc3987' => '0',
                'expected_no_encoding' => '0',
            ],
            'preserve falsey 2' => [
                'pairs' => ['0' => ''],
                'expected_rfc1738' => '0=',
                'expected_rfc3986' => '0=',
                'expected_rfc3987' => '0=',
                'expected_no_encoding' => '0=',
            ],
            'preserve falsey 3' => [
                'pairs' => ['0' => '0'],
                'expected_rfc1738' => '0=0',
                'expected_rfc3986' => '0=0',
                'expected_rfc3987' => '0=0',
                'expected_no_encoding' => '0=0',
            ],
            'rcf1738' => [
                'pairs' => ['toto' => 'foo+bar'],
                'expected_rfc1738' => 'toto=foo%2Bbar',
                'expected_rfc3986' => 'toto=foo+bar',
                'expected_rfc3987' => 'toto=foo+bar',
                'expected_no_encoding' => 'toto=foo+bar',
            ],
        ];
    }

    public function testBuildQueryThrowsException()
    {
        $this->expectException(Exception::class);
        Uri\build_query(['foo' => ['bar' => new ArrayIterator(['foo', 'bar', 'baz'])]]);
    }
}

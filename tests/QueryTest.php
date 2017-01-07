<?php

namespace LeagueTest\Uri\Components;

use ArrayIterator;
use League\Uri\Components\Exception;
use League\Uri\Components\Query;
use PHPUnit\Framework\TestCase;

/**
 * @group query
 */
class QueryTest extends TestCase
{
    /**
     * @var Query
     */
    protected $query;

    protected function setUp()
    {
        $this->query = new Query('kingkong=toto');
    }

    public function testSetState()
    {
        $generateComponent = eval('return '.var_export($this->query, true).';');
        $this->assertEquals($this->query, $generateComponent);
    }

    public function testDefined()
    {
        $this->assertFalse($this->query->isNull());
        $this->assertTrue($this->query->withContent(null)->isNull());
    }

    public function testDebugInfo()
    {
        $this->assertInternalType('array', $this->query->__debugInfo());
    }

    public function testWithContent()
    {
        $this->assertSame($this->query, $this->query->withContent('kingkong=toto'));
    }

    public function testFailedConstructor()
    {
        $this->expectException(Exception::class);
        new Query('foo#bar');
    }

    public function testIterator()
    {
        $this->assertSame(['kingkong' => 'toto'], iterator_to_array($this->query, true));
    }

    /**
     * @dataProvider queryProvider
     */
    public function testGetUriComponent($input, $expected)
    {
        $query = is_array($input) ? Query::createFromPairs($input) : new Query($input);

        $this->assertSame($expected, $query->getUriComponent());
    }

    public function queryProvider()
    {
        $unreserved = 'a-zA-Z0-9.-_~!$&\'()*+,;=:@';

        return [
            'bug fix issue 84' => ['fào=?%25bar&q=v%61lue', '?f%C3%A0o=?%25bar&q=v%61lue'],
            'string' => ['kingkong=toto', '?kingkong=toto'],
            'null' => [null, ''],
            'empty string' => ['', '?'],
            'empty array' => [[], ''],
            'non empty array' => [['' => null], '?'],
            'contains a reserved word #' => ['foo%23bar', '?foo%23bar'],
            'contains a delimiter ?' => ['?foo%23bar', '??foo%23bar'],
            'key-only' => ['k^ey', '?k%5Eey'],
            'key-value' => ['k^ey=valu`', '?k%5Eey=valu%60'],
            'array-key-only' => ['key[]', '?key%5B%5D'],
            'array-key-value' => ['key[]=valu`', '?key%5B%5D=valu%60'],
            'complex' => ['k^ey&key[]=valu`&f<>=`bar', '?k%5Eey&key%5B%5D=valu%60&f%3C%3E=%60bar'],
            'Percent encode spaces' => ['q=va lue', '?q=va%20lue'],
            'Percent encode multibyte' => ['€', '?%E2%82%AC'],
            "Don't encode something that's already encoded" => ['q=va%20lue', '?q=va%20lue'],
            'Percent encode invalid percent encodings' => ['q=va%2-lue', '?q=va%2-lue'],
            "Don't encode path segments" => ['q=va/lue', '?q=va/lue'],
            "Don't encode unreserved chars or sub-delimiters" => [$unreserved, '?'.$unreserved],
            'Encoded unreserved chars are not decoded' => ['q=v%61lue', '?q=v%61lue'],
        ];
    }

    public function testcreateFromPairsWithTraversable()
    {
        $query = Query::createFromPairs(new ArrayIterator(['john' => 'doe the john']));
        $this->assertCount(1, $query);
    }

    /**
     * @param $input
     * @dataProvider createFromPairsFailedProvider
     */
    public function testcreateFromPairsFailed($input)
    {
        $this->expectException(Exception::class);
        Query::createFromPairs($input);
    }

    public function createFromPairsFailedProvider()
    {
        return [
            'Non traversable object' => [new \stdClass()],
            'String' => ['toto=23'],
            'Non pairs array' => [['toto' => ['foo' => [new \stdClass()]]]],
        ];
    }

    /**
     * @param $input
     * @param $expected
     * @dataProvider validMergeValue
     */
    public function testMerge($input, $expected)
    {
        $query = $this->query->merge($input);
        $this->assertSame($expected, (string) $query);
    }

    public function validMergeValue()
    {
        return [
            'with new data' => [
                'john=doe the john',
                'kingkong=toto&john=doe%20the%20john',
            ],
            'with the same data' => [
                'kingkong=toto',
                'kingkong=toto',
            ],
            'with string' => [
                'foo=bar',
                'kingkong=toto&foo=bar',
            ],
            'with empty string' => [
                '',
                'kingkong=toto',
            ],
        ];
    }

    /**
     * @dataProvider validAppendValue
     */
    public function testAppend($query, $append_data, $expected)
    {
        $query = new Query($query);
        $this->assertSame($expected, (string) $query->append($append_data));
    }

    public function validAppendValue()
    {
        return [
            ['', 'foo=bar&foo=baz', 'foo=bar&foo=baz'],
            ['', 'foo=bar', 'foo=bar'],
            ['', 'foo=', 'foo='],
            ['', 'foo', 'foo'],
            ['foo=bar', 'foo=baz', 'foo=bar&foo=baz'],
            ['foo=bar', 'foo=', 'foo=bar&foo='],
            ['foo=bar', 'foo', 'foo=bar&foo'],
            ['foo=bar', 'foo=baz&foo=yolo', 'foo=bar&foo=baz&foo=yolo'],
            ['foo=bar', '', 'foo=bar'],
        ];
    }


    public function testGetParameter()
    {
        $this->assertSame('toto', $this->query->getPair('kingkong'));
    }

    public function testGetParameterWithDefaultValue()
    {
        $expected = 'toofan';
        $this->assertSame($expected, $this->query->getPair('togo', $expected));
    }

    public function testHas()
    {
        $this->assertTrue($this->query->hasPair('kingkong'));
        $this->assertFalse($this->query->hasPair('togo'));
    }

    public function testCountable()
    {
        $this->assertSame(1, count($this->query));
    }

    public function testKeys()
    {
        $query = Query::createFromPairs([
            'foo' => 'bar',
            'baz' => 'troll',
            'lol' => 3,
            'toto' => 'troll',
            'yolo' => null,
        ]);
        $this->assertCount(0, $query->keys('foo'));
        $this->assertSame(['foo'], $query->keys('bar'));
        $this->assertCount(1, $query->keys('3'));
        $this->assertSame(['lol'], $query->keys('3'));
        $this->assertSame(['baz', 'toto'], $query->keys('troll'));
        $this->assertSame(['yolo'], $query->keys(null));
    }

    public function testStringWithoutContent()
    {
        $query = new Query('foo&bar&baz');

        $this->assertCount(3, $query->keys());
        $this->assertSame(['foo', 'bar', 'baz'], $query->keys());
        $this->assertSame(null, $query->getPair('foo'));
        $this->assertSame(null, $query->getPair('bar'));
        $this->assertSame(null, $query->getPair('baz'));
    }

    public function testGetPairs()
    {
        $expected = ['foo' => null, 'bar' => null, 'baz' => null, 'to.go' => 'toofan'];
        $query = new Query('foo&bar&baz&to.go=toofan');
        $this->assertSame($expected, $query->getPairs());
    }

    /**
     * @dataProvider parsedQueryProvider
     */
    public function testGetParams($query, $expected)
    {
        $this->assertSame($expected, (new Query($query))->getParams());
    }

    /**
     * Test AbstractSegment::without
     *
     * @param $origin
     * @param $without
     * @param $result
     *
     * @dataProvider withoutProvider
     */
    public function testWithout($origin, $without, $result)
    {
        $this->assertSame($result, (string) (new Query($origin))->withoutPairs($without));
    }

    public function withoutProvider()
    {
        return [
            ['foo&bar&baz&to.go=toofan', ['foo', 'to.go'], 'bar&baz'],
            ['foo&bar&baz&to.go=toofan', ['foo', 'unknown'], 'bar&baz&to.go=toofan'],
            ['foo&bar&baz&to.go=toofan', [], 'foo&bar&baz&to.go=toofan'],
        ];
    }

    /**
     * @param $data
     * @param $sort
     * @param $expected
     * @dataProvider ksortProvider
     */
    public function testksort($data, $sort, $expected)
    {
        $this->assertSame($expected, Query::createFromPairs($data)->ksort($sort)->getPairs());
    }

    public function ksortProvider()
    {
        return [
            [
                ['superman' => 'lex luthor', 'batman' => 'joker'],
                SORT_REGULAR,
                [ 'batman' => 'joker', 'superman' => 'lex luthor'],
            ],
            [
                ['superman' => 'lex luthor', 'batman' => 'joker'],
                function ($dataA, $dataB) {
                    return strcasecmp($dataA, $dataB);
                },
                [ 'batman' => 'joker', 'superman' => 'lex luthor'],
            ],
            [
                ['superman' => 'lex luthor', 'superwoman' => 'joker'],
                function ($dataA, $dataB) {
                    return strcasecmp($dataA, $dataB);
                },
                ['superman' => 'lex luthor', 'superwoman' => 'joker'],
            ],
        ];
    }

    /**
     * @dataProvider parserProvider
     */
    public function testParse($query, $separator, $expected, $encoding)
    {
        $this->assertSame($expected, Query::parse($query, $separator, $encoding));
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
     */
    public function testBuild(
        $pairs,
        $expected_rfc1738,
        $expected_rfc3986,
        $expected_rfc3987,
        $expected_iri,
        $expected_no_encoding
    ) {
        $this->assertSame($expected_rfc1738, Query::build($pairs, '&', Query::RFC1738_ENCODING));
        $this->assertSame($expected_rfc3986, Query::build($pairs, '&', Query::RFC3986_ENCODING));
        $this->assertSame($expected_rfc3987, Query::build($pairs, '&', Query::RFC3987_ENCODING));
        $this->assertSame($expected_no_encoding, Query::build($pairs, '&', Query::NO_ENCODING));
        $this->assertSame($expected_iri, Query::createFromPairs($pairs)->getContent(Query::RFC3987_ENCODING));
    }

    public function buildProvider()
    {
        return [
            'empty string' => [
                'pairs' => [],
                'expected_rfc1738' => '',
                'expected_rfc3986' => '',
                'expected_rfc3987' => '',
                'expected_iri' => null,
                'expected_no_encoding' => '',
            ],
            'identical keys' => [
                'pairs' => ['a' => ['1', '2']],
                'expected_rfc1738' => 'a=1&a=2',
                'expected_rfc3986' => 'a=1&a=2',
                'expected_rfc3987' => 'a=1&a=2',
                'expected_iri' => 'a=1&a=2',
                'expected_no_encoding' => 'a=1&a=2',
            ],
            'no value' => [
                'pairs' => ['a' => null, 'b' => null],
                'expected_rfc1738' => 'a&b',
                'expected_rfc3986' => 'a&b',
                'expected_rfc3987' => 'a&b',
                'expected_iri' => 'a&b',
                'expected_no_encoding' => 'a&b',
            ],
            'empty value' => [
                'pairs' => ['a' => '', 'b' => ''],
                'expected_rfc1738' => 'a=&b=',
                'expected_rfc3986' => 'a=&b=',
                'expected_rfc3987' => 'a=&b=',
                'expected_iri' => 'a=&b=',
                'expected_no_encoding' => 'a=&b=',
            ],
            'php array' => [
                'pairs' => ['a[]' => ['1', '2']],
                'expected_rfc1738' => 'a%5B%5D=1&a%5B%5D=2',
                'expected_rfc3986' => 'a%5B%5D=1&a%5B%5D=2',
                'expected_rfc3987' => 'a[]=1&a[]=2',
                'expected_iri' => 'a[]=1&a[]=2',
                'expected_no_encoding' => 'a[]=1&a[]=2',
            ],
            'preserve dot' => [
                'pairs' => ['a.b' => '3'],
                'expected_rfc1738' => 'a.b=3',
                'expected_rfc3986' => 'a.b=3',
                'expected_rfc3987' => 'a.b=3',
                'expected_iri' => 'a.b=3',
                'expected_no_encoding' => 'a.b=3',
            ],
            'no key stripping' => [
                'pairs' => ['a' => '', 'b' => null],
                'expected_rfc1738' => 'a=&b',
                'expected_rfc3986' => 'a=&b',
                'expected_rfc3987' => 'a=&b',
                'expected_iri' => 'a=&b',
                'expected_no_encoding' => 'a=&b',
            ],
            'no value stripping' => [
                'pairs' => ['a' => 'b='],
                'expected_rfc1738' => 'a=b=',
                'expected_rfc3986' => 'a=b=',
                'expected_rfc3987' => 'a=b=',
                'expected_iri' => 'a=b=',
                'expected_no_encoding' => 'a=b=',
            ],
            'key only' => [
                'pairs' => ['a' => null],
                'expected_rfc1738' => 'a',
                'expected_rfc3986' => 'a',
                'expected_rfc3987' => 'a',
                'expected_iri' => 'a',
                'expected_no_encoding' => 'a',
            ],
            'preserve falsey 1' => [
                'pairs' => ['0' => null],
                'expected_rfc1738' => '0',
                'expected_rfc3986' => '0',
                'expected_rfc3987' => '0',
                'expected_iri' => '0',
                'expected_no_encoding' => '0',
            ],
            'preserve falsey 2' => [
                'pairs' => ['0' => ''],
                'expected_rfc1738' => '0=',
                'expected_rfc3986' => '0=',
                'expected_rfc3987' => '0=',
                'expected_iri' => '0=',
                'expected_no_encoding' => '0=',
            ],
            'preserve falsey 3' => [
                'pairs' => ['0' => '0'],
                'expected_rfc1738' => '0=0',
                'expected_rfc3986' => '0=0',
                'expected_rfc3987' => '0=0',
                'expected_iri' => '0=0',
                'expected_no_encoding' => '0=0',
            ],
            'rcf1738' => [
                'pairs' => ['toto' => 'foo+bar'],
                'expected_rfc1738' => 'toto=foo%2Bbar',
                'expected_rfc3986' => 'toto=foo+bar',
                'expected_rfc3987' => 'toto=foo+bar',
                'expected_iri' => 'toto=foo+bar',
                'expected_no_encoding' => 'toto=foo+bar',
            ],
        ];
    }

    public function testBuildWithMalformedUtf8Chars()
    {
        $this->assertSame(
            'badutf8=%A0TM1TMS061114IP1',
            Query::build(['badutf8' => rawurldecode('%A0TM1TMS061114IP1')])
        );
    }

    public function testThrowsExceptionOnInvalidEncodingType()
    {
        $this->expectException(Exception::class);
        Query::build([], '&', -1);
    }

    public function testInvalidEncodingTypeThrowException()
    {
        $this->expectException(Exception::class);
        (new Query('query'))->getContent(-1);
    }

    public function testFailSafeQueryParsing()
    {
        $arr = ['a' => '1', 'b' => 'le heros'];
        $expected = 'a=1&b=le%20heros';

        $this->assertSame($expected, Query::build($arr, '&'));
    }

    public function testParserBuilderPreserveQuery()
    {
        $querystring = 'uri=http://example.com?a=b%26c=d';
        $data = Query::parse($querystring);
        $this->assertSame(['uri' => 'http://example.com?a=b&c=d'], $data);
        $this->assertSame($querystring, Query::build($data));
    }

    /**
     * @dataProvider parsedQueryProvider
     */
    public function testParsedQuery($query, $expectedData)
    {
        $this->assertSame($expectedData, Query::extract($query));
    }

    public function parsedQueryProvider()
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

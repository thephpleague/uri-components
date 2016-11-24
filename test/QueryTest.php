<?php

namespace LeagueTest\Uri\Components;

use ArrayIterator;
use League\Uri\Components\Exception;
use League\Uri\Components\Query;

/**
 * @group query
 */
class QueryTest extends AbstractTestCase
{
    /**
     * @var Query
     */
    protected $query;

    protected function setUp()
    {
        $this->query = new Query('kingkong=toto');
    }

    /**
     * @supportsDebugInfo
     */
    public function testDebugInfo()
    {
        $this->assertInternalType('array', $this->query->__debugInfo());
        ob_start();
        var_dump($this->query);
        $res = ob_get_clean();
        $this->assertContains($this->query->__toString(), $res);
        $this->assertContains('query', $res);
    }

    public function testSetState()
    {
        $generateComponent = eval('return '.var_export($this->query, true).';');
        $this->assertEquals($this->query, $generateComponent);
    }

    public function testDefined()
    {
        $this->assertTrue($this->query->isDefined());
        $this->assertFalse($this->query->withContent(null)->isDefined());
    }

    public function testWithContent()
    {
        $this->assertSame($this->query, $this->query->withContent('kingkong=toto'));
    }

    /**
     * @param $str
     * @dataProvider failedConstructor
     */
    public function testFailedConstructor($str)
    {
        $this->expectException(Exception::class);
        new Query($str);
    }

    public function failedConstructor()
    {
        return [
            'bool' => [true],
            'Std Class' => [(object) 'foo'],
            'float' => [1.2],
            'array' => [['foo']],
            'reserved char' => ['foo#bar'],
        ];
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
            'Percent encode invalid percent encodings' => ['q=va%2-lue', '?q=va%252-lue'],
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
                Query::createFromPairs(['john' => 'doe the john']),
                'kingkong=toto&john=doe%20the%20john',
            ],
            'with the same data' => [
                new Query('kingkong=toto'),
                'kingkong=toto',
            ],
            'without new data' => [
                new Query(''),
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

    public function testGetParameter()
    {
        $this->assertSame('toto', $this->query->getValue('kingkong'));
    }

    public function testGetParameterWithDefaultValue()
    {
        $expected = 'toofan';
        $this->assertSame($expected, $this->query->getValue('togo', $expected));
    }

    public function testhasKey()
    {
        $this->assertTrue($this->query->hasKey('kingkong'));
        $this->assertFalse($this->query->hasKey('togo'));
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
        ]);
        $this->assertCount(0, $query->keys('foo'));
        $this->assertSame(['foo'], $query->keys('bar'));
        $this->assertCount(1, $query->keys('3'));
        $this->assertSame(['lol'], $query->keys('3'));
        $this->assertSame(['baz', 'toto'], $query->keys('troll'));
    }

    public function testStringWithoutContent()
    {
        $query = new Query('foo&bar&baz');

        $this->assertCount(3, $query->keys());
        $this->assertSame(['foo', 'bar', 'baz'], $query->keys());
        $this->assertSame(null, $query->getValue('foo'));
        $this->assertSame(null, $query->getValue('bar'));
        $this->assertSame(null, $query->getValue('baz'));
    }

    public function testgetPairs()
    {
        $expected = ['foo' => null, 'bar' => null, 'baz' => null, 'to.go' => 'toofan'];
        $query = new Query('foo&bar&baz&to.go=toofan');
        $this->assertSame($expected, $query->getPairs());
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
        $this->assertSame($result, (string) (new Query($origin))->without($without));
    }

    public function withoutProvider()
    {
        return [
            ['foo&bar&baz&to.go=toofan', ['foo', 'to.go'], 'bar&baz'],
            ['foo&bar&baz&to.go=toofan', ['foo', 'unknown'], 'bar&baz&to.go=toofan'],
        ];
    }

    /**
     * @dataProvider filterProvider
     *
     * @param array    $params
     * @param callable $callable
     * @param int      $flag
     * @param string   $expected
     */
    public function testFilter($params, $callable, $flag, $expected)
    {
        $this->assertSame($expected, (string) Query::createFromPairs($params)->filter($callable, $flag));
    }

    public function filterProvider()
    {
        $func = function ($value) {
            return stripos($value, '.') !== false;
        };

        $funcBoth = function ($value, $key) {
            return strpos($value, 'o') !== false && strpos($key, 'o') !== false;
        };

        return [
            'empty query' => [[], $func, 0, ''],
            'remove One' => [['toto' => 'foo.bar', 'zozo' => 'stay'], $func, 0, 'toto=foo.bar'],
            'remove All' => [['to.to' => 'foobar', 'zozo' => 'stay'], $func, 0, ''],
            'remove None' => [['toto' => 'foo.bar', 'zozo' => 'st.ay'], $func, 0, 'toto=foo.bar&zozo=st.ay'],
            'remove with filter both' => [['toto' => 'foo', 'foo' => 'bar'], $funcBoth, ARRAY_FILTER_USE_BOTH, 'toto=foo'],
        ];
    }

    /**
     * @param $params
     * @param $callable
     * @param $expected
     * @dataProvider filterByOffsetsProvider
     */
    public function testFilterOffsets($params, $callable, $expected)
    {
        $this->assertSame($expected, (string) Query::createFromPairs($params)->filter($callable, ARRAY_FILTER_USE_KEY));
    }

    public function filterByOffsetsProvider()
    {
        $func = function ($value) {
            return stripos($value, '.') !== false;
        };

        return [
            'empty query' => [[], $func, ''],
            'remove One' => [['toto' => 'foo.bar', 'zozo' => 'stay'], $func, ''],
            'remove All' => [['to.to' => 'foobar', 'zozo' => 'stay'], $func, 'to.to=foobar'],
            'remove None' => [['to.to' => 'foo.bar', 'zo.zo' => 'st.ay'], $func, 'to.to=foo.bar&zo.zo=st.ay'],
        ];
    }

    /**
     * @dataProvider invalidFilter
     * @param $callable
     * @param $flag
     */
    public function testFilterOffsetsFailed($callable, $flag)
    {
        $this->expectException(Exception::class);
        Query::createFromPairs([])->filter($callable, $flag);
    }

    public function invalidFilter()
    {
        $callback = function () {
            return true;
        };

        return [[$callback, 'toto']];
    }

    /**
     * @dataProvider invalidQueryStrings
     * @param $query
     */
    public function testWithQueryRaisesExceptionForInvalidQueryStrings($query)
    {
        $this->expectException(Exception::class);
        new Query($query);
    }

    public function invalidQueryStrings()
    {
        return [
            'true' => [ true ],
            'false' => [ false ],
            'array' => [ [ 'baz=bat' ] ],
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
     *
     * @param string $query
     * @param string $separator
     * @param array  $expected
     */
    public function testParse($query, $separator, $expected)
    {
        $this->assertSame($expected, Query::parse($query, $separator));
    }

    public function parserProvider()
    {
        return [
            'empty string' => ['', '&', []],
            'identical keys' => ['a=1&a=2', '&', ['a' => ['1', '2']]],
            'no value' => ['a&b', '&', ['a' => null, 'b' => null]],
            'empty value' => ['a=&b=', '&', ['a' => '', 'b' => '']],
            'php array' => ['a[]=1&a[]=2', '&', ['a[]' => ['1', '2']]],
            'preserve dot' => ['a.b=3', '&', ['a.b' => '3']],
            'decode' => ['a%20b=c%20d', '&', ['a b' => 'c d']],
            'no key stripping' => ['a=&b', '&', ['a' => '', 'b' => null]],
            'no value stripping' => ['a=b=', '&', ['a' => 'b=']],
            'key only' => ['a', '&', ['a' => null]],
            'preserve falsey 1' => ['0', '&', ['0' => null]],
            'preserve falsey 2' => ['0=', '&', ['0' => '']],
            'preserve falsey 3' => ['a=0', '&', ['a' => '0']],
            'different separator' => ['a=0;b=0&c=4', ';', ['a' => '0', 'b' => '0&c=4']],
            'numeric key only' => ['42', '&', ['42' => null]],
            'numeric key' => ['42=l33t', '&', ['42' => 'l33t']],
        ];
    }

    /**
     * @param $query
     * @param $expected
     * @dataProvider buildProvider
     */
    public function testBuild($query, $expected)
    {
        $this->assertSame($expected, Query::build($query, '&', false));
    }

    public function buildProvider()
    {
        return [
            'empty string' => [[], ''],
            'identical keys' => [['a' => ['1', '2']], 'a=1&a=2'],
            'no value' => [['a' => null, 'b' => null], 'a&b'],
            'empty value' => [['a' => '', 'b' => ''], 'a=&b='],
            'php array' => [['a[]' => ['1', '2']], 'a%5B%5D=1&a%5B%5D=2'],
            'preserve dot' => [['a.b' => '3'], 'a.b=3'],
            'no key stripping' => [['a' => '', 'b' => null], 'a=&b'],
            'no value stripping' => [['a' => 'b='], 'a=b='],
            'key only' => [['a' => null], 'a'],
            'preserve falsey 1' => [['0' => null], '0'],
            'preserve falsey 2' => [['0' => ''], '0='],
            'preserve falsey 3' => [['a' => '0'], 'a=0'],
        ];
    }

    public function testFailSafeQueryParsing()
    {
        $arr = ['a' => '1', 'b' => 'le heros'];
        $expected = 'a=1&b=le%20heros';

        $this->assertSame($expected, Query::build($arr, '&', 'yolo'));
    }

    public function testParserBuilderPreserveQuery()
    {
        $querystring = 'uri=http://example.com?a=b%26c=d';
        $data = Query::parse($querystring);
        $this->assertSame([
            'uri' => 'http://example.com?a=b&c=d',
        ], $data);
        $this->assertSame($querystring, Query::build($data));
    }

    /**
     * @dataProvider parsedQueryProvider
     */
    public function testParsedQuery($query, $name, $expectedData, $expectedValue)
    {
        $component = new Query($query);
        $this->assertSame($expectedData, $component->getParsed());
        $this->assertSame($expectedValue, $component->getParsedValue($name));
    }

    public function parsedQueryProvider()
    {
        return [
            [
                'query' => '&&',
                'name' => 'toto',
                'expected' => [],
                'value' => null,
            ],
            [
                'query' => 'arr[1=sid&arr[4][2=fred',
                'name' => 'arr',
                'expected' => [
                    'arr[1' => 'sid',
                    'arr' => ['4' => 'fred'],
                ],
                'value' => ['4' => 'fred'],
            ],
            [
                'query' => 'arr1]=sid&arr[4]2]=fred',
                'name' => 'arr',
                'expected' => [
                    'arr1]' => 'sid',
                    'arr' => ['4' => 'fred'],
                ],
                'value' => ['4' => 'fred'],
            ],
            [
                'query' => 'arr[one=sid&arr[4][two=fred',
                'name' => 'arr',
                'expected' => [
                    'arr[one' => 'sid',
                    'arr' => ['4' => 'fred'],
                ],
                'value' => ['4' => 'fred'],
            ],
            [
                'query' => 'first=%41&second=%a&third=%b',
                'name' => 'first',
                'expected' => [
                    'first' => 'A',
                    'second' => '%a',
                    'third' => '%b',
                ],
                'value' => 'A',
            ],
            [
                'query' => 'arr.test[1]=sid&arr test[4][two]=fred',
                'name' => 'arr.test',
                'expected' => [
                    'arr.test' => ['1' => 'sid'],
                    'arr test' => ['4' => ['two' => 'fred']],
                ],
                'value' => ['1' => 'sid'],
            ],
            [
                'query' => 'foo&bar=&baz=bar&fo.o',
                'name' => 'bar',
                'expected' => [
                    'foo' => '',
                    'bar' => '',
                    'baz' => 'bar',
                    'fo.o' => '',
                ],
                'value' => '',
            ],
        ];
    }
}

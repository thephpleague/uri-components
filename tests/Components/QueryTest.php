<?php

namespace LeagueTest\Uri\Components;

use ArrayIterator;
use League\Uri\Components\Query;
use League\Uri\Exception;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * @group query
 * @coversDefaultClass \League\Uri\Components\Query
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

    /**
     * @covers ::__set_state
     */
    public function testSetState()
    {
        $generateComponent = eval('return '.var_export($this->query, true).';');
        $this->assertEquals($this->query, $generateComponent);
    }

    /**
     * @covers ::filterSeparator
     * @dataProvider invalidSeparatorProvider
     * @param mixed $separator
     */
    public function testInvalidSeparator($separator)
    {
        $this->expectException(Exception::class);
        new Query('foo=bar', $separator);
    }

    public function invalidSeparatorProvider()
    {
        return [
            'separator can not be `=`' => ['='],
        ];
    }

    /**
     * @covers ::getSeparator
     * @covers ::withSeparator
     */
    public function testSeparator()
    {
        $query = new Query('foo=bar&kingkong=toto');
        $new_query = $query->withSeparator('|');
        $this->assertSame('&', $query->getSeparator());
        $this->assertSame('|', $new_query->getSeparator());
        $this->assertSame('foo=bar|kingkong=toto', $new_query->getContent());
    }

    /**
     * @covers ::__debugInfo
     */
    public function testDebugInfo()
    {
        $this->assertInternalType('array', $this->query->__debugInfo());
    }

    /**
     * @covers ::withContent
     */
    public function testWithContent()
    {
        $this->assertSame($this->query, $this->query->withContent('kingkong=toto'));
        $this->assertNotSame($this->query, $this->query->withContent('kingkong=tata'));
    }

    /**
     * @covers ::getIterator
     * @covers ::keys
     * @covers ::pairs
     */
    public function testIterator()
    {
        $query = new Query('a=1&b=2&c=3&a=4');
        $keys = [];
        $values = [];
        $keysp = [];
        $valuesp = [];
        foreach ($query as $pair) {
            $keys[] = $pair[0];
            $values[] = $pair[1];
        }
        $this->assertSame(['a', 'b', 'c', 'a'], $keys);
        $this->assertSame(['1', '2', '3', '4'], $values);
        $this->assertCount(4, $query->pairs());

        foreach ($query->pairs() as $key => $value) {
            $keysp[] = $key;
            $valuesp[] = $value;
        }

        $this->assertSame($keys, $keysp);
        $this->assertSame($values, $valuesp);
    }

    /**
     * @covers ::getUriComponent
     * @covers ::createFromPairs
     * @dataProvider queryProvider
     * @param string|array $input
     * @param string       $expected
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
            'query object' => [new Query('kingkong=toto'), '?kingkong=toto'],
            'empty string' => ['', '?'],
            'empty array' => [[], ''],
            'non empty array' => [[['', null]], '?'],
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

    /**
     * @covers ::createFromPairs
     * @covers ::filterPair
     */
    public function testCreateFromPairsWithTraversable()
    {
        $query = Query::createFromPairs(new ArrayIterator([['john', 'doe the john']]));
        $this->assertCount(1, $query);
    }

    /**
     * @covers ::createFromPairs
     */
    public function testcreateFromPairsWithQueryObject()
    {
        $query = new Query('a=1&b=2');
        $this->assertEquals($query, Query::createFromPairs($query));
    }

    /**
     * @covers ::createFromPairs
     * @covers ::filterPair
     * @covers \League\Uri\Exception
     *
     * @param mixed $input
     * @dataProvider createFromPairsFailedProvider
     */
    public function testCreateFromPairsFailed($input)
    {
        $this->expectException(TypeError::class);
        Query::createFromPairs($input);
    }

    public function createFromPairsFailedProvider()
    {
        return [
            'Non traversable object' => [(object) []],
            'String' => ['toto=23'],
        ];
    }

    public function testCreateFromPairsFailedWithBadIterable()
    {
        $this->expectException(Exception::class);
        Query::createFromPairs([['toto' => ['foo' => [(object) []]]]]);
    }

    /**
     * @covers ::__construct
     * @covers ::withoutEmptyPairs
     */
    public function testNormalization()
    {
        $this->assertSame('foo=bar', (new Query('foo=bar&&&=&&&&&&'))->withoutEmptyPairs()->getContent());
        $this->assertNull((new Query('&=bar&='))->withoutEmptyPairs()->getContent());
        $this->assertNull((new Query('&&&&&&&&&&&'))->withoutEmptyPairs()->getContent());
        $this->assertSame($this->query, $this->query->withoutEmptyPairs());
    }

    /**
     * @covers ::merge
     *
     * @dataProvider mergeDataProvider
     *
     * @param string $base_query
     * @param string $query
     * @param string $expected
     */
    public function testMerge(string $base_query, string $query, string $expected)
    {
        $base = new Query($base_query);
        $this->assertSame($expected, $base->merge($query)->getContent());
    }

    public function mergeDataProvider()
    {
        return [
            'with new data' => [
                'kingkong=toto',
                'john=doe the john',
                'kingkong=toto&john=doe%20the%20john',
            ],
            'with the same data' => [
                'kingkong=toto',
                'kingkong=toto',
                'kingkong=toto',
            ],
            'with empty string' => [
                'kingkong=toto',
                '',
                'kingkong=toto',
            ],
            'no separator' => [
                'foo=bar',
                'bar=baz',
                'foo=bar&bar=baz',
            ],
            'base query ends with separator' => [
                'foo=bar&',
                'bar=baz',
                'foo=bar&bar=baz',
            ],
            'base query starts with separator' => [
                '&foo=bar',
                'bar=baz',
                'foo=bar&bar=baz',
            ],
            'query ends with separator' => [
                'foo=bar',
                'bar=baz&',
                'foo=bar&bar=baz',
            ],
            'query starts with separator' => [
                'foo=bar',
                '&bar=baz',
                'foo=bar&bar=baz',
            ],
            'separator on query starts and base query ends' => [
                'foo=bar&',
                '&bar=baz',
                'foo=bar&bar=baz',
            ],
            'separator on query ends and base query starts' => [
                '&foo=bar',
                'bar=baz&',
                'foo=bar&bar=baz',
            ],
            'separator on each end' => [
                '&foo=bar&',
                '&bar=baz&',
                'foo=bar&bar=baz',
            ],
            'pair without empty key (1)' => [
                '=toto&foo=bar',
                'bar=baz',
                '=toto&foo=bar&bar=baz',
            ],
            'pair without empty key (2)' => [
                '=toto&foo=bar',
                '&bar=baz&',
                'foo=bar&bar=baz',
            ],
        ];
    }

    /**
     * @covers ::append
     *
     * @dataProvider validAppendValue
     * @param null|string $query
     * @param null|string $append_data
     * @param null|string $expected
     */
    public function testAppend($query, $append_data, $expected)
    {
        $base = new Query($query);
        $this->assertSame($expected, $base->append($append_data)->getContent());
    }

    public function validAppendValue()
    {
        return [
            ['', 'foo=bar&foo=baz', 'foo=bar&foo=baz'],
            [null, null, null],
            [null, 'foo=bar&foo=baz', 'foo=bar&foo=baz'],
            ['foo=bar&foo=baz', null, 'foo=bar&foo=baz'],
            ['', 'foo=bar', 'foo=bar'],
            ['', 'foo=', 'foo='],
            ['', 'foo', 'foo'],
            ['foo=bar', 'foo=baz', 'foo=bar&foo=baz'],
            ['foo=bar', 'foo=', 'foo=bar&foo='],
            ['foo=bar', 'foo', 'foo=bar&foo'],
            ['foo=bar', 'foo=baz&foo=yolo', 'foo=bar&foo=baz&foo=yolo'],
            ['foo=bar', '', 'foo=bar'],
            ['foo=bar', 'foo=baz', 'foo=bar&foo=baz'],
            ['foo=bar', '&foo=baz', 'foo=bar&foo=baz'],
            ['&foo=bar', 'foo=baz', 'foo=bar&foo=baz'],
            ['foo=bar&', 'foo=baz&', 'foo=bar&foo=baz'],
            ['&foo=bar', '&foo=baz', 'foo=bar&foo=baz'],
            ['foo=bar&', '&foo=baz', 'foo=bar&foo=baz'],
            ['&foo=bar&', '&foo=baz&', 'foo=bar&foo=baz'],
            ['=toto&foo=bar', 'foo=bar', '=toto&foo=bar&foo=bar'],
        ];
    }

    /**
     * @covers ::get
     * @covers ::getAll
     */
    public function testGetParameter()
    {
        $query = new Query('kingkong=toto&kingkong=barbaz&&=&=b');
        $this->assertNull($query->get('togo'));
        $this->assertSame([], $query->getAll('togo'));
        $this->assertSame('toto', $query->get('kingkong'));
        $this->assertNull($query->get(''));
        $this->assertSame(['toto', 'barbaz'], $query->getAll('kingkong'));
        $this->assertSame([null, '', 'b'], $query->getAll(''));
    }

    /**
     * @covers ::has
     */
    public function testHas()
    {
        $this->assertTrue($this->query->has('kingkong'));
        $this->assertFalse($this->query->has('togo'));
    }

    /**
     * @covers ::count
     */
    public function testCountable()
    {
        $query = new Query('kingkong=toto&kingkong=barbaz');
        $this->assertCount(2, $query);
    }

    /**
     * @covers ::keys
     */
    public function testKeys()
    {
        $query = Query::createFromPairs([
            ['foo', 'bar'],
            ['baz', 'troll'],
            ['lol', 3],
            ['toto', 'troll'],
            ['yolo', null],
        ]);
        $this->assertCount(5, $query->keys());
        $this->assertCount(2, $query->keys('troll'));
        $this->assertSame(['baz', 'toto'], $query->keys('troll'));
        $this->assertCount(1, $query->keys(null));
        $this->assertSame(['yolo'], $query->keys(null));
    }

    /**
     * @covers ::keys
     */
    public function testStringWithoutContent()
    {
        $query = new Query('foo&bar&baz');

        $this->assertCount(3, $query->keys());
        $this->assertNull($query->get('foo'));
        $this->assertNull($query->get('bar'));
        $this->assertNull($query->get('baz'));
    }

    /**
     * @covers ::withoutPairs
     *
     * @param string $origin
     * @param array  $without
     * @param string $result
     *
     * @dataProvider withoutPairsProvider
     */
    public function testWithoutPairs($origin, $without, $result)
    {
        $this->assertSame($result, (string) (new Query($origin))->withoutPairs(...$without));
    }

    public function withoutPairsProvider()
    {
        return [
            ['foo&bar&baz&to.go=toofan', ['foo', 'to.go'], 'bar&baz'],
            ['foo&bar&baz&to.go=toofan', ['foo', 'unknown'], 'bar&baz&to.go=toofan'],
            ['foo&bar&baz&to.go=toofan', [], 'foo&bar&baz&to.go=toofan'],
            ['a=b&c=d', ['a'], 'c=d'],
            ['a=a&b=b&a=a&c=c', ['a'], 'b=b&c=c'],
            ['a=a&=&b=b&c=c', [''], 'a=a&b=b&c=c'],
            ['a=a&&b=b&c=c', [''], 'a=a&b=b&c=c'],
        ];
    }

    /**
     * @covers ::withoutPairs
     */
    public function testWithoutPairsGetterMethod()
    {
        $query = (new Query())->appendTo('first', 1);
        $this->assertTrue($query->has('first'));
        $this->assertSame(1, $query->get('first'));
        $query = $query->withoutPairs('first');
        $this->assertFalse($query->has('first'));
        $query = $query
            ->appendTo('first', 1)
            ->appendTo('first', 10)
            ->withoutPairs('first')
        ;
        $this->assertFalse($query->has('first'));
    }

    /**
     * @covers ::withoutParams
     *
     * @param array  $origin
     * @param array  $without
     * @param string $expected
     *
     * @dataProvider withoutParamsProvider
     */
    public function testWithoutParams(array $origin, array $without, string $expected)
    {
        $this->assertSame($expected, (string) Query::createFromParams($origin)->withoutParams(...$without));
    }

    public function withoutParamsProvider()
    {
        $data = [
            'filter' => [
                'foo' => [
                    'bar',
                    'baz',
                ],
                'bar' => [
                    'bar' => 'foo',
                    'foo' => 'bar',
                ],
            ],
        ];

        return [
            'simple removal' => [
                'origin' => ['foo' => 'bar', 'bar' => 'baz'],
                'without' => ['bar'],
                'expected' => 'foo=bar',
            ],
            'complext removal' => [
                'origin' => [
                    'arr[one' => 'sid',
                    'arr' => ['4' => 'fred'],
                ],
                'without' => ['arr'],
                'expected' => 'arr%5Bone=sid',
            ],
            'nothing to remove' => [
                'origin' => $data,
                'without' => ['filter[dummy]'],
                'expected' => 'filter%5Bfoo%5D%5B0%5D=bar&filter%5Bfoo%5D%5B1%5D=baz&filter%5Bbar%5D%5Bbar%5D=foo&filter%5Bbar%5D%5Bfoo%5D=bar',
            ],
            'remove 2nd level' => [
                'origin' => $data,
                'without' => ['filter[bar]'],
                'expected' => 'filter%5Bfoo%5D%5B0%5D=bar&filter%5Bfoo%5D%5B1%5D=baz',
            ],
            'remove nth level' => [
                'origin' => $data,
                'without' => ['filter[foo][0]', 'filter[bar][bar]'],
                'expected' => 'filter%5Bfoo%5D%5B1%5D=baz&filter%5Bbar%5D%5Bfoo%5D=bar',
            ],
        ];
    }

    /**
     * @covers ::withoutParams
     * @covers ::createFromParams
     * @covers ::toParams
     */
    public function testWithoutParamsDoesNotChangeParamsKey()
    {
        $data = [
            'foo' => [
                'bar',
                'baz',
            ],
        ];

        $query = Query::createFromParams($data);
        $this->assertSame('foo%5B0%5D=bar&foo%5B1%5D=baz', $query->getContent());
        $new_query = $query->withoutParams('foo[0]');
        $this->assertSame('foo%5B1%5D=baz', $new_query->getContent());
        $this->assertSame(['foo' => [1 => 'baz']], $new_query->toParams());
    }

    /**
     * @covers ::createFromParams
     * @covers ::toParams
     */
    public function testCreateFromParamsWithTraversable()
    {
        $data = [
            'foo' => [
                'bar',
                'baz',
            ],
        ];
        $query = Query::createFromParams(new ArrayIterator($data));
        $this->assertSame($data, $query->toParams());
    }

    public function testCreateFromParamsWithQueryObject()
    {
        $query = new Query('a=1&b=2');
        $this->assertEquals($query, Query::createFromParams($query));
    }

    /**
     * @covers ::createFromParams
     */
    public function testCreateFromParamsThrowsException()
    {
        $this->expectException(TypeError::class);
        Query::createFromParams('foo=bar');
    }

    /**
     * @covers ::withoutNumericIndices
     */
    public function testWithoutNumericIndices()
    {
        $data = [
            'filter' => [
                'foo' => [
                    'bar',
                    'baz',
                ],
                'bar' => [
                    'bar' => 'foo',
                    'foo' => 'bar',
                ],
            ],
        ];

        $with_indices = 'filter%5Bfoo%5D%5B0%5D=bar&filter%5Bfoo%5D%5B1%5D=baz&filter%5Bbar%5D%5Bbar%5D=foo&filter%5Bbar%5D%5Bfoo%5D=bar';

        $without_indices = 'filter%5Bfoo%5D%5B%5D=bar&filter%5Bfoo%5D%5B%5D=baz&filter%5Bbar%5D%5Bbar%5D=foo&filter%5Bbar%5D%5Bfoo%5D=bar';

        $query = Query::createFromParams($data);
        $this->assertSame($with_indices, $query->getContent());
        $this->assertSame($data, $query->toParams());

        $new_query = $query->withoutNumericIndices();
        $this->assertSame($without_indices, $new_query->getContent());
        $this->assertSame($data, $new_query->toParams());
    }

    /**
     * @covers ::withoutNumericIndices
     */
    public function testWithoutNumericIndicesRetursSameInstance()
    {
        $this->assertSame($this->query->withoutNumericIndices(), $this->query);
    }

    /**
     * @covers ::withoutNumericIndices
     */
    public function testWithoutNumericIndicesReturnsAnother()
    {
        $query = new Query('foo[3]');
        $this->assertSame('foo[]', $query->withoutNumericIndices()->getContent(Query::NO_ENCODING));
    }

    /**
     * @covers ::withoutNumericIndices
     */
    public function testWithoutNumericIndicesDoesNotAffectPairValue()
    {
        $query = Query::createFromParams(['foo' => 'bar[3]']);
        $this->assertSame($query, $query->withoutNumericIndices());
    }

    /**
     * @covers ::createFromParams
     */
    public function testCreateFromParamsOnEmptyParams()
    {
        $query = Query::createFromParams([]);
        $this->assertSame($query, $query->withoutNumericIndices());
    }

    /**
     * @covers ::getContent
     */
    public function testGetContentOnEmptyContent()
    {
        $this->assertNull(Query::createFromParams([])->getContent());
    }

    /**
     * @covers ::getContent
     */
    public function testGetContentOnHavingContent()
    {
        $this->assertSame('foo=bar', Query::createFromParams(['foo' => 'bar'])->getContent());
    }

    /**
     * @covers ::__toString
     */
    public function testGetContentOnToString()
    {
        $this->assertSame('foo=bar', (string) Query::createFromParams(['foo' => 'bar']));
    }

    /**
     * @covers ::withSeparator
     */
    public function testWithSeperatorOnHavingSeparator()
    {
        $query = Query::createFromParams(['foo' => '/bar']);
        $this->assertSame($query, $query->withSeparator('&'));
    }

    /**
     * @covers ::withoutNumericIndices
     */
    public function testWithoutNumericIndicesOnEmptyContent()
    {
        $query = Query::createFromParams([]);
        $this->assertSame($query, $query->withoutNumericIndices());
    }

    /**
     * @covers ::ksort
     *
     * @param array        $data
     * @param int|callable $sort
     * @param string       $expected
     *
     * @dataProvider ksortProvider
     */
    public function testksort($data, $sort, $expected)
    {
        $this->assertSame($expected, (string) Query::createFromPairs($data)->ksort($sort));
    }

    public function ksortProvider()
    {
        return [
            [
                [['superman', 'lex luthor'], ['batman', 'joker']],
                SORT_REGULAR,
                'batman=joker&superman=lex%20luthor',
            ],
            [
                [['superman', 'lex luthor'], ['batman', 'joker']],
                function ($dataA, $dataB) {
                    return strcasecmp($dataA, $dataB);
                },
                'batman=joker&superman=lex%20luthor',
            ],
            [
                [['superman', 'lex luthor'], ['superwoman', 'joker']],
                function ($dataA, $dataB) {
                    return strcasecmp($dataA, $dataB);
                },
                'superman=lex%20luthor&superwoman=joker',
            ],
        ];
    }

    /**
     * @covers ::getContent
     */
    public function testInvalidEncodingTypeThrowException()
    {
        $this->expectException(Exception::class);
        (new Query('query'))->getContent(-1);
    }

    /**
     * @covers ::withPair
     * @covers ::filterPair
     *
     * @dataProvider provideWithPairData
     *
     * @param null|string $query
     * @param string      $key
     * @param mixed       $value
     * @param array       $expected
     */
    public function testWithPair($query, $key, $value, $expected)
    {
        $query = new Query($query);
        $this->assertSame($expected, $query->withPair($key, $value)->getAll($key));
    }

    public function provideWithPairData()
    {
        return [
            [
                null,
                'foo',
                'bar',
                ['bar'],
            ],
            [
                'foo=bar',
                'foo',
                'bar',
                ['bar'],
            ],
            [
                'foo=bar',
                'foo',
                null,
                [null],
            ],
            [
                'foo=bar',
                'foo',
                false,
                ['false'],
            ],
        ];
    }

    /**
     * @covers ::withPair
     * @covers ::filterPair
     */
    public function testWithPairBasic()
    {
        $this->assertSame('c=d&a=B', (string) (new Query('a=b&c=d'))->withPair('a', 'B'));
        $this->assertSame('c=d&a=B', (string) (new Query('a=b&c=d&a=e'))->withPair('a', 'B'));
        $this->assertSame('a=b&c=d&e=f', (string) (new Query('a=b&c=d'))->withPair('e', 'f'));
    }

    /**
     * @covers ::withPair
     * @covers ::get
     */
    public function testWithPairGetterMethods()
    {
        $query = new Query('a=1&a=2&a=3');
        $this->assertSame('1', $query->get('a'));

        $query = $query->withPair('first', 4);
        $this->assertSame('1', $query->get('a'));

        $query = $query->withPair('a', 4);
        $this->assertSame('4', $query->get('a'));
    }

    /**
     * @covers ::withPair
     * @covers ::filterPair
     */
    public function testWithPairThrowsException()
    {
        $this->expectException(Exception::class);
        (new Query(null))->withPair('foo', (object) ['data']);
    }

    /**
     * @covers ::withoutDuplicates
     *
     * @dataProvider provideWithoutDuplicatesData
     *
     * @param null|string $query
     * @param null|string $expected
     */
    public function testWithoutDuplicates($query, $expected)
    {
        $query = new Query($query);
        $this->assertSame($expected, $query->withoutDuplicates()->getContent());
    }

    public function provideWithoutDuplicatesData()
    {
        return [
            [null, null],
            ['foo=bar&foo=bar', 'foo=bar'],
        ];
    }

    /**
     * @covers ::appendTo
     */
    public function testAppendToSameName()
    {
        $query = new Query(null);
        $this->assertSame('a=b', (string) $query->appendTo('a', 'b'));
        $this->assertSame('a=b&a=b', (string) $query->appendTo('a', 'b')->appendTo('a', 'b'));
        $this->assertSame('a=b&a=b&a=c', (string) $query->appendTo('a', 'b')->appendTo('a', 'b')->appendTo('a', new class() {
            public function __toString()
            {
                return 'c';
            }
        }));
    }

    /**
     * @covers ::appendTo
     */
    public function testAppendToWithEmptyString()
    {
        $query = new Query(null);
        $this->assertSame('', (string) $query->appendTo('', null));
        $this->assertSame('=', (string) $query->appendTo('', ''));
        $this->assertSame('a', (string) $query->appendTo('a', null));
        $this->assertSame('a=', (string) $query->appendTo('a', ''));
        $this->assertSame(
            'a&a=&&=',
            (string) $query
            ->appendTo('a', null)
            ->appendTo('a', '')
            ->appendTo('', null)
            ->appendTo('', '')
        );
    }


    /**
     * @covers ::appendTo
     * @covers ::get
     * @covers ::getAll
     */
    public function testAppendToWithGetter()
    {
        $query = (new Query(null))
            ->appendTo('first', 1)
            ->appendTo('second', 2)
            ->appendTo('third', '')
            ->appendTo('first', 10)
        ;
        $this->assertSame('first=1&second=2&third=&first=10', (string) $query);
        $this->assertTrue($query->has('first'));
        $this->assertSame(1, $query->get('first'));
        $this->assertSame(2, $query->get('second'));
        $this->assertSame('', $query->get('third'));

        $newQuery = $query->appendTo('first', 10);
        $this->assertSame('first=1&second=2&third=&first=10&first=10', (string) $newQuery);
        $this->assertSame(1, $newQuery->get('first'));
    }

    /**
     * @covers ::appendTo
     */
    public function testAppendToThrowsException()
    {
        $this->expectException(TypeError::class);
        (new Query())->appendTo('foo', ['bar']);
    }
}

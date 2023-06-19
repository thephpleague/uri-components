<?php

/**
 * League.Uri (https://uri.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Uri\Components;

use ArrayIterator;
use DateInterval;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Http;
use League\Uri\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use function is_array;
use function json_encode;

/**
 * @group query
 * @coversDefaultClass \League\Uri\Components\Query
 */
final class QueryTest extends TestCase
{
    protected Query $query;

    protected function setUp(): void
    {
        $this->query = Query::fromRFC3986('kingkong=toto');
    }

    public function testSeparator(): void
    {
        $query = Query::fromRFC3986('foo=bar&kingkong=toto');
        $new_query = $query->withSeparator('|');
        self::assertSame('&', $query->getSeparator());
        self::assertSame('|', $new_query->getSeparator());
        self::assertSame('foo=bar|kingkong=toto', $new_query->value());

        $this->expectException(SyntaxError::class);
        $new_query->withSeparator('');
    }

    public function testIterator(): void
    {
        $query = Query::fromRFC3986('a=1&b=2&c=3&a=4');

        $keys = [];
        $values = [];
        foreach ($query as $pair) {
            $keys[] = $pair[0];
            $values[] = $pair[1];
        }
        self::assertSame(['a', 'b', 'c', 'a'], $keys);
        self::assertSame(['1', '2', '3', '4'], $values);

        $keysp = [];
        $valuesp = [];
        foreach ($query->pairs() as $key => $value) {
            $keysp[] = $key;
            $valuesp[] = $value;
        }

        self::assertSame(['a', 'b', 'c', 'a'], $keysp);
        self::assertSame(['1', '2', '3', '4'], $valuesp);
    }

    public function testJsonEncode(): void
    {
        $query = Query::fromRFC3986('a=1&b=2&c=3&a=4&a=3%20d');
        self::assertSame('"a=1&b=2&c=3&a=4&a=3+d"', json_encode($query));
    }

    public function testGetUriComponent(): void
    {
        self::assertSame('', Query::new()->getUriComponent());
        self::assertSame('?', Query::fromRFC3986('')->getUriComponent());
        self::assertSame('?foo=bar', Query::fromRFC3986('foo=bar')->getUriComponent());
    }

    /**
     * @dataProvider queryProvider
     */
    public function testStringRepresentationComponent(string|array|Query $input, string $expected): void
    {
        $query = is_array($input) ? Query::fromPairs($input) : Query::fromRFC3986($input);

        self::assertSame($expected, (string) $query);
    }

    public static function queryProvider(): array
    {
        $unreserved = 'a-zA-Z0-9.-_~!$&\'()*+,;=:@';

        return [
            'bug fix issue 84' => ['fào=?%25bar&q=v%61lue', 'f%C3%A0o=?%25bar&q=value'],
            'string' => ['kingkong=toto', 'kingkong=toto'],
            'query object' => [Query::fromRFC3986('kingkong=toto'), 'kingkong=toto'],
            'empty string' => ['', ''],
            'empty array' => [[], ''],
            'non empty array' => [[['', null]], ''],
            'contains a reserved word #' => ['foo%23bar', 'foo%23bar'],
            'contains a delimiter ?' => ['?foo%23bar', '?foo%23bar'],
            'key-only' => ['k^ey', 'k%5Eey'],
            'key-value' => ['k^ey=valu`', 'k%5Eey=valu%60'],
            'array-key-only' => ['key[]', 'key%5B%5D'],
            'array-key-value' => ['key[]=valu`', 'key%5B%5D=valu%60'],
            'complex' => ['k^ey&key[]=valu`&f<>=`bar', 'k%5Eey&key%5B%5D=valu%60&f%3C%3E=%60bar'],
            'Percent encode spaces' => ['q=va lue', 'q=va%20lue'],
            'Percent encode multibyte' => ['€', '%E2%82%AC'],
            "Don't encode something that's already encoded" => ['q=va%20lue', 'q=va%20lue'],
            'Percent encode invalid percent encodings' => ['q=va%2-lue', 'q=va%2-lue'],
            "Don't encode path segments" => ['q=va/lue', 'q=va/lue'],
            "Don't encode unreserved chars or sub-delimiters" => [$unreserved, $unreserved],
            'Encoded unreserved chars are not decoded' => ['q=v%61lue', 'q=value'],
        ];
    }

    public function testCreateFromPairsWithIterable(): void
    {
        /** @var iterable<int, array{0:string, 1:string|null}> $iterable */
        $iterable = (function (): iterable {
            $data = [['john', 'doe the john'], ['john', null]];

            foreach ($data as $offset => $value) {
                yield $offset => $value;
            }
        })();

        $query = Query::fromPairs($iterable);

        self::assertCount(2, $query);
    }

    public function testcreateFromPairsWithQueryObject(): void
    {
        $query = Query::fromRFC3986('a=1&b=2');
        self::assertEquals($query, Query::fromPairs($query));
    }

    public function testCreateFromPairsFailedWithBadIterable(): void
    {
        $this->expectException(SyntaxError::class);
        Query::fromPairs([['toto' => ['foo' => [(object) []]]]]);
    }

    public function testNormalization(): void
    {
        self::assertSame('foo=bar', (Query::fromRFC3986('foo=bar&&&=&&&&&&'))->withoutEmptyPairs()->toRFC3986());
        self::assertNull((Query::fromRFC3986('&=bar&='))->withoutEmptyPairs()->toRFC1738());
        self::assertNull((Query::fromRFC3986('&&&&&&&&&&&'))->withoutEmptyPairs()->toRFC1738());
        self::assertSame($this->query, $this->query->withoutEmptyPairs());
    }

    /**
     * @dataProvider validAppendValue
     */
    public function testAppend(?string $query, Stringable|string|int|bool|null $append_data, ?string $expected): void
    {
        $base = Query::fromRFC3986($query);

        self::assertSame($expected, $base->append($append_data)->toRFC3986());
    }

    public static function validAppendValue(): array
    {
        return [
            ['', 'foo=bar&foo=baz', 'foo=bar&foo=baz'],
            [null, null, null],
            [null, 'foo=bar&foo=baz', 'foo=bar&foo=baz'],
            ['foo=bar&foo=baz', null, 'foo=bar&foo=baz'],
            ['', 'foo=bar', 'foo=bar'],
            ['', 'foo=', 'foo='],
            ['', 'foo', 'foo'],
            ['foo=bar', Query::fromRFC3986('foo=baz'), 'foo=bar&foo=baz'],
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

    public function testGetParameter(): void
    {
        $query = Query::fromRFC3986('kingkong=toto&kingkong=barbaz&&=&=b');
        self::assertNull($query->get('togo'));
        self::assertSame([], $query->getAll('togo'));
        self::assertSame('toto', $query->get('kingkong'));
        self::assertNull($query->get(''));
        self::assertSame(['toto', 'barbaz'], $query->getAll('kingkong'));
        self::assertSame([null, '', 'b'], $query->getAll(''));
    }

    public function testHas(): void
    {
        self::assertTrue($this->query->has('kingkong'));
        self::assertFalse($this->query->has('togo'));
    }

    public function testCountable(): void
    {
        $query = Query::fromRFC3986('kingkong=toto&kingkong=barbaz');
        self::assertCount(2, $query);
    }

    public function testStringWithoutContent(): void
    {
        $query = Query::fromRFC3986('foo&bar&baz');
        self::assertNull($query->get('foo'));
        self::assertNull($query->get('bar'));
        self::assertNull($query->get('baz'));
    }

    public function testParams(): void
    {
        $query = Query::fromRFC3986('foo[]=bar&foo[]=baz');
        /** @var array $params */
        $params = $query->params();
        self::assertCount(1, $params);
        self::assertSame(['bar', 'baz'], $query->params('foo'));
        self::assertNull($query->params('foo[]'));
    }

    /**
     * @dataProvider withoutPairProvider
     */
    public function testwithoutPair(string $origin, array $without, string $result): void
    {
        self::assertSame($result, (string) (Query::fromRFC3986($origin))->withoutPair(...$without));
    }

    public static function withoutPairProvider(): array
    {
        return [
            ['foo&bar&baz&to.go=toofan', ['foo', 'to.go'], 'bar&baz'],
            ['foo&bar&baz&to.go=toofan', ['foo', 'unknown'], 'bar&baz&to.go=toofan'],
            ['foo&bar&baz&to.go=toofan', ['tata', 'query'], 'foo&bar&baz&to.go=toofan'],
            ['a=b&c=d', ['a'], 'c=d'],
            ['a=a&b=b&a=a&c=c', ['a'], 'b=b&c=c'],
            ['a=a&=&b=b&c=c', [''], 'a=a&b=b&c=c'],
            ['a=a&&b=b&c=c', [''], 'a=a&b=b&c=c'],
        ];
    }

    public function testWithoutPairVariadicArgument(): void
    {
        $query = Query::fromRFC3986('foo&bar=baz');

        self::assertSame($query, $query->withoutPair());
    }

    public function testwithoutPairGetterMethod(): void
    {
        $query = Query::new()->appendTo('first', 1);
        self::assertTrue($query->has('first'));
        self::assertSame('1', $query->get('first'));
        $query = $query->withoutPair('first');
        self::assertFalse($query->has('first'));
        $query = $query
            ->appendTo('first', 1)
            ->appendTo('first', 10)
            ->withoutPair('first')
        ;
        self::assertFalse($query->has('first'));
    }

    /**
     * @dataProvider withoutParamProvider
     */
    public function testwithoutParam(array $origin, array $without, string $expected): void
    {
        self::assertSame($expected, (string) Query::fromParams($origin)->withoutParam(...$without));
    }

    public static function withoutParamProvider(): array
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

    public function testwithoutParamDoesNotChangeParamsKey(): void
    {
        $data = [
            'foo' => [
                'bar',
                'baz',
            ],
        ];

        $query = Query::fromParams($data);
        self::assertSame('foo%5B0%5D=bar&foo%5B1%5D=baz', $query->value());
        $new_query = $query->withoutParam('foo[0]');
        self::assertSame('foo%5B1%5D=baz', $new_query->value());
        self::assertSame(['foo' => [1 => 'baz']], $new_query->params());
    }

    public function testWithoutParamVariadicArgument(): void
    {
        $query = Query::fromRFC3986('foo&bar=baz');

        self::assertSame($query, $query->withoutParam());
    }

    public function testCreateFromParamsWithTraversable(): void
    {
        $data = [
            'foo' => [
                'bar',
                'baz',
            ],
        ];
        $query = Query::fromParams(new ArrayIterator($data));
        self::assertSame($data, $query->params());
    }

    public function testCreateFromParamsWithQueryObject(): void
    {
        $query = Query::fromRFC3986('a=1&b=2');
        self::assertEquals($query->value(), Query::fromParams($query)->value());
    }

    public static function testWithoutNumericIndices(): void
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

        $query = Query::fromParams($data);
        self::assertSame($with_indices, $query->value());
        self::assertSame($data, $query->params());

        $new_query = $query->withoutNumericIndices();
        self::assertSame($without_indices, $new_query->value());
        self::assertSame($data, $new_query->params());
    }

    public function testWithoutNumericIndicesRetursSameInstance(): void
    {
        self::assertSame($this->query->withoutNumericIndices(), $this->query);
    }

    public function testWithoutNumericIndicesReturnsAnother(): void
    {
        $query = (Query::fromRFC3986('foo[3]'))->withoutNumericIndices();
        self::assertTrue($query->has('foo[]'));
        self::assertFalse($query->has('foo[3]'));
    }

    public function testWithoutNumericIndicesDoesNotAffectPairValue(): void
    {
        $query = Query::fromParams(['foo' => 'bar[3]']);
        self::assertSame($query, $query->withoutNumericIndices());
    }

    public function testCreateFromParamsOnEmptyParams(): void
    {
        $query = Query::fromParams([]);
        self::assertSame($query, $query->withoutNumericIndices());
    }

    public function testCreateFromParamsWithObject(): void
    {
        $query = Query::fromParams(new DateInterval('PT1H'));
        self::assertTrue($query->has('f'));
        self::assertTrue($query->has('days'));
        self::assertTrue($query->has('y'));
    }

    public function testGetContentOnEmptyContent(): void
    {
        self::assertNull(Query::fromParams([])->value());
    }

    public function testGetContentOnHavingContent(): void
    {
        self::assertSame('foo=bar', Query::fromParams(['foo' => 'bar'])->value());
    }

    public function testGetContentOnToString(): void
    {
        self::assertSame('foo=bar', (string) Query::fromParams(['foo' => 'bar']));
    }

    public function testWithSeperatorOnHavingSeparator(): void
    {
        $query = Query::fromParams(['foo' => '/bar']);
        self::assertSame($query, $query->withSeparator('&'));
    }

    public function testWithoutNumericIndicesOnEmptyContent(): void
    {
        $query = Query::fromParams([]);
        self::assertSame($query, $query->withoutNumericIndices());
    }

    public static function testSort(): void
    {
        $query = Query::new()
            ->appendTo('a', 3)
            ->appendTo('b', 2)
            ->appendTo('a', 1)
        ;

        $sortedQuery = $query->sort();

        self::assertSame('a=3&b=2&a=1', (string) $query);
        self::assertSame('a=3&a=1&b=2', (string) $sortedQuery);
        self::assertNotEquals($sortedQuery, $query);
    }

    /**
     * @dataProvider sameQueryAfterSortingProvider
     */
    public function testSortReturnSameInstance(?string $query): void
    {
        $query = Query::fromRFC3986($query);
        $sortedQuery = $query->sort();
        self::assertSame($sortedQuery, $query);
    }

    public static function sameQueryAfterSortingProvider(): array
    {
        return [
            'same already sorted' => ['a=3&a=1&b=2'],
            'empty query' => [null],
            'contains each pair key only once' => ['batman=robin&aquaman=aqualad&foo=bar&bar=baz'],
        ];
    }

    /**
     * @dataProvider provideWithPairData
     */
    public function testWithPair(?string $query, string $key, string|null|bool $value, array $expected): void
    {
        self::assertSame($expected, (Query::fromRFC3986($query))->withPair($key, $value)->getAll($key));
    }

    public static function provideWithPairData(): array
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

    public function testWithPairBasic(): void
    {
        self::assertSame('a=B&c=d', (string) (Query::fromRFC3986('a=b&c=d'))->withPair('a', 'B'));
        self::assertSame('a=B&c=d', (string) (Query::fromRFC3986('a=b&c=d&a=e'))->withPair('a', 'B'));
        self::assertSame('a=b&c=d&e=f', (string) (Query::fromRFC3986('a=b&c=d'))->withPair('e', 'f'));
    }

    /**
     * @dataProvider mergeBasicProvider
     */
    public function testMergeBasic(string $src, UriComponentInterface|Stringable|int|string|bool|null $dest, string $expected): void
    {
        self::assertSame($expected, (string) (Query::fromRFC3986($src))->merge($dest));
    }

    public static function mergeBasicProvider(): array
    {
        return [
            'merging null' => [
                'src' => 'a=b&c=d',
                'dest' => null,
                'expected' => 'a=b&c=d',
            ],
            'merging empty string' => [
                'src' => 'a=b&c=d',
                'dest' => '',
                'expected' => 'a=b&c=d&',
            ],
            'merging simple string string' => [
                'src' => 'a=b&c=d',
                'dest' => 'a=B',
                'expected' => 'a=B&c=d',
            ],
            'merging strip additional pairs with same key' => [
                'src' => 'a=b&c=d&a=e',
                'dest' => 'a=B',
                'expected' => 'a=B&c=d',
            ],
            'merging append new data if not found in src query' => [
                'src' => 'a=b&c=d',
                'dest' => 'e=f',
                'expected' => 'a=b&c=d&e=f',
            ],
            'merge can use ComponentInterface' => [
                'src' => 'a=b&c=d',
                'dest' => Query::new(),
                'expected' => 'a=b&c=d',
            ],
        ];
    }

    public function testWithPairGetterMethods(): void
    {
        $query = Query::fromRFC3986('a=1&a=2&a=3');
        self::assertSame('1', $query->get('a'));

        $query = $query->withPair('first', 4);
        self::assertSame('1', $query->get('a'));

        $query = $query->withPair('a', 4); /* @phpstan-ignore-line */
        self::assertSame('4', $query->get('a'));

        $query = $query->withPair('q', $query);
        self::assertSame('a=4&first=4', $query->get('q'));
    }

    public function testMergeGetterMethods(): void
    {
        $query = Query::fromRFC3986('a=1&a=2&a=3');
        self::assertSame('1', $query->get('a'));

        $query = $query->merge(Query::fromRFC3986('first=4'));
        self::assertSame('1', $query->get('a'));

        $query = $query->merge('a=4');
        self::assertSame('4', $query->get('a'));

        $query = $query->merge(Query::fromPairs([['q', $query->value()]]));
        self::assertSame('a=4&first=4', $query->get('q'));
    }

    /**
     * @dataProvider provideWithoutDuplicatesData
     */
    public function testWithoutDuplicates(?string $query, ?string $expected): void
    {
        $query = Query::fromRFC3986($query);
        self::assertSame($expected, $query->withoutDuplicates()->value());
    }

    public static function provideWithoutDuplicatesData(): array
    {
        return [
            'empty query' => [null, null],
            'remove duplicate pair' => ['foo=bar&foo=bar', 'foo=bar'],
            'no duplicate pair key' => ['foo=bar&bar=foo', 'foo=bar&bar=foo'],
            'no diplicate pair value' => ['foo=bar&foo=baz', 'foo=bar&foo=baz'],
        ];
    }

    public function testAppendToSameName(): void
    {
        $query = Query::new();
        self::assertSame('a=b', (string) $query->appendTo('a', 'b'));
        self::assertSame('a=b&a=b', (string) $query->appendTo('a', 'b')->appendTo('a', 'b'));
        self::assertSame('a=b&a=b&a=c', (string) $query->appendTo('a', 'b')->appendTo('a', 'b')->appendTo('a', new class() {
            public function __toString(): string
            {
                return 'c';
            }
        }));
    }

    public function testAppendToWithEmptyString(): void
    {
        $query = Query::new();
        self::assertSame('', (string) $query->appendTo('', null));
        self::assertSame('=', (string) $query->appendTo('', ''));
        self::assertSame('a', (string) $query->appendTo('a', null));
        self::assertSame('a=', (string) $query->appendTo('a', ''));
        self::assertSame(
            'a&a=&&=',
            (string) $query
            ->appendTo('a', null)
            ->appendTo('a', '')
            ->appendTo('', null)
            ->appendTo('', '')
        );
    }

    public function testAppendToWithGetter(): void
    {
        $query = Query::new()
            ->appendTo('first', 1)
            ->appendTo('second', 2)
            ->appendTo('third', '')
            ->appendTo('first', 10)
        ;
        self::assertSame('first=1&second=2&third=&first=10', (string) $query);
        self::assertTrue($query->has('first'));
        self::assertSame('1', $query->get('first'));
        self::assertSame('2', $query->get('second'));
        self::assertSame('', $query->get('third'));

        $newQuery = $query->appendTo('first', 10);
        self::assertSame('first=1&second=2&third=&first=10&first=10', (string) $newQuery);
        self::assertSame('1', $newQuery->get('first'));
    }

    /**
     * @dataProvider getURIProvider
     */
    public function testCreateFromUri(Psr7UriInterface|UriInterface $uri, ?string $expected): void
    {
        $query = Query::fromUri($uri);

        self::assertSame($expected, $query->value());
    }

    public static function getURIProvider(): iterable
    {
        return [
            'PSR-7 URI object' => [
                'uri' => Http::new('http://example.com?foo=bar'),
                'expected' => 'foo=bar',
            ],
            'PSR-7 URI object with no query' => [
                'uri' => Http::new('http://example.com'),
                'expected' => null,
            ],
            'PSR-7 URI object with empty string query' => [
                'uri' => Http::new('http://example.com?'),
                'expected' => null,
            ],
            'League URI object' => [
                'uri' => Uri::new('http://example.com?foo=bar'),
                'expected' => 'foo=bar',
            ],
            'League URI object with no query' => [
                'uri' => Uri::new('http://example.com'),
                'expected' => null,
            ],
            'League URI object with empty string query' => [
                'uri' => Uri::new('http://example.com?'),
                'expected' => '',
            ],
        ];
    }

    public function testCreateFromRFCSpecification(): void
    {
        $query = Query::fromRFC3986('foo=b%20ar|foo=baz', '|');

        self::assertEquals($query, Query::fromRFC1738('foo=b+ar|foo=baz', '|'));
    }
}

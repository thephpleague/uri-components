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
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Http;
use League\Uri\QueryComposeMode;
use League\Uri\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use ValueError;

use function json_encode;

#[CoversClass(Query::class)]
#[Group('query')]
final class QueryTest extends TestCase
{
    protected Query $query;

    protected function setUp(): void
    {
        $this->query = Query::new('kingkong=toto');
    }

    public function testSeparator(): void
    {
        $query = Query::new('foo=bar&kingkong=toto');
        $newQuery = $query->withSeparator(';');
        self::assertSame('&', $query->getSeparator());
        self::assertSame(';', $newQuery->getSeparator());
        self::assertSame('foo=bar;kingkong=toto', $newQuery->value());
        self::assertFalse($query->isEmpty());

        $this->expectException(SyntaxError::class);
        $newQuery->withSeparator('');
    }

    public function testIterator(): void
    {
        $query = Query::new('a=1&b=2&c=3&a=4');

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
        self::assertSame(
            '"a=1&b=2&c=3&a=4&a=3+d"',
            json_encode(Query::new('a=1&b=2&c=3&a=4&a=3%20d'))
        );
    }

    public function testGetUriComponent(): void
    {
        self::assertSame('', Query::new()->getUriComponent());
        self::assertSame('?', Query::new('')->getUriComponent());
        self::assertSame('?foo=bar', Query::new('foo=bar')->getUriComponent());
        self::assertTrue(Query::new()->isEmpty());
        self::assertFalse(Query::new('')->isEmpty());
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

        self::assertCount(2, Query::fromPairs($iterable));
    }

    public function testcreateFromPairsWithQueryObject(): void
    {
        $query = Query::new('a=1&b=2');
        self::assertEquals($query, Query::fromPairs($query));
    }

    public function testCreateFromPairsFailedWithBadIterable(): void
    {
        $this->expectException(SyntaxError::class);

        Query::fromPairs([['toto' => ['foo' => [(object) []]]]]); /* @phpstan-ignore-line */
    }

    public function testNormalization(): void
    {
        self::assertSame('foo=bar', Query::new('foo=bar&&&=&&&&&&')->withoutEmptyPairs()->value());
        self::assertNull(Query::new('&=bar&=')->withoutEmptyPairs()->toRFC1738());
        self::assertNull(Query::new('&&&&&&&&&&&')->withoutEmptyPairs()->toRFC1738());
        self::assertSame($this->query, $this->query->withoutEmptyPairs());
    }

    #[DataProvider('validAppendValue')]
    public function testAppend(?string $query, Stringable|string|null $appendData, ?string $expected): void
    {
        self::assertSame($expected, Query::new($query)->append($appendData)->value());
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
            ['foo=bar', Query::new('foo=baz'), 'foo=bar&foo=baz'],
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
        $query = Query::new('kingkong=toto&kingkong=barbaz&&=&=b');

        self::assertNull($query->get('togo'));
        self::assertNull($query->first('togo'));
        self::assertNull($query->last('togo'));
        self::assertSame([], $query->getAll('togo'));
        self::assertSame('toto', $query->get('kingkong'));
        self::assertNull($query->get(''));
        self::assertSame(['toto', 'barbaz'], $query->getAll('kingkong'));
        self::assertSame('toto', $query->first('kingkong'));
        self::assertSame('barbaz', $query->last('kingkong'));
        self::assertSame([null, '', 'b'], $query->getAll(''));
    }

    public function testHas(): void
    {
        self::assertTrue($this->query->has('kingkong'));
        self::assertFalse($this->query->has('togo'));
    }

    public function testCountable(): void
    {
        self::assertCount(2, Query::new('kingkong=toto&kingkong=barbaz'));
    }

    public function testStringWithoutContent(): void
    {
        $query = Query::new('foo&bar&baz');

        self::assertNull($query->get('foo'));
        self::assertNull($query->get('bar'));
        self::assertNull($query->get('baz'));
    }

    public function testParams(): void
    {
        $query = Query::new('foo[]=bar&foo[]=baz');

        self::assertCount(1, $query->parameters());
        self::assertSame(['bar', 'baz'], $query->getList('foo'));
        self::assertSame([], $query->getList('foo[]'));
    }

    #[DataProvider('withoutKeyPairProvider')]
    public function testwithoutKeyPair(string $origin, array $without, string $result): void
    {
        self::assertSame($result, (string) Query::new($origin)->withoutPairByKey(...$without));
    }

    public static function withoutKeyPairProvider(): array
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

    public function testwithoutKeyPairVariadicArgument(): void
    {
        $query = Query::new('foo&bar=baz');

        self::assertSame($query, $query->withoutPairByKey());
    }

    public function testwithoutKeyPairGetterMethod(): void
    {
        $query = Query::new()->appendTo('first', 1);
        self::assertTrue($query->has('first'));
        self::assertSame('1', $query->get('first'));
        $query = $query->withoutPairByKey('first');
        self::assertFalse($query->has('first'));
        $query = $query
            ->appendTo('first', 1)
            ->appendTo('first', 10)
            ->withoutPairByKey('first')
        ;
        self::assertFalse($query->has('first'));
    }

    /**
     * @param list<Stringable|string|int|bool|null> $values
     */
    #[DataProvider('providePairsValuesToBeRemoved')]
    public function testWithoutPairByValue(string $query, array $values, string $expected): void
    {
        self::assertSame($expected, Query::fromRFC3986($query)->withoutPairByValue(...$values)->value());
    }

    public static function providePairsValuesToBeRemoved(): iterable
    {
        yield 'remove nothing' => [
            'query' => 'foo=bar&foo=baz',
            'values' => [],
            'expected' => 'foo=bar&foo=baz',
        ];

        yield 'remove nothing if the value is not found' => [
            'query' => 'foo=bar&foo=baz',
            'values' => ['fdasfdsdsafsd'],
            'expected' => 'foo=bar&foo=baz',
        ];

        yield 'remove null value pairs' => [
            'query' => 'foo=bar&foo=baz&foo',
            'values' => [null],
            'expected' => 'foo=bar&foo=baz',
        ];

        yield 'remove empty value pairs' => [
            'query' => 'foo=bar&foo=baz&foo=',
            'values' => [''],
            'expected' => 'foo=bar&foo=baz',
        ];

        yield 'remove multi match value pairs' => [
            'query' => 'toto=bar&margo=bar&bar=toto',
            'values' => ['bar'],
            'expected' => 'bar=toto',
        ];

        yield 'remove boolean false value' => [
            'query' => 'toto=false&margo=true&bar=toto',
            'values' => [false],
            'expected' => 'margo=true&bar=toto',
        ];

        yield 'remove boolean true value' => [
            'query' => 'toto=false&margo=true&bar=toto',
            'values' => [true],
            'expected' => 'toto=false&bar=toto',
        ];
    }

    /**
     * @param list{0:string, 1:Stringable|string|int|bool|null} $pair
     */
    #[DataProvider('providePairsToBeRemoved')]
    public function testWithoutPairByKeyValue(string $query, array $pair, string $expected): void
    {
        self::assertSame($expected, Query::fromRFC3986($query)->withoutPairByKeyValue(...$pair)->value());
    }

    public static function providePairsToBeRemoved(): iterable
    {
        yield 'remove nothing' => [
            'query' => 'foo=bar&foo=baz',
            'pair' => ['foo', 'fdasfdsdsafsd'],
            'expected' => 'foo=bar&foo=baz',
        ];

        yield 'remove pair without value' => [
            'query' => 'foo=bar&foo=baz&foo',
            'pair' => ['foo', null],
            'expected' => 'foo=bar&foo=baz',
        ];

        yield 'remove only one pair' => [
            'query' => 'foo=bar&foo=baz',
            'pair' => ['foo', 'bar'],
            'expected' => 'foo=baz',
        ];

        yield 'remove multiple matching pairs' => [
            'query' => 'foo=bar&foo=baz&foo=bar',
            'pair' => ['foo', 'bar'],
            'expected' => 'foo=baz',
        ];

        yield 'remove boolean false matching pairs' => [
            'query' => 'foo=bar&foo=false&foo=true',
            'pair' => ['foo', false],
            'expected' => 'foo=bar&foo=true',
        ];

        yield 'remove boolean true matching pairs' => [
            'query' => 'foo=bar&foo=true&foo=false',
            'pair' => ['foo', true],
            'expected' => 'foo=bar&foo=false',
        ];
    }

    #[DataProvider('withoutParamProvider')]
    public function testwithoutParam(array $origin, array $without, string $expected): void
    {
        self::assertSame(
            $expected,
            Query::fromVariable($origin)
                ->withoutList(...$without)
                ->withoutPairByKey(...$without)
                ->toString()
        );
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

    public function testWithoutParamDoesNotChangeParamsKey(): void
    {
        $data = [
            'foo' => [
                'bar',
                'baz',
            ],
        ];

        $query = Query::fromVariable($data);
        self::assertSame('foo%5B0%5D=bar&foo%5B1%5D=baz', $query->value());

        self::assertTrue($query->hasList('foo'));
        self::assertFalse($query->hasList('bar'));
        self::assertFalse($query->hasList('foo', 'bar'));

        $newQuery = $query->withoutPairByKey('foo[0]');

        self::assertSame('foo%5B1%5D=baz', $newQuery->value());
        self::assertSame(['foo' => [1 => 'baz']], $newQuery->parameters());
    }

    public function testWithoutParamVariadicArgument(): void
    {
        $query = Query::new('foo&bar=baz');

        self::assertSame($query, $query->withoutPairByKey());
    }

    public function testCreateFromParamsWithTraversable(): void
    {
        $data = [
            'foo' => [
                'bar',
                'baz',
            ],
        ];

        self::assertSame([], Query::fromVariable(new ArrayIterator($data))->parameters());
    }

    public function testCreateFromParamsWithQueryObject(): void
    {
        $query = Query::new('a=1&b=2');
        self::assertEquals('pairs%5B0%5D%5B0%5D=a&pairs%5B0%5D%5B1%5D=1&pairs%5B1%5D%5B0%5D=b&pairs%5B1%5D%5B1%5D=2&separator=%26&parameters%5Ba%5D=1&parameters%5Bb%5D=2', Query::fromVariable($query)->value());
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

        $withIndices = 'filter%5Bfoo%5D%5B0%5D=bar&filter%5Bfoo%5D%5B1%5D=baz&filter%5Bbar%5D%5Bbar%5D=foo&filter%5Bbar%5D%5Bfoo%5D=bar';
        $withoutIndices = 'filter%5Bfoo%5D%5B%5D=bar&filter%5Bfoo%5D%5B%5D=baz&filter%5Bbar%5D%5Bbar%5D=foo&filter%5Bbar%5D%5Bfoo%5D=bar';

        $query = Query::fromVariable($data);
        self::assertSame($withIndices, $query->value());
        self::assertSame($data, $query->parameters());

        $newQuery = $query->withoutNumericIndices();
        self::assertSame($withoutIndices, $newQuery->value());
        self::assertSame($data, $newQuery->parameters());
    }

    public function testWithoutNumericIndicesRetursSameInstance(): void
    {
        self::assertSame($this->query->withoutNumericIndices(), $this->query);
    }

    public function testWithoutNumericIndicesReturnsAnother(): void
    {
        $query = (Query::new('foo[3]'))->withoutNumericIndices();

        self::assertTrue($query->has('foo[]'));
        self::assertFalse($query->has('foo[3]'));
    }

    public function testWithoutNumericIndicesDoesNotAffectPairValue(): void
    {
        $query = Query::fromVariable(['foo' => 'bar[3]']);

        self::assertSame($query, $query->withoutNumericIndices());
    }

    public function testCreateFromParamsOnEmptyParams(): void
    {
        $query = Query::fromVariable([]);

        self::assertSame($query, $query->withoutNumericIndices());
    }

    public function testGetContentOnEmptyContent(): void
    {
        self::assertSame('', Query::fromVariable([])->value());
    }

    public function testGetContentOnHavingContent(): void
    {
        self::assertSame('foo=bar', Query::fromVariable(['foo' => 'bar'])->value());
    }

    public function testGetContentOnToString(): void
    {
        self::assertSame('foo=bar', (string) Query::fromVariable(['foo' => 'bar']));
    }

    public function testWithSeperatorOnHavingSeparator(): void
    {
        $query = Query::fromVariable(['foo' => '/bar']);

        self::assertSame($query, $query->withSeparator('&'));
    }

    public function testWithoutNumericIndicesOnEmptyContent(): void
    {
        $query = Query::fromVariable([]);

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

    #[DataProvider('sameQueryAfterSortingProvider')]
    public function testSortReturnSameInstance(?string $query): void
    {
        $query = Query::new($query);

        self::assertSame($query, $query->sort());
    }

    public static function sameQueryAfterSortingProvider(): array
    {
        return [
            'same already sorted' => ['a=3&a=1&b=2'],
            'empty query' => [null],
            'contains each pair key only once' => ['aquaman=aqualad&bar=baz&batman=robin&foo=bar'],
        ];
    }

    #[DataProvider('provideWithPairData')]
    public function testWithPair(?string $query, string $key, string|null|bool $value, array $expected): void
    {
        self::assertSame($expected, Query::new($query)->withPair($key, $value)->getAll($key));
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
        self::assertSame('a=B&c=d', Query::new('a=b&c=d')->withPair('a', 'B')->toString());
        self::assertSame('a=B&c=d', Query::new('a=b&c=d&a=e')->withPair('a', 'B')->toString());
        self::assertSame('a=b&c=d&e=f', Query::new('a=b&c=d')->withPair('e', 'f')->toString());
    }

    #[DataProvider('mergeBasicProvider')]
    public function testMergeBasic(string $src, Stringable|string|null $dest, string $expected): void
    {
        self::assertSame($expected, Query::new($src)->merge($dest)->toString());
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
        $query = Query::new('a=1&a=2&a=3');
        self::assertSame('1', $query->get('a'));

        $query = $query->withPair('first', 4);
        self::assertSame('1', $query->get('a'));

        $query = $query->withPair('a', 4);
        self::assertSame('4', $query->get('a'));

        $query = $query->withPair('q', $query);
        self::assertSame('a=4&first=4', $query->get('q'));
    }

    public function testMergeGetterMethods(): void
    {
        $query = Query::new('a=1&a=2&a=3');
        self::assertSame('1', $query->get('a'));

        $query = $query->merge(Query::new('first=4'));
        self::assertSame('1', $query->get('a'));

        $query = $query->merge('a=4');
        self::assertSame('4', $query->get('a'));

        $query = $query->merge(Query::fromPairs([['q', $query->value()]]));
        self::assertSame('a=4&first=4', $query->get('q'));
    }

    #[DataProvider('provideWithoutDuplicatesData')]
    public function testWithoutDuplicates(?string $query, ?string $expected): void
    {
        self::assertSame($expected, Query::new($query)->withoutDuplicates()->value());
    }

    public static function provideWithoutDuplicatesData(): array
    {
        return [
            'empty query' => [null, null],
            'remove duplicate pair' => ['foo=bar&foo=bar', 'foo=bar'],
            'no duplicate pair key' => ['foo=bar&bar=foo', 'foo=bar&bar=foo'],
            'no duplicate pair value' => ['foo=bar&foo=baz', 'foo=bar&foo=baz'],
        ];
    }

    public function testAppendToSameName(): void
    {
        $query = Query::new();
        self::assertSame('a=b', (string) $query->appendTo('a', 'b'));
        self::assertSame('a=b&a=b', (string) $query->appendTo('a', 'b')->appendTo('a', 'b'));
        self::assertSame('a=b&a=b&a=c', (string) $query->appendTo('a', 'b')->appendTo('a', 'b')->appendTo('a', new class () {
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

    #[DataProvider('getURIProvider')]
    public function testCreateFromUri(Psr7UriInterface|UriInterface $uri, ?string $expected): void
    {
        self::assertSame($expected, Query::fromUri($uri)->value());
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

    public function testItFailsToCreateFromRFCSpecificationWithInvalidSeparator(): void
    {
        $this->expectException(SyntaxError::class);

        Query::fromRFC3986('foo=b%20ar;foo=baz', ''); /* @phpstan-ignore-line */
    }

    public function testItFailsToCreateFromRFCSpecificationWithEmptySeparator(): void
    {
        $this->expectException(SyntaxError::class);

        Query::fromRFC1738('foo=b%20ar;foo=baz', ''); /* @phpstan-ignore-line */
    }

    public function testInstantiationFromURLSearchParams(): void
    {
        $expected = ['foo' => 'bar'];
        $query = Query::fromVariable(URLSearchParams::fromVariable($expected));

        self::assertSame('', $query->value());
    }

    #[DataProvider('provideIndexOfPairs')]
    public function test_index_of(array $pairs, string $key, int $nth, ?int $expected): void
    {
        self::assertSame($expected, Query::fromPairs($pairs)->indexOf($key, $nth));
    }

    public static function provideIndexOfPairs(): array
    {
        return [
            // --- Empty dataset ---
            'empty array' => [
                'pairs' => [],
                'key' => 'a',
                'nth' => 0,
                'expected' => null,
            ],

            // --- Single occurrence ---
            'single match' => [
                'pairs' => [['a', 1], ['b', 2], ['c', 3]],
                'key' => 'a',
                'nth' => 0,
                'expected' => 0,
            ],
            'single no match' => [
                'pairs' => [['a', 1], ['b', 2], ['c', 3]],
                'key' =>  'x',
                'nth' => 0,
                'expected' => null,
            ],

            // --- Multiple matches ---
            'first occurrence' => [
                'pairs' => [['a', 1], ['b', 2], ['a', 3], ['c', 4], ['a', 5]],
                'key' => 'a',
                'nth' => 0,
                'expected' =>  0,
            ],
            'second occurrence' => [
                'pairs' => [['a', 1], ['b', 2], ['a', 3], ['c', 4], ['a', 5]],
                'key' => 'a',
                'nth' => 1,
                'expected' => 2,
            ],
            'third occurrence' => [
                'pairs' => [['a', 1], ['b', 2], ['a', 3], ['c', 4], ['a', 5]],
                'key' => 'a',
                'nth' => 2,
                'expected' => 4,
            ],
            'out of bounds positive' => [
                'pairs' => [['a', 1], ['a', 2]],
                'key' => 'a',
                'nth' => 2,
                'expected' => null,
            ],

            // --- Negative nth (count from end) ---
            'last occurrence (-1)' => [
                'pairs' => [['a', 1], ['b', 2], ['a', 3], ['a', 4]],
                'key' => 'a',
                'nth' => -1,
                'expected' => 3,
            ],
            'second-to-last (-2)' => [
                'pairs' => [['a', 1], ['b', 2], ['a', 3], ['a', 4]],
                'key' => 'a',
                'nth' => -2,
                'expected' => 2,
            ],
            'negative out of bounds' => [
                'pairs' => [['a', 1], ['b', 2]],
                'key' => 'a',
                'nth' => -3,
                'expected' => null,
            ],
            'negative no matches' => [
                'pairs' => [['x', 1], ['y', 2]],
                'key' => 'z',
                'nth' => -1,
                'expected' => null,
            ],
        ];
    }

    #[DataProvider('provideIndexOfValues')]
    public function test_index_of_value(array $pairs, ?string $value, int $nth, ?int $expected): void
    {
        self::assertSame($expected, Query::fromPairs($pairs)->indexOfValue($value, $nth));
    }

    public static function provideIndexOfValues(): array
    {
        return [
            // --- Empty dataset ---
            'empty array' => [
                'pairs' => [],
                'value' => '1',
                'nth' => 0,
                'expected' => null,
            ],

            // --- Single occurrence ---
            'single match' => [
                'pairs' => [['a', 1], ['b', 2], ['c', 3]],
                'value' => '1',
                'nth' => 0,
                'expected' => 0,
            ],
            'single no match' => [
                'pairs' => [['a', 1], ['b', 2], ['c', 3]],
                'value' => '42',
                'nth' => 0,
                'expected' => null,
            ],

            // --- Multiple matches ---
            'first occurrence' => [
                'pairs' => [['a', 1], ['b', 2], ['c', 1], ['d', 3], ['e', 1]],
                'value' => '1',
                'nth' => 0,
                'expected' => 0,
            ],
            'second occurrence' => [
                'pairs' => [['a', 1], ['b', 2], ['c', 1], ['d', 3], ['e', 1]],
                'value' => '1',
                'nth' => 1,
                'expected' => 2,
            ],
            'third occurrence' => [
                'pairs' => [['a', 1], ['b', 2], ['c', 1], ['d', 3], ['e', 1]],
                'value' => '1',
                'nth' => 2,
                'expected' => 4,
            ],
            'out of bounds positive' => [
                'pairs' => [['a', 1], ['b', 1]],
                'value' => '1',
                'nth' => 2,
                'expected' => null,
            ],

            // --- Negative nth (count from end) ---
            'last occurrence (-1)' => [
                'pairs' => [['a', 1], ['b', 2], ['c', 1], ['d', 1]],
                'value' => '1',
                'nth' => -1,
                'expected' => 3,
            ],
            'second-to-last (-2)' => [
                'pairs' => [['a', 1], ['b', 2], ['c', 1], ['d', 1]],
                'value' => '1',
                'nth' => -2,
                'expected' => 2,
            ],
            'negative out of bounds' => [
                'pairs' => [['a', 1], ['b', 2]],
                'value' => '1',
                'nth' => -3,
                'expected' => null,
            ],
            'negative no matches' => [
                'pairs' => [['x', 1], ['y', 2]],
                'value' => '42',
                'nth' => -1,
                'expected' => null,
            ],
        ];
    }

    public function testReplaceExistingPair(): void
    {
        $query = Query::new('a=1&b=2&c=3');
        $result = $query->replace(1, 'b', 99);

        self::assertNotSame($query, $result, 'replace() should return a new instance');
        self::assertSame('a=1&b=2&c=3', $query->toString(), 'Original instance must not be modified');
        self::assertSame('a=1&b=99&c=3', $result->toString());
    }

    public function testReplaceWithSamePairReturnsSameInstance(): void
    {
        $query = Query::new('a=1&b=2');
        $result = $query->replace(0, 'a', 1);

        self::assertSame($query, $result, 'Should return the same instance when nothing changes');
    }

    public function testReplaceWithNullValue(): void
    {
        $query = Query::new('a=1&b=2');
        $result = $query->replace(1, 'b', null);

        self::assertSame('a=1&b', $result->toString());
    }

    public function testReplaceLastPairUsingNegativeOffset(): void
    {
        $query = Query::new('a=1&b=2&c=3');
        $result = $query->replace(-1, 'c', 99);

        self::assertSame('a=1&b=2&c=99', $result->toString());
    }

    public function testReplaceSecondToLastUsingNegativeOffset(): void
    {
        $query = Query::new('a=1&b=2&c=3');
        $result = $query->replace(-2, 'b', 88);

        self::assertSame('a=1&b=88&c=3', $result->toString());
    }

    public function testReplaceWithPositiveOutOfBoundsOffsetThrows(): void
    {
        $query = Query::new('a=1&b=2');

        $this->expectException(ValueError::class);
        $query->replace(5, 'x', 10);
    }

    public function testReplaceWithNegativeOutOfBoundsOffsetThrows(): void
    {
        $query = Query::new('a=1&b=2');

        $this->expectException(ValueError::class);
        $query->replace(-3, 'x', 10);
    }

    public function testReplaceDoesNotMutateOriginal(): void
    {
        $query = Query::new('a=1&b=2');
        $query->replace(1, 'b', 5);

        self::assertSame('a=1&b=2', $query->toString(), 'Original instance must remain unchanged');
    }

    public function test_it_can_add_parameters(): void
    {
        $query = Query::new('a=1&b=2')->withList('c', [1, 2, 3]);

        self::assertSame('a=1&b=2&c%5B0%5D=1&c%5B1%5D=2&c%5B2%5D=3', $query->toString());

        $query = Query::new('a=1&b=2')->withList('c', [1, 2, 3], QueryComposeMode::Safe);

        self::assertSame('a=1&b=2&c%5B%5D=1&c%5B%5D=2&c%5B%5D=3', $query->toString());
    }

    public function test_it_can_add_parameters_without_deleting_non_array_like_parameters(): void
    {
        $query = Query::new('a=1&b=2')->withList('a', [1, 2, 3]);

        self::assertSame('a=1&b=2&a%5B0%5D=1&a%5B1%5D=2&a%5B2%5D=3', $query->toString());
    }

    public function test_it_can_remove_parameters_list_like_parameters(): void
    {
        $query = Query::new('a=1&b=2&a%5B0%5D=1&a%5B1%5D=2&a%5B2%5D=3');

        self::assertTrue($query->has('a'));
        self::assertTrue($query->has('b'));
        self::assertTrue($query->hasList('a'));
        self::assertFalse($query->hasList('b'));

        $altQuery = $query->withoutList('a');

        self::assertSame('a=1&b=2', $altQuery->toString());
        self::assertTrue($altQuery->has('a'));
        self::assertTrue($altQuery->has('b'));
        self::assertFalse($altQuery->hasList('a'));
        self::assertFalse($altQuery->hasList('b'));
    }
}

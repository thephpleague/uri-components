<?php

/**
 * League.Uri (https://uri.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\Components;

use ArgumentCountError;
use DateInterval;
use IteratorAggregate;
use League\Uri\Exceptions\SyntaxError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;
use Traversable;

final class URLSearchParamsTest extends TestCase
{
    public function testBasicConstructor(): void
    {
        $params = new URLSearchParams();
        self::assertSame('', $params->toString());
        self::assertTrue($params->isEmpty());
        self::assertSame([], [...$params]);
        self::assertSame([], [...$params->keys()]);
        self::assertSame([], [...$params->values()]);

        $params = new URLSearchParams('');
        self::assertSame('', $params->toString());
        self::assertTrue($params->isEmpty());

        $params = new URLSearchParams('a=b');
        self::assertSame('a=b', $params->toString());

        $params = new URLSearchParams('?a=b');
        self::assertSame('a=b', $params->toString());

        $params = new URLSearchParams($params);
        self::assertSame('a=b', $params->toString());

        $params = new URLSearchParams(new stdClass());
        self::assertSame('', $params->toString());
    }

    public function testTextConstructor(): void
    {
        $params = new URLSearchParams('a=b');
        self::assertTrue($params->has('a'));
        self::assertFalse($params->has('b'));

        $params = new URLSearchParams('a=b&c');
        self::assertTrue($params->has('a'));
        self::assertTrue($params->has('c'));
        self::assertSame(['a', 'c'], [...$params->keys()]);
        self::assertSame(['b', ''], [...$params->values()]);

        $params = new URLSearchParams('&a&&& &&&&&a+b=& c&m%c3%b8%c3%b8');
        self::assertTrue($params->has('a'), 'Search params object has name "a"');
        self::assertTrue($params->has('a b'), 'Search params object has name "a b"');
        self::assertTrue($params->has(' '), 'Search params object has name " "');
        self::assertFalse($params->has('c'), 'Search params object did not have the name "c"');
        self::assertTrue($params->has(' c'), 'Search params object has name " c"');
        self::assertTrue($params->has('møø'), 'Search params object has name "møø"');

        $params = new URLSearchParams('id=0&value=%');
        self::assertTrue($params->has('id'), 'Search params object has name "id"');
        self::assertTrue($params->has('value'), 'Search params object has name "value"');
        self::assertSame('0', $params->get('id'));
        self::assertSame('0', $params->first('id'));
        self::assertSame('%', $params->get('value'));

        $params = new URLSearchParams('b=%2sf%2a');
        self::assertTrue($params->has('b'), 'Search params object has name "b"');
        self::assertSame('%2sf*', $params->get('b'));

        $params = new URLSearchParams('b=%2%2af%2a');
        self::assertTrue($params->has('b'), 'Search params object has name "b"');
        self::assertSame('%2*f*', $params->get('b'));

        $params = new URLSearchParams('b=%%2a');
        self::assertTrue($params->has('b'), 'Search params object has name "b"');
        self::assertSame('%*', $params->get('b'));
    }

    public function testConstructorWithObjects(): void
    {
        $seed = new URLSearchParams('a=b&c=d');
        $params = new URLSearchParams($seed);
        self::assertSame('b', $params->get('a'));
        self::assertSame('d', $params->get('c'));
        self::assertFalse($params->has('d'));

        // The name-value pairs are copied when created; later updates should not be observable.
        $seed->append('e', 'f');
        self::assertFalse($params->has('e'));

        $params->append('g', 'h');
        self::assertFalse($seed->has('g'));

        $params = new URLSearchParams(new class () implements IteratorAggregate {
            public function getIterator(): Traversable
            {
                yield from ['a' => 'b', 'c' => 'd'];
            }
        });
        self::assertSame('b', $params->get('a'));
        self::assertSame('d', $params->get('c'));
        self::assertFalse($params->has('d'));
    }

    public function testQueryParsing(): void
    {
        $params = new URLSearchParams('a=b+c');
        self::assertSame('b c', $params->get('a'));

        $params = new URLSearchParams('a+b=c');
        self::assertSame('c', $params->get('a b'));
    }

    public function testQueryEncoding(): void
    {
        $expected = '+15555555555';
        $params = new URLSearchParams();
        $params->set('query', $expected);
        $newParams = new URLSearchParams($params->toString());
        self::assertSame('query=%2B15555555555', $params->toString());
        self::assertSame($expected, $params->get('query'));
        self::assertSame($expected, $newParams->get('query'));
    }

    public function testParseSpace(): void
    {
        $params = new URLSearchParams('a=b c');
        self::assertSame($params->get('a'), 'b c');

        $params = new URLSearchParams('a b=c');
        self::assertSame('c', $params->get('a b'));
    }

    public function testParseEncodedSpace(): void
    {
        $params = new URLSearchParams('a=b%20c');
        self::assertSame('b c', $params->get('a'));

        $params = new URLSearchParams('a%20b=c');
        self::assertSame('c', $params->get('a b'));
    }

    public function testNewInstanceWithSequenceOfSequencesOfString(): void
    {
        $params = new URLSearchParams([]);
        self::assertSame('', (string) $params);

        $params = new URLSearchParams([['a', 'b'], ['c', 'd']]);
        self::assertSame('b', $params->get('a'));
        self::assertSame('d', $params->get('c'));
    }

    #[DataProvider('providesInvalidSequenceOfSequencesOfString')]
    public function testNewInstanceWithSequenceOfSequencesOfStringFails(array $sequences): void
    {
        $this->expectException(SyntaxError::class);

        new URLSearchParams($sequences);
    }

    public static function providesInvalidSequenceOfSequencesOfString(): iterable
    {
        return [
            [
                [[1]],
            ],
            [
                [[1, 2, 3]],
            ],
        ];
    }

    #[DataProvider('providesComplexConstructorData')]
    public function testComplexConstructor(string $json): void
    {
        /** @var object{input: string, output: array<array{0: string, 1: string}>, name: string} $res */
        $res = json_decode($json);

        $params = new URLSearchParams($res->input);
        self::assertSame($res->output, [...$params], 'Invalid '.$res->name);
    }

    public static function providesComplexConstructorData(): iterable
    {
        return [
            ['{ "input": {"+": "%C2"}, "output": [["+", "%C2"]], "name": "object with +" }'],
            ['{ "input": {"c": "x", "a": "?"}, "output": [["c", "x"], ["a", "?"]], "name": "object with two keys" }'],
            ['{ "input": [["c", "x"], ["a", "?"]], "output": [["c", "x"], ["a", "?"]], "name": "array with two keys" }'],
            // invalid json errors in PHP ['{ "input": {"\uD835x": "1", "xx": "2", "\uD83Dx": "3"}, "output": [["\uFFFDx", "3"], ["xx", "2"]], "name": "2 unpaired surrogates (no trailing)" }'],
            // invalid json errors in PHP ['{ "input": {"x\uDC53": "1", "x\uDC5C": "2", "x\uDC65": "3"}, "output": [["x\uFFFD", "3"]], "name": "3 unpaired surrogates (no leading)" }'],
            // invalid json errors in PHP ['{ "input": {"a\0b": "42", "c\uD83D": "23", "d\u1234": "foo"}, "output": [["a\0b", "42"], ["c\uFFFD", "23"], ["d\u1234", "foo"]], "name": "object with NULL, non-ASCII, and surrogate keys" }']
        ];
    }


    public function testItCanAppendSameName(): void
    {
        $params = new URLSearchParams();
        $params->append('a', 'b');
        self::assertSame('a=b', $params->toString());

        $params->append('a', 'b');
        self::assertSame('a=b&a=b', $params->toString());

        $params->append('a', 'c');
        self::assertSame('a=b&a=b&a=c', $params->toString());
        self::assertSame(['a', 'a', 'a'], [...$params->keys()]);
        self::assertSame(['b', 'b', 'c'], [...$params->values()]);
    }

    public function testItCanAppendEmptyString(): void
    {
        $params = new URLSearchParams();
        $params->append('', '');
        self::assertSame('=', $params->toString());

        $params->append('', '');
        self::assertSame('=&=', $params->toString());
    }

    public function testItCanAppendNull(): void
    {
        $params = new URLSearchParams();
        $params->append(null, null);
        self::assertSame('null=null', $params->toString());

        $params->append(null, null);
        self::assertSame('null=null&null=null', $params->toString());
    }

    public function testItCanAppendMultipleParameters(): void
    {
        $params = new URLSearchParams();
        $params->append('first', 1);
        $params->append('second', 2);
        $params->append('third', '');
        $params->append('first', 10);
        self::assertTrue($params->has('first'));
        self::assertSame('1', $params->get('first'));
        self::assertSame('2', $params->get('second'));
        self::assertSame('', $params->get('third'));

        $params->append('first', 10);
        self::assertSame('1', $params->get('first'));
        self::assertSame(['1', '10', '10'], [...$params->getAll('first')]);
        self::assertSame('1', $params->first('first'));
        self::assertSame('10', $params->last('first'));
        self::assertNull($params->last('fourth'));
        self::assertNull($params->first('fourth'));
    }

    public function testDeleteBasics(): void
    {
        $params = new URLSearchParams('a=b&c=d');
        $params->delete('a');
        self::assertSame($params->toString(), 'c=d');

        $params = new URLSearchParams('a=a&b=b&a=a&c=c');
        $params->delete('a');
        self::assertSame($params->toString(), 'b=b&c=c');

        $params = new URLSearchParams('a=a&=&b=b&c=c');
        $params->delete('');
        self::assertSame($params->toString(), 'a=a&b=b&c=c');

        $params = new URLSearchParams('a=a&null=null&b=b');
        $params->delete(null);
        self::assertSame($params->toString(), 'a=a&b=b');

        $params = new URLSearchParams('a=a&null=null&b=b');
        $params->delete(null);
        self::assertSame($params->toString(), 'a=a&b=b');
    }

    public function testDeleteAppendedMultiple(): void
    {
        $params = new URLSearchParams();
        $params->append('first', 1);
        self::assertTrue($params->has('first'), 'Search params object has name "first"');
        self::assertSame($params->get('first'), '1', 'Search params object has name "first" with value "1"');

        $params->delete('first');
        self::assertCount(0, $params);
        self::assertFalse($params->has('first'), 'Search params object has no "first" name');

        $params->append('first', 1);
        $params->append('first', 10);
        self::assertCount(2, $params);

        $params->delete('first');
        self::assertFalse($params->has('first'), 'Search params object has no "first" name');

        $params = new URLSearchParams('param1&param2');
        $params->delete('param1');
        $params->delete('param2');
        self::assertCount(0, $params);
        self::assertSame($params->toString(), '', 'Search params object has name "first" with value "1"');
    }

    public function testTwoArgumentDelete(): void
    {
        $params = new URLSearchParams();
        $params->append('a', 'b');
        $params->append('a', 'c');
        $params->append('a', 'd');
        $params->deleteValue('a', 'c');
        self::assertSame($params->toString(), 'a=b&a=d');
        self::assertCount(2, $params);
    }

    public function testInvalidDeleteUsageWithoutMoreThanTwoArguments(): void
    {
        $this->expectException(ArgumentCountError::class);

        $params = new URLSearchParams('a=b&a=d&c&e&');
        $params->delete('a', 'b', 'c');
    }

    public function testForEachCheck(): void
    {
        $params = new URLSearchParams('a=1&b=2&c=3');
        $keys = [];
        $values = [];
        $params->each(function ($value, $key) use (&$keys, &$values) {
            $keys[] = $key;
            $values[] = $value;
        });

        self::assertSame(['a', 'b', 'c'], $keys);
        self::assertSame(['1', '2', '3'], $values);
    }

    public function testForOfCheck(): void
    {
        $params = URLSearchParams::fromUri('http://a.b/c?a=1&b=2&c=3&d=4');
        self::assertSame([
            ['a', '1'],
            ['b', '2'],
            ['c', '3'],
            ['d', '4'],
        ], [...$params]);
    }

    public function testGetMethod(): void
    {
        $params = new URLSearchParams('a=b&c=d');
        self::assertSame($params->get('a'), 'b');
        self::assertSame($params->get('c'), 'd');
        self::assertSame($params->get('e'), null);

        $params = new URLSearchParams('a=b&c=d&a=e');
        self::assertSame($params->get('a'), 'b');

        $params = new URLSearchParams('=b&c=d');
        self::assertSame($params->get(''), 'b');

        $params = new URLSearchParams('a=&c=d&a=e');
        self::assertSame($params->get('a'), '');

        $params = new URLSearchParams('first=second&third&&');
        self::assertTrue($params->has('first'), 'Search params object has name "first"');
        self::assertSame($params->get('first'), 'second', 'Search params object has name "first" with value "second"');
        self::assertSame($params->get('third'), '', 'Search params object has name "third" with the empty value.');
        self::assertSame($params->get('fourth'), null, 'Search params object has no "fourth" name and value.');
    }

    public function testGetAllMethod(): void
    {
        $params = new URLSearchParams('a=b&c=d');
        self::assertSame($params->getAll('a'), ['b']);
        self::assertSame($params->getAll('c'), ['d']);
        self::assertSame($params->getAll('e'), []);

        $params = new URLSearchParams('a=b&c=d&a=e');
        self::assertSame($params->getAll('a'), ['b', 'e']);

        $params = new URLSearchParams('=b&c=d');
        self::assertSame($params->getAll(''), ['b']);

        $params = new URLSearchParams('a=&c=d&a=e');
        self::assertSame($params->getAll('a'), ['', 'e']);

        $params = new URLSearchParams('a=1&a=2&a=3&a');
        self::assertTrue($params->has('a'), 'Search params object has name "a"');

        $matches = $params->getAll('a');
        self::assertCount(4, $matches, 'Search params object has values for name "a"');
        self::assertSame($matches, ['1', '2', '3', ''], 'Search params object has expected name "a" values');

        $params->set('a', 'one');
        self::assertSame($params->get('a'), 'one', 'Search params object has name "a" with value "one"');

        $matches = $params->getAll('a');
        self::assertCount(1, $matches, 'Search params object has values for name "a"');
        self::assertSame($matches, ['one'], 'Search params object has expected name "a" values');
    }

    public function testSetMethod(): void
    {
        $params = new URLSearchParams('a=b&c=d');
        $params->set('a', 'B');
        self::assertSame($params->toString(), 'a=B&c=d');

        $params = new URLSearchParams('a=b&c=d&a=e');
        $params->set('a', 'B');
        self::assertSame($params->toString(), 'a=B&c=d');

        $params->set('e', 'f');
        self::assertSame($params->toString(), 'a=B&c=d&e=f');

        $params = new URLSearchParams('a=1&a=2&a=3');
        self::assertTrue($params->has('a'), 'Search params object has name "a"');
        self::assertSame($params->get('a'), '1', 'Search params object has name "a" with value "1"');

        $params->set('first', 4);
        self::assertTrue($params->has('a'), 'Search params object has name "a"');
        self::assertSame($params->get('a'), '1', 'Search params object has name "a" with value "1"');

        $params->set('a', 4);
        self::assertTrue($params->has('a'), 'Search params object has name "a"');
        self::assertSame($params->get('a'), '4', 'Search params object has name "a" with value "4"');
    }

    public function testSerializeSpace(): void
    {
        $params = new URLSearchParams();
        $params->append('a', 'b c');
        self::assertSame($params->toString(), 'a=b+c');

        $params->delete('a');
        $params->append('a b', 'c');
        self::assertSame($params->toString(), 'a+b=c');
    }

    public function testSerializeEmptyValue(): void
    {
        $params = new URLSearchParams();
        $params->append('a', '');
        self::assertSame($params.'', 'a=');

        $params->append('a', '');
        self::assertSame($params.'', 'a=&a=');

        $params->append('', 'b');
        self::assertSame($params.'', 'a=&a=&=b');

        $params->append('', '');
        self::assertSame($params.'', 'a=&a=&=b&=');

        $params->append('', '');
        self::assertSame($params.'', 'a=&a=&=b&=&=');
    }

    public function testSerializeName(): void
    {
        $params = new URLSearchParams();
        $params->append('', 'b');
        self::assertSame($params.'', '=b');
        $params->append('', 'b');
        self::assertSame($params.'', '=b&=b');
    }

    public function testSerializeEmptyNameAndValue(): void
    {
        $params = new URLSearchParams();
        $params->append('', '');
        self::assertSame($params.'', '=');
        $params->append('', '');
        self::assertSame($params.'', '=&=');
    }

    public function testSerializePlusSign(): void
    {
        $params = new URLSearchParams();
        $params->append('a', 'b+c');
        self::assertSame($params.'', 'a=b%2Bc');
        $params->delete('a');
        $params->append('a+b', 'c');
        self::assertSame($params.'', 'a%2Bb=c');
    }

    public function testSerializeEqualSign(): void
    {
        $params = new URLSearchParams();
        $params->append('=', 'a');
        self::assertSame($params.'', '%3D=a');
        $params->append('b', '=');
        self::assertSame($params.'', '%3D=a&b=%3D');
    }

    public function testSerializeAmpersandSign(): void
    {
        $params = new URLSearchParams();
        $params->append('&', 'a');
        self::assertSame($params.'', '%26=a');
        $params->append('b', '&');
        self::assertSame($params.'', '%26=a&b=%26');
    }

    public function testSerializeReservedCharacters(): void
    {
        $params = new URLSearchParams();
        $params->append('a', '*-._');
        self::assertSame($params.'', 'a=*-._');
        $params->delete('a');
        $params->append('*-._', 'c');
        self::assertSame($params.'', '*-._=c');
    }

    public function testSerializePercentage(): void
    {
        $params = new URLSearchParams();
        $params->append('a', 'b%c');
        self::assertSame($params.'', 'a=b%25c');
        $params->delete('a');
        $params->append('a%b', 'c');
        self::assertSame($params.'', 'a%25b=c');

        $params = new URLSearchParams('id=0&value=%');
        self::assertSame($params.'', 'id=0&value=%25');
    }

    public function testSerializeNullByte(): void
    {
        $params = new URLSearchParams();
        $params->append('a', "b\0c");
        self::assertSame($params.'', 'a=b%00c');
        $params->delete('a');
        $params->append("a\0b", 'c');
        self::assertSame($params.'', 'a%00b=c');
    }

    public function testSerializePileOfPoo(): void
    {
        $params = new URLSearchParams();
        $params->append('a', 'b💩c');
        self::assertSame($params.'', 'a=b%F0%9F%92%A9c');
        $params->delete('a');
        $params->append('a💩b', 'c');
        self::assertSame($params.'', 'a%F0%9F%92%A9b=c');
    }

    public function testToStringMethod(): void
    {
        $params = new URLSearchParams('a=b&c=d&&e&&');
        self::assertSame($params->toString(), 'a=b&c=d&e=');
        $params = new URLSearchParams('a = b &a=b&c=d%20');
        self::assertSame($params->toString(), 'a+=+b+&a=b&c=d+');
        // The lone '=' _does_ survive the round trip.
        $params = new URLSearchParams('a=&a=b');
        self::assertSame($params->toString(), 'a=&a=b');

        $params = new URLSearchParams('b=%2sf%2a');
        self::assertSame($params->toString(), 'b=%252sf*');

        $params = new URLSearchParams('b=%2%2af%2a');
        self::assertSame($params->toString(), 'b=%252*f*');

        $params = new URLSearchParams('b=%%2a');
        self::assertSame($params->toString(), 'b=%25*');
    }

    public function testNoNormalizationForCarriageReturnCharacters(): void
    {
        $params = new URLSearchParams();
        $params->append("a\nb", "c\rd");
        $params->append("e\n\rf", "g\r\nh");

        self::assertSame($params->toString(), 'a%0Ab=c%0Dd&e%0A%0Df=g%0D%0Ah');
    }

    #[DataProvider('provideSortingPayload')]
    public function testSorting(string $input, array $output): void
    {
        $params = new URLSearchParams($input);
        $params->sort();
        self::assertSame($output, [...$params]);
    }

    public static function provideSortingPayload(): iterable
    {
        $json = <<<JSON
[
  {
    "input": "z=b&a=b&z=a&a=a",
    "output": [["a", "b"], ["a", "a"], ["z", "b"], ["z", "a"]]
  },
  {
    "input": "\uFFFD=x&\uFFFC&\uFFFD=a",
    "output": [["\uFFFC", ""], ["\uFFFD", "x"], ["\uFFFD", "a"]]
  },
  {
    "input": "ﬃ&🌈",
    "output": [["🌈", ""], ["ﬃ", ""]]
  },
  {
    "input": "z=z&a=a&z=y&a=b&z=x&a=c&z=w&a=d&z=v&a=e&z=u&a=f&z=t&a=g",
    "output": [["a", "a"], ["a", "b"], ["a", "c"], ["a", "d"], ["a", "e"], ["a", "f"], ["a", "g"], ["z", "z"], ["z", "y"], ["z", "x"], ["z", "w"], ["z", "v"], ["z", "u"], ["z", "t"]]
  },
  {
    "input": "bbb&bb&aaa&aa=x&aa=y",
    "output": [["aa", "x"], ["aa", "y"], ["aaa", ""], ["bb", ""], ["bbb", ""]]
  },
  {
    "input": "z=z&=f&=t&=x",
    "output": [["", "f"], ["", "t"], ["", "x"], ["z", "z"]]
  },
  {
    "input": "a🌈&a💩",
    "output": [["a🌈", ""], ["a💩", ""]]
  }
]
JSON;
        yield from json_decode($json, true);
    }

    public function testSizeWithDeletionMethodAndBehaviour(): void
    {
        $params = new URLSearchParams('a=1&b=2&a=3');
        self::assertCount(3, $params);
        self::assertTrue($params->isNotEmpty());
        self::assertFalse($params->isEmpty());
        self::assertSame(2, $params->uniqueKeyCount());

        $params->delete('a');
        self::assertCount(1, $params);
        self::assertSame(1, $params->uniqueKeyCount());
    }

    public function testSizeWithAdditionMethodAndBehaviour(): void
    {
        $params = new URLSearchParams('a=1&b=2&a=3');
        self::assertCount(3, $params);
        self::assertSame(2, $params->uniqueKeyCount());

        $params->append('b', '4');
        self::assertCount(4, $params);
        self::assertSame(2, $params->uniqueKeyCount());
    }

    public function testSizeWithEmptyInstance(): void
    {
        $params = new URLSearchParams();
        self::assertCount(0, $params);
        self::assertFalse($params->isNotEmpty());
        self::assertTrue($params->isEmpty());
        self::assertSame(0, $params->uniqueKeyCount());
    }

    public function testBasicHasMethod(): void
    {
        $params = new URLSearchParams('a=b&c=d');
        self::assertTrue($params->has('a'));
        self::assertTrue($params->has('c'));
        self::assertFalse($params->has('e'));

        $params = new URLSearchParams('a=b&c=d&a=e');
        self::assertTrue($params->has('a'));

        $params = new URLSearchParams('=b&c=d');
        self::assertTrue($params->has(''));

        $params = new URLSearchParams('null=a');
        self::assertTrue($params->has(null));
    }

    public function testHasMethodFollowingADeletion(): void
    {
        $params = new URLSearchParams('a=b&c=d&&');
        $params->append('first', 1);
        $params->append('first', 2);
        self::assertTrue($params->has('a'), 'Search params object has name "a"');
        self::assertTrue($params->has('c'), 'Search params object has name "c"');
        self::assertTrue($params->has('first'), 'Search params object has name "first"');
        self::assertFalse($params->has('d'), 'Search params object has no name "d"');

        $params->delete('first');
        self::assertFalse($params->has('first'), 'Search params object has no name "first"');
    }

    public function testHasMethodSupportsTwoVariables(): void
    {
        $params = new URLSearchParams('a=b&a=d&c&e&');
        self::assertTrue($params->hasValue('a', 'b'));
        self::assertFalse($params->hasValue('a', 'c'));
        self::assertTrue($params->hasValue('a', 'd'));
        self::assertTrue($params->hasValue('e', ''));

        $params->append('first', null);
        self::assertFalse($params->hasValue('first', ''));
        self::assertTrue($params->hasValue('first', 'null'));

        $params->deleteValue('a', 'b');
        self::assertTrue($params->hasValue('a', 'd'));
    }

    public function testInvalidHasUsageWithoutMoreThanTwoArguments(): void
    {
        $this->expectException(ArgumentCountError::class);

        $params = new URLSearchParams('a=b&a=d&c&e&');
        $params->has('a', 'b', 'c');
    }

    public function testJsonEncode(): void
    {
        self::assertSame(
            '"a=1&b=2&c=3&a=4&a=3+d"',
            json_encode(new URLSearchParams('a=1&b=2&c=3&a=4&a=3+d'))
        );
    }

    public function testGetUriComponent(): void
    {
        self::assertSame('', (new URLSearchParams())->getUriComponent());
        self::assertSame('', (new URLSearchParams(''))->getUriComponent());
        self::assertSame('?foo=bar', (new URLSearchParams('foo=bar'))->getUriComponent());
    }

    public function testFromParameters(): void
    {
        $parameters = [
            'filter' => [
                'foo' => [
                    'bar baz',
                    'baz',
                ],
                'bar' => [
                    'bar' => 'foo',
                    'foo' => 'bar',
                ],
            ],
        ];

        $params = URLSearchParams::fromVariable($parameters);
        self::assertCount(4, $params);
        self::assertSame('bar baz', $params->get('filter[foo][0]'));
        self::assertSame('bar', $params->get('filter[bar][foo]'));
    }

    public function testInstantiateWithAssociativeInput(): void
    {
        $interval = new DateInterval('P3MT12M5S');
        $params = URLSearchParams::fromAssociative($interval);
        self::assertSame((new URLSearchParams($interval))->toString(), $params->toString());
        self::assertSame('3', $params->get('m'));
        self::assertSame('12', $params->get('i'));
        self::assertSame('5', $params->get('s'));
        self::assertSame('0', $params->get('y'));
        self::assertSame('0', $params->get('invert'));
        self::assertSame('false', $params->get('days'));
        self::assertNull($params->get('yolo'));
    }

    public function testInstantiateWithAssociativeArray(): void
    {
        $associative = ['y' => 0, 'invert' => 0, 'days' => false, 'm'  => 3, 'i' => 12, 's' => 5, 'yolo' => null];
        $params = URLSearchParams::fromAssociative($associative);
        self::assertSame((new URLSearchParams((object) $associative))->toString(), $params->toString());
        self::assertSame('3', $params->get('m'));
        self::assertSame('12', $params->get('i'));
        self::assertSame('5', $params->get('s'));
        self::assertSame('0', $params->get('y'));
        self::assertSame('0', $params->get('invert'));
        self::assertSame('false', $params->get('days'));
        self::assertSame('null', $params->get('yolo'));
    }

    public function testInstantiateWithPairsFails(): void
    {
        $this->expectException(SyntaxError::class);

        URLSearchParams::fromPairs(['key' => '730d67']); /* @phpstan-ignore-line */
    }

    public function testInstantiateWithPairs(): void
    {
        $pairs = [['a', 'b'], ['a', 'c']];
        $params = URLSearchParams::fromPairs($pairs);
        self::assertSame((new URLSearchParams($pairs))->toString(), $params->toString());
        self::assertSame('b', $params->get('a'));
        self::assertSame(2, $params->size());
        self::assertSame($pairs, [...$params->entries()]);
    }

    public function testInstantiateWithString(): void
    {
        $params = URLSearchParams::new('a=b');
        self::assertSame((new URLSearchParams('a=b'))->toString(), $params->toString());
        self::assertSame('b', $params->get('a'));

        $params = URLSearchParams::new('?a=b');
        self::assertSame((new URLSearchParams('?a=b'))->toString(), $params->toString());
        self::assertSame('a=b', $params->toString());
        self::assertSame('b', $params->get('a'));

        self::assertSame('b', URLSearchParams::new('%3Fa=b')->get('?a'));
    }

    public function testFromParametersRespectURLSpecTypeConversion(): void
    {
        $parameters = new DateInterval('P12MT23H12S');

        self::assertSame(
            URLSearchParams::fromAssociative($parameters)->toString(),
            URLSearchParams::fromVariable($parameters)->toString(),
        );
    }

    /**
     * @see https://github.com/php/php-src/tree/master/ext/standard/tests/http/http_build_query
     */
    #[DataProvider('providesParametersInput')]
    public function testFromParametersWithDifferentInput(object|array $data, string $expected): void
    {
        self::assertSame($expected, URLSearchParams::fromVariable($data)->toString());
    }

    public static function providesParametersInput(): iterable
    {
        yield 'from an object with public properties' => [
            'data' => ['foo' => 'bar', 'baz' => 1, 'test' => "a ' \" ", 'abc', 'float' => 10.42, 'true' => true, 'false' => false],
            'expected' => 'foo=bar&baz=1&test=a+%27+%22+&0=abc&float=10.42&true=true&false=false',
        ];

        yield 'empty parameters' => [
            'data' => [],
            'expected' => '',
        ];

        yield 'encoding of the plus sign and the float number' => [
            'data' => ['x' => 1E+14, 'y' => '1E+14'],
            'expected' => 'x=100000000000000.0&y=1E%2B14',
        ];

        yield 'class public properties' => [
          'data' => new class () {
              public string $public = 'input';
              protected string $protected = 'hello';
              private string $private = 'world'; /* @phpstan-ignore-line */
          },
          'expected' => 'public=input',
        ];

        yield 'empty class' => [
            'data' => new class () {},
            'expected' => '',
        ];

        yield 'just stringable class' => [
            'data' => new class () implements Stringable {
                public function __toString(): string
                {
                    return $this::class;
                }
            },
            'expected' => '',
        ];

        yield 'stringable object' => [
            'data' => ['hello', new class () implements Stringable {
                public function __toString(): string
                {
                    return $this::class;
                }
            }],
            'expected' => '0=hello',
        ];

        yield 'stringable class with public properties' => [
            'data' => new class () implements Stringable {
                public string $public = 'input';
                protected string $protected = 'hello';
                private string $private = 'world'; /* @phpstan-ignore-line */
                public function __toString(): string
                {
                    return $this::class;
                }
            },
            'expected' => 'public=input',
        ];

        $parent = new class () {
            public mixed $public = 'input';
            protected string $protected = 'hello';
            private string $private = 'world'; /* @phpstan-ignore-line */
        };

        $child = new class () {
            public mixed $public = 'input';
            protected string $protected = 'hello';
            private string $private = 'world'; /* @phpstan-ignore-line */
        };

        $parent->public = $child;

        yield 'nested classes' => [
            'data' => $parent,
            'expected' => 'public%5Bpublic%5D=input',
        ];

        yield 'nested arrays' => [
            'data' => [
                20,
                5 => 13,
                '9' => [
                    1 => 'val1',
                    3 => 'val2',
                    'string' => 'string',
                ],
                'name' => 'homepage',
                'page' => 10,
                'sort' => [
                    'desc',
                    'admin' => [
                        'admin1',
                        'admin2' => [
                            'who' => 'admin2',
                            2 => 'test',
                        ],
                    ],
                ],
            ],
            'expected' => '0=20&5=13&9%5B1%5D=val1&9%5B3%5D=val2&9%5Bstring%5D=string&name=homepage&page=10&sort%5B0%5D=desc&sort%5Badmin%5D%5B0%5D=admin1&sort%5Badmin%5D%5Badmin2%5D%5Bwho%5D=admin2&sort%5Badmin%5D%5Badmin2%5D%5B2%5D=test',
        ];

        yield 'test with mathematical expression' => [
            'data' => [
                'name' => 'main page',
                'sort' => 'desc,admin',
                'equation' => '10 + 10 - 5',
            ],
            'expected' => 'name=main+page&sort=desc%2Cadmin&equation=10+%2B+10+-+5',
        ];

        yield 'test with array containing null value' => [
            'data' => [null],
            'expected' => '',
        ];

        yield 'test with resource' => [
            'data' => [STDIN],
            'expected' => '',
        ];

        $recursive = new class () {
            public mixed $public = 'input';
        };
        $recursive->public = $recursive;

        yield 'test object recursion' => [
            'data' => $recursive,
            'expected' => '',
        ];

        $v = 'value';
        $ref = &$v;

        yield 'using reference in array' => [
            'data' => [$ref],
            'expected' => '0=value',
        ];

        yield 'using a traversable' => [
            'data' => new class () implements IteratorAggregate {
                public function getIterator(): Traversable
                {
                    yield from ['foo' => 'bar'];
                }
            },
            'expected' => '',
        ];
    }
}

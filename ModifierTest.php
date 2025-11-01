<?php

/**
 * League.Uri (https://uri.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Uri;

use DOMException;
use GuzzleHttp\Psr7\Utils;
use League\Uri\Components\DataPath;
use League\Uri\Components\FragmentDirectives;
use League\Uri\Components\FragmentDirectives\Directive;
use League\Uri\Components\FragmentDirectives\TextDirective;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use Uri\WhatWg\Url;

use const PHP_QUERY_RFC3986;

#[CoversClass(Modifier::class)]
#[CoversClass(FragmentDirectives::class)]
#[Group('host')]
#[Group('resolution')]
final class ModifierTest extends TestCase
{
    private const BASE_URI = 'http://a/b/c/d;p?q';
    private readonly string $uri;
    private readonly Modifier $modifier;

    protected function setUp(): void
    {
        $this->uri = 'http://www.example.com/path/to/the/sky.php?kingkong=toto&foo=bar%20baz#doc3';
        $this->modifier = Modifier::wrap($this->uri);
    }

    /*****************************
     * QUERY MODIFIER METHOD TESTS
     ****************************/
    #[DataProvider('validMergeQueryProvider')]
    public function testMergeQuery(string $query, string $expected): void
    {
        self::assertSame($expected, $this->modifier->mergeQuery($query)->unwrap()->getQuery());
    }

    public static function validMergeQueryProvider(): array
    {
        return [
            ['toto', 'kingkong=toto&foo=bar%20baz&toto'],
            ['kingkong=ape', 'kingkong=ape&foo=bar%20baz'],
        ];
    }

    #[DataProvider('validMergeQueryPairsProvider')]
    public function testMergeQueryPairs(iterable $pairs, string $expected): void
    {
        self::assertSame($expected, $this->modifier->mergeQueryPairs($pairs)->unwrap()->getQuery());
    }

    public static function validMergeQueryPairsProvider(): array
    {
        return [
            [
                'pairs' => [['toto', null]],
                'expected' => 'kingkong=toto&foo=bar%20baz&toto',
            ],
            [
                'pairs' => [['kingkong', 'ape']],
                'expected' => 'kingkong=ape&foo=bar%20baz',
            ],
        ];
    }

    #[DataProvider('validMergeQueryParametersProvider')]
    public function testMergeQueryParameters(iterable $parameters, string $expected): void
    {
        self::assertSame($expected, $this->modifier->mergeQueryParameters($parameters)->unwrap()->getQuery());
    }

    public static function validMergeQueryParametersProvider(): array
    {
        return [
            [
                'parameters' => ['toto' => null],
                'expected' => 'kingkong=toto&foo=bar%20baz',
            ],
            [
                'parameters' => ['toto' => ''],
                'expected' => 'kingkong=toto&foo=bar%20baz&toto=',
            ],
            [
                'parameters' => ['kingkong' => 'ape'],
                'expected' => 'kingkong=ape&foo=bar%20baz',
            ],
        ];
    }

    #[DataProvider('validAppendQueryProvider')]
    public function testAppendQuery(string $query, string $expected): void
    {
        self::assertSame($expected, $this->modifier->appendQuery($query)->unwrap()->getQuery());
    }

    public static function validAppendQueryProvider(): array
    {
        return [
            ['toto', 'kingkong=toto&foo=bar%20baz&toto'],
            ['kingkong=ape', 'kingkong=toto&foo=bar%20baz&kingkong=ape'],
        ];
    }

    #[DataProvider('validAppendQueryPairsProvider')]
    public function testAppendQueryPairs(iterable $query, string $expected): void
    {
        self::assertSame($expected, $this->modifier->appendQueryPairs($query)->unwrap()->getQuery());
    }

    public static function validAppendQueryPairsProvider(): array
    {
        return [
            [
                [['toto', null]],
                'kingkong=toto&foo=bar%20baz&toto',
            ],
            [
                [['kingkong', 'ape']],
                'kingkong=toto&foo=bar%20baz&kingkong=ape',
            ],
        ];
    }

    #[DataProvider('validAppendQueryParametersProvider')]
    public function testAppendQueryParameters(iterable $query, string $expected): void
    {
        self::assertSame($expected, $this->modifier->appendQueryParameters($query)->unwrap()->getQuery());
    }

    public static function validAppendQueryParametersProvider(): array
    {
        return [
            [
                ['toto' => ''],
                'kingkong=toto&foo=bar%20baz&toto=',
            ],
            [
                ['kingkong' => 'ape'],
                'kingkong=toto&foo=bar%20baz&kingkong=ape',
            ],
        ];
    }

    public function testKsortQuery(): void
    {
        $uri = Http::new('http://example.com/?kingkong=toto&foo=bar%20baz&kingkong=ape');
        self::assertSame('foo=bar%20baz&kingkong=toto&kingkong=ape', Modifier::wrap($uri)->sortQuery()->unwrap()->getQuery());
    }

    #[DataProvider('validWithoutQueryValuesProvider')]
    public function testWithoutQueryValuesProcess(array $input, string $expected): void
    {
        self::assertSame($expected, $this->modifier->removeQueryPairsByKey(...$input)->unwrap()->getQuery());
    }

    public static function validWithoutQueryValuesProvider(): array
    {
        return [
            [['1'], 'kingkong=toto&foo=bar%20baz'],
            [['kingkong'], 'foo=bar%20baz'],
        ];
    }

    #[DataProvider('validWithoutQueryPairByValueProvider')]
    public function testvalidWithoutQueryPairByValue(array $values, string $expected): void
    {
        self::assertSame($expected, $this->modifier->removeQueryPairsByValue(...$values)->unwrap()->getQuery());
    }

    public static function validWithoutQueryPairByValueProvider(): array
    {
        return [
            [['afdsfasd'], 'kingkong=toto&foo=bar%20baz'],
            [['toto'], 'foo=bar%20baz'],
        ];
    }

    #[DataProvider('validWithoutQueryPairByKeyValueProvider')]
    public function testvalidWithoutQueryPairByKeyValue(array $values, string $expected): void
    {
        self::assertSame($expected, $this->modifier->removeQueryPairsByKeyValue(...$values)->unwrap()->getQuery());
    }

    public static function validWithoutQueryPairByKeyValueProvider(): array
    {
        return [
            [['afdsfasd', null], 'kingkong=toto&foo=bar%20baz'],
            [['kingkong', 'tota'], 'kingkong=toto&foo=bar%20baz'],
            [['kingkong', 'toto'], 'foo=bar%20baz'],
        ];
    }

    #[DataProvider('removeParamsProvider')]
    public function testWithoutQueryParams(string $uri, array $input, ?string $expected): void
    {
        self::assertSame($expected, Modifier::wrap($uri)->removeQueryParameters(...$input)->unwrap()->getQuery());
    }

    public static function removeParamsProvider(): array
    {
        return [
            [
                'uri' => 'http://example.com',
                'input' => ['foo'],
                'expected' => null,
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

    #[DataProvider('removeQueryParameterIndicesProvider')]
    public function testWithoutQueryParameterIndices(string $uri, string $expected): void
    {
        self::assertSame($expected, Modifier::wrap($uri)->removeQueryParameterIndices()->unwrap()->getQuery());
    }

    public static function removeQueryParameterIndicesProvider(): array
    {
        return [
            [
                'uri' => 'http://example.com?foo=bar',
                'expected' => 'foo=bar',
            ],
            [
                'uri' => 'http://example.com?foo[0]=bar&foo[1]=baz',
                'expected' => 'foo%5B%5D=bar&foo%5B%5D=baz',
            ],
            [
                'uri' => 'http://example.com?foo[not-remove]=bar&foo[1]=baz',
                'expected' => 'foo%5Bnot-remove%5D=bar&foo%5B%5D=baz',
            ],
        ];
    }

    #[DataProvider('removeEmptyPairsProvider')]
    public function testRemoveEmptyPairs(string $uri, ?string $expected): void
    {
        self::assertSame($expected, Modifier::wrap(Uri::new($uri))->removeEmptyQueryPairs()->toString());
        self::assertSame($expected, Modifier::wrap(Http::new($uri))->removeEmptyQueryPairs()->toString());
    }

    public static function removeEmptyPairsProvider(): iterable
    {
        return [
            'null query component' => [
                'uri' => 'http://example.com',
                'expected' => 'http://example.com',
            ],
            'empty query component' => [
                'uri' => 'http://example.com?',
                'expected' => 'http://example.com',
            ],
            'no empty pair query component' => [
                'uri' => 'http://example.com?foo=bar',
                'expected' => 'http://example.com?foo=bar',
            ],
            'with empty pair as last pair' => [
                'uri' => 'http://example.com?foo=bar&',
                'expected' => 'http://example.com?foo=bar',
            ],
            'with empty pair as first pair' => [
                'uri' => 'http://example.com?&foo=bar',
                'expected' => 'http://example.com?foo=bar',
            ],
            'with empty pair inside the component' => [
                'uri' => 'http://example.com?foo=bar&&&&bar=baz',
                'expected' => 'http://example.com?foo=bar&bar=baz',
            ],
        ];
    }

    public function testEncodeQuery(): void
    {
        self::assertSame(
            $this->modifier->encodeQuery(PHP_QUERY_RFC3986)->toString(),
            $this->modifier->toString()
        );

        self::assertSame(
            $this->modifier->encodeQuery(PHP_QUERY_RFC1738)->unwrap()->getQuery(),
            'kingkong=toto&foo=bar+baz'
        );
    }

    /*****************************
     * HOST MODIFIER METHOD TESTS
     ****************************/
    #[DataProvider('validHostProvider')]
    public function testPrependLabelProcess(string $label, int $key, string $prepend, string $append, string $replace): void
    {
        self::assertSame($prepend, $this->getHost($this->modifier->prependLabel($label)));
    }

    #[DataProvider('validHostProvider')]
    public function testAppendLabelProcess(string $label, int $key, string $prepend, string $append, string $replace): void
    {
        self::assertSame($append, $this->getHost($this->modifier->appendLabel($label)));
    }

    #[DataProvider('validHostProvider')]
    public function testReplaceLabelProcess(string $label, int $key, string $prepend, string $append, string $replace): void
    {
        self::assertSame($replace, $this->getHost($this->modifier->replaceLabel($key, $label)));
    }

    public static function validHostProvider(): array
    {
        return [
            ['toto', 2, 'toto.www.example.com', 'www.example.com.toto', 'toto.example.com'],
            ['123', 1, '123.www.example.com', 'www.example.com.123', 'www.123.com'],
        ];
    }

    public function testItCanSliceHostLabels(): void
    {
        $uri = 'http://www.localhost.co.uk/path/to/the/sky/';

        self::assertSame('http://www.localhost/path/to/the/sky/', Modifier::wrap($uri)->sliceLabels(2, 2)->toString());
    }

    public function testAppendLabelWithIpv4Host(): void
    {
        $uri = Http::new('http://127.0.0.1/foo/bar');

        self::assertSame(
            '127.0.0.1.localhost',
            $this->getHost(Modifier::wrap($uri)->appendLabel('.localhost'))
        );
    }

    public function testAppendLabelThrowsWithOtherIpHost(): void
    {
        $this->expectException(SyntaxError::class);

        Modifier::wrap(Http::new('http://[::1]/foo/bar'))->appendLabel('.localhost');
    }

    public function testPrependLabelWithIpv4Host(): void
    {
        $uri = Http::new('http://127.0.0.1/foo/bar');

        self::assertSame(
            'localhost.127.0.0.1',
            $this->getHost(Modifier::wrap($uri)->prependLabel('localhost.'))
        );
    }

    public function testAppendNulLabel(): void
    {
        $uri = Uri::new('http://127.0.0.1');

        self::assertSame($uri, Modifier::wrap($uri)->appendLabel(null)->unwrap());
    }

    public function testPrependLabelThrowsWithOtherIpHost(): void
    {
        $this->expectException(SyntaxError::class);

        Modifier::wrap(Http::new('http://[::1]/foo/bar'))->prependLabel('.localhost');
    }

    public function testPrependNullLabel(): void
    {
        $uri = Uri::new('http://127.0.0.1');

        self::assertSame($uri, Modifier::wrap($uri)->prependLabel(null)->unwrap());
    }

    public function testHostToAsciiProcess(): void
    {
        $uri = Uri::new('http://مثال.إختبار/where/to/go');

        self::assertSame(
            'http://xn--mgbh0fb.xn--kgbechtv/where/to/go',
            (string)  Modifier::wrap($uri)->hostToAscii()
        );
    }

    public function testWithoutZoneIdentifierProcess(): void
    {
        $uri = Http::new('http://[fe80::1234%25eth0-1]/path/to/the/sky.php');

        self::assertSame(
            'http://[fe80::1234]/path/to/the/sky.php',
            (string) Modifier::wrap($uri)->removeZoneId()
        );
    }

    #[DataProvider('validwithoutLabelProvider')]
    public function testwithoutLabelProcess(array $keys, string $expected): void
    {
        self::assertSame($expected, $this->getHost($this->modifier->removeLabels(...$keys)));
    }

    public static function validwithoutLabelProvider(): array
    {
        return [
            [[1], 'www.com'],
        ];
    }

    public function testRemoveLabels(): void
    {
        self::assertSame('example.com', $this->getHost($this->modifier->removeLabels(2)));
    }

    public function testModifyingTheHostKeepHostUnicode(): void
    {
        $modifier = Modifier::wrap(Utils::uriFor('http://shop.bébé.be'));

        self::assertSame('http://shop.bébé', $modifier->removeLabels(0)->toString());
        self::assertSame('http://www.bébé.be', $modifier->replaceLabel(-1, 'www')->toString());
        self::assertSame('http://new.shop.bébé.be', $modifier->prependLabel('new')->toString());
        self::assertSame('http://shop.bébé.be.new', $modifier->appendLabel('new')->toString());
        self::assertSame('http://shop.bébé.be', $modifier->hostToUnicode()->toString());
        self::assertSame('http://shop.xn--bb-bjab.be', $modifier->hostToAscii()->toString());

        $modifier = Modifier::wrap(Utils::uriFor('http://shop.bebe.be'));

        self::assertSame('http://xn--bb-bjab.bebe.be', $modifier->replaceLabel(-1, 'bébé')->toString());
        self::assertSame('http://xn--bb-bjab.shop.bebe.be', $modifier->prependLabel('bébé')->toString());
        self::assertSame('http://shop.bebe.be.xn--bb-bjab', $modifier->appendLabel('bébé')->toString());
        self::assertSame('http://shop.bebe.be', $modifier->hostToAscii()->toString());
        self::assertSame('http://shop.bebe.be', $modifier->hostToUnicode()->toString());
    }

    public function testAddRootLabel(): void
    {
        self::assertSame('www.example.com.', $this->getHost($this->modifier->addRootLabel()->addRootLabel()));
    }

    public function testRemoveRootLabel(): void
    {
        self::assertSame('www.example.com', $this->getHost($this->modifier->addRootLabel()->removeRootLabel()));
        self::assertSame('www.example.com', $this->getHost($this->modifier->removeRootLabel()));
    }

    public function testItCanBeJsonSerialize(): void
    {
        $uri = Http::new($this->uri);

        self::assertSame(json_encode($uri), json_encode($this->modifier));
    }

    public function testItCanConvertHostToUnicode(): void
    {
        $uriString = 'http://bébé.be';
        $uri = (string) Http::new($uriString);
        $modifier = Modifier::wrap(Utils::uriFor($uri));

        self::assertSame('http://xn--bb-bjab.be', $uri);
        self::assertSame('http://xn--bb-bjab.be', (string) $modifier);
        self::assertSame($uriString, $modifier->hostToUnicode()->toString());
    }

    public function testICanNormalizeIPv4HostToDecimal(): void
    {
        $uri = 'http://0300.0250.0000.0001/path/to/the/sky.php';
        $expected = 'http://192.168.0.1/path/to/the/sky.php';

        self::assertSame($expected, Modifier::wrap($uri)->hostToDecimal()->toString());
    }

    public function testICanNormalizeIPv4HostToOctal(): void
    {
        $uri = 'http://0300.0250.0.1/path/to/the/sky.php';
        $expected = 'http://0300.0250.0000.0001/path/to/the/sky.php';

        self::assertSame($expected, Modifier::wrap($uri)->hostToOctal()->toString());
    }

    public function testICanNormalizeIPv4HostToHexadecimal(): void
    {
        $uri = 'http://0300.0250.0000.0001/path/to/the/sky.php';
        $expected = 'http://0xc0a801/path/to/the/sky.php';

        self::assertSame($expected, Modifier::wrap($uri)->hostToHexadecimal()->toString());
    }

    public function testIpv4NormalizeHostWithPsr7Uri(): void
    {
        $uri = Http::new('http://0/test');
        $newUri = Modifier::wrap($uri)->hostToDecimal()->unwrap();
        self::assertSame('0.0.0.0', $newUri instanceof Url ? $newUri->getAsciiHost() : $newUri->getHost());

        $uri = Http::new('http://11.be/test');
        $unchangedUri = Modifier::wrap($uri)->hostToDecimal()->unwrap();
        self::assertSame($uri, $unchangedUri);
    }

    public function testIpv4NormalizeHostWithLeagueUri(): void
    {
        $uri = Uri::new('http://0/test');
        $newUri = Modifier::wrap($uri)->hostToDecimal()->unwrap();
        self::assertSame('0.0.0.0', $newUri instanceof Url ? $newUri->getAsciiHost() : $newUri->getHost());

        $uri = Http::new('http://11.be/test');
        $unchangedUri = Modifier::wrap($uri)->hostToDecimal()->unwrap();
        self::assertSame($uri, $unchangedUri);
    }

    /*********************
     * PATH MODIFIER TESTS
     *********************/
    #[DataProvider('fileProvider')]
    public function testToBinary(Uri $binary, Uri $ascii): void
    {
        self::assertSame($binary->toString(), Modifier::wrap($ascii)->dataPathToBinary()->toString());
    }

    #[DataProvider('fileProvider')]
    public function testToAscii(Uri $binary, Uri $ascii): void
    {
        self::assertSame($ascii->toString(), Modifier::wrap($binary)->dataPathToAscii()->toString());
    }

    public static function fileProvider(): array
    {
        $rootPath = dirname(__DIR__).'/test_files';

        $textPath = DataPath::new('text/plain;charset=us-ascii,Bonjour%20le%20monde%21');
        $binPath = DataPath::fromFileContents($rootPath.'/red-nose.gif');

        $ascii = Uri::new('data:text/plain;charset=us-ascii,Bonjour%20le%20monde%21');
        $binary = Uri::new('data:'.$textPath->toBinary());

        $pathBin = Uri::fromFileContents($rootPath.'/red-nose.gif');
        $pathAscii = Uri::new('data:'.$binPath->toAscii());

        return [
            [$pathBin, $pathAscii],
            [$binary, $ascii],
        ];
    }

    public function testDataUriWithParameters(): void
    {
        $uri = Uri::new('data:text/plain;charset=us-ascii,Bonjour%20le%20monde!');
        self::assertSame(
            'text/plain;coco=chanel,Bonjour%20le%20monde!',
            Modifier::wrap($uri)->replaceDataUriParameters('coco=chanel')->unwrap()->getPath()
        );
    }

    #[DataProvider('appendSegmentProvider')]
    public function testAppendProcess(string $segment, string $append): void
    {
        self::assertSame($append, $this->modifier->appendSegment($segment)->unwrap()->getPath());
    }

    public static function appendSegmentProvider(): array
    {
        return [
            ['toto', '/path/to/the/sky.php/toto'],
            ['le blanc', '/path/to/the/sky.php/le%20blanc'],
        ];
    }

    #[DataProvider('validAppendSegmentProvider')]
    public function testAppendProcessWithRelativePath(string $uri, string $segment, string $expected): void
    {
        self::assertSame($expected, Modifier::wrap($uri)->appendSegment($segment)->toString());
    }

    public static function validAppendSegmentProvider(): array
    {
        return [
            'uri with trailing slash' => [
                'uri' => 'http://www.example.com/report/',
                'segment' => 'new-segment',
                'expected' => 'http://www.example.com/report/new-segment',
            ],
            'uri with path without trailing slash' => [
                'uri' => 'http://www.example.com/report',
                'segment' => 'new-segment',
                'expected' => 'http://www.example.com/report/new-segment',
            ],
            'uri with absolute path' => [
                'uri' => 'http://www.example.com/',
                'segment' => 'new-segment',
                'expected' => 'http://www.example.com/new-segment',
            ],
            'uri with empty path' => [
                'uri' => 'http://www.example.com',
                'segment' => 'new-segment',
                'expected' => 'http://www.example.com/new-segment',
            ],
        ];
    }

    #[DataProvider('validBasenameProvider')]
    public function testBasename(string $path, string $uri, string $expected): void
    {
        self::assertSame($expected, (string) Modifier::wrap(Uri::new($uri))->replaceBasename($path));
    }

    public static function validBasenameProvider(): array
    {
        return [
            ['baz', 'http://example.com', 'http://example.com/baz'],
            ['baz', 'http://example.com/foo/bar', 'http://example.com/foo/baz'],
            ['baz', 'http://example.com/foo/', 'http://example.com/foo/baz'],
            ['baz', 'http://example.com/foo', 'http://example.com/baz'],
        ];
    }

    public function testBasenameThrowException(): void
    {
        $this->expectException(SyntaxError::class);

        Modifier::wrap(Uri::new('http://example.com'))->replaceBasename('foo/baz');
    }

    #[DataProvider('validDirnameProvider')]
    public function testDirname(string $path, string $uri, string $expected): void
    {
        self::assertSame($expected, (string) Modifier::wrap(Uri::new($uri))->replaceDirname($path));
    }

    public static function validDirnameProvider(): array
    {
        return [
            ['baz', 'http://example.com', 'http://example.com/baz/'],
            ['baz/', 'http://example.com', 'http://example.com/baz/'],
            ['baz', 'http://example.com/foo', 'http://example.com/baz/foo'],
            ['baz', 'http://example.com/foo/yes', 'http://example.com/baz/yes'],
        ];
    }

    #[DataProvider('prependSegmentProvider')]
    public function testPrependProcess(string $uri, string $segment, string $expectedPath): void
    {
        $uri = Uri::new($uri);
        self::assertSame($expectedPath, Modifier::wrap($uri)->prependSegment($segment)->unwrap()->getPath());
    }

    public static function prependSegmentProvider(): array
    {
        return [
            [
                'uri' => 'http://www.example.com/path/to/the/sky.php?kingkong=toto&foo=bar+baz#doc3',
                'segment' => 'toto',
                'expectedPath' => '/toto/path/to/the/sky.php',
            ],
            [
                'uri' => 'http://www.example.com/path/to/the/sky.php?kingkong=toto&foo=bar+baz#doc3',
                'segment' => 'le blanc',
                'expectedPath' => '/le%20blanc/path/to/the/sky.php',
            ],
            [
                'uri' => 'http://example.com/',
                'segment' => 'toto',
                'expectedPath' => '/toto/',
            ],
            [
                'uri' => 'http://example.com',
                'segment' => '/toto',
                'expectedPath' => '/toto/',
            ],
        ];
    }

    #[DataProvider('replaceSegmentProvider')]
    public function testReplaceSegmentProcess(string $segment, int $key, string $append, string $prepend, string $replace): void
    {
        self::assertSame($replace, $this->modifier->replaceSegment($key, $segment)->unwrap()->getPath());
    }

    public static function replaceSegmentProvider(): array
    {
        return [
            ['toto', 2, '/path/to/the/sky.php/toto', '/toto/path/to/the/sky.php', '/path/to/toto/sky.php'],
            ['le blanc', 2, '/path/to/the/sky.php/le%20blanc', '/le%20blanc/path/to/the/sky.php', '/path/to/le%20blanc/sky.php'],
        ];
    }

    #[DataProvider('addBasepathProvider')]
    public function testaddBasepath(string $basepath, string $expected): void
    {
        self::assertSame($expected, $this->modifier->addBasePath($basepath)->unwrap()->getPath());
    }

    public static function addBasepathProvider(): array
    {
        return [
            ['/', '/path/to/the/sky.php'],
            ['', '/path/to/the/sky.php'],
            ['/path/to', '/path/to/the/sky.php'],
            ['/route/to', '/route/to/path/to/the/sky.php'],
        ];
    }

    public function testaddBasepathWithRelativePath(): void
    {
        $uri = Http::new('base/path');
        self::assertSame('/base/path', Modifier::wrap($uri)->addBasePath('/base/path')->unwrap()->getPath());
    }

    #[DataProvider('removeBasePathProvider')]
    public function testRemoveBasePath(string $basepath, string $expected): void
    {
        self::assertSame($expected, $this->modifier->removeBasePath($basepath)->unwrap()->getPath());
    }

    public static function removeBasePathProvider(): array
    {
        return [
            'base path is the leading slash' => ['/', '/path/to/the/sky.php'],
            'base path is the empty string' => ['', '/path/to/the/sky.php'],
            'base path is included in the current path' => ['/path/to', '/the/sky.php'],
            'base path is not included in the current path' => ['/route/to', '/path/to/the/sky.php'],
        ];
    }

    public function testRemoveBasePathWithRelativePath(): void
    {
        $uri = Http::new('base/path');
        self::assertSame('base/path', Modifier::wrap($uri)->removeBasePath('/base/path')->unwrap()->getPath());
    }

    #[DataProvider('validwithoutSegmentProvider')]
    public function testWithoutSegment(array $keys, string $expected): void
    {
        self::assertSame($expected, $this->modifier->removeSegments(...$keys)->unwrap()->getPath());
    }

    public static function validwithoutSegmentProvider(): array
    {
        return [
            [[1], '/path/the/sky.php'],
        ];
    }

    public function testWithoutDotSegmentsProcess(): void
    {
        $uri = Http::new(
            'http://www.example.com/path/../to/the/./sky.php?kingkong=toto&foo=bar+baz#doc3'
        );
        self::assertSame('/to/the/sky.php', Modifier::wrap($uri)->removeDotSegments()->unwrap()->getPath());
    }

    public function testWithoutEmptySegmentsProcess(): void
    {
        $uri = Http::new(
            'http://www.example.com/path///to/the//sky.php?kingkong=toto&foo=bar+baz#doc3'
        );
        self::assertSame('/path/to/the/sky.php', Modifier::wrap($uri)->removeEmptySegments()->unwrap()->getPath());
    }

    public function testWithoutTrailingSlashProcess(): void
    {
        $uri = Http::new('http://www.example.com/');
        self::assertSame('', Modifier::wrap($uri)->removeTrailingSlash()->unwrap()->getPath());
    }

    #[DataProvider('validExtensionProvider')]
    public function testExtensionProcess(string $extension, string $expected): void
    {
        self::assertSame($expected, $this->modifier->replaceExtension($extension)->unwrap()->getPath());
    }

    public static function validExtensionProvider(): array
    {
        return [
            ['csv', '/path/to/the/sky.csv'],
            ['', '/path/to/the/sky'],
        ];
    }

    public function testWithTrailingSlashProcess(): void
    {
        self::assertSame('/path/to/the/sky.php/', $this->modifier->addTrailingSlash()->unwrap()->getPath());
    }

    public function testWithoutLeadingSlashProcess(): void
    {
        $uri = Http::new('/foo/bar?q=b#h');

        self::assertSame('foo/bar?q=b#h', (string) Modifier::wrap($uri)->removeLeadingSlash());
    }

    public function testWithLeadingSlashProcess(): void
    {
        $uri = Http::new('foo/bar?q=b#h');

        self::assertSame('/foo/bar?q=b#h', (string) Modifier::wrap($uri)->addLeadingSlash());
    }

    public function testReplaceSegmentConstructorFailed2(): void
    {
        $this->expectException(SyntaxError::class);

        $this->modifier->replaceSegment(2, "whyno\0t");
    }

    public function testExtensionProcessFailed(): void
    {
        $this->expectException(SyntaxError::class);

        $this->modifier->replaceExtension('to/to');
    }

    public static function providesInvalidMethodNames(): iterable
    {
        yield 'unknown method' => ['method' => 'unknownMethod'];
        yield 'case sensitive method' => ['method' => 'rePLAceExtenSIOn'];
    }

    public function testItCanSlicePathSegments(): void
    {
        $uri = 'http://www.localhost.com/path/to/the/sky/';

        self::assertSame('http://www.localhost.com/the/sky/', Modifier::wrap($uri)->sliceSegments(2, 2)->toString());
    }

    #[DataProvider('ipv6NormalizationUriProvider')]
    public function testItCanExpandOrCompressTheHost(
        string $inputUri,
        string $compressedUri,
        string $expandedUri,
    ): void {
        $uri = Modifier::wrap(Http::new($inputUri));

        self::assertSame($compressedUri, $uri->hostToIpv6Compressed()->toString());
        self::assertSame($expandedUri, $uri->hostToIpv6Expanded()->toString());
    }

    public static function ipv6NormalizationUriProvider(): iterable
    {
        yield 'no change happen with a non IP host' => [
            'inputUri' => 'https://example.com/foo/bar',
            'compressedUri' => 'https://example.com/foo/bar',
            'expandedUri' => 'https://example.com/foo/bar',
        ];

        yield 'no change happen with a IPv4 host' => [
            'inputUri' => 'https://127.0.0.1/foo/bar',
            'compressedUri' => 'https://127.0.0.1/foo/bar',
            'expandedUri' => 'https://127.0.0.1/foo/bar',
        ];

        yield 'IPv6 gets expanded if needed' => [
            'inputUri' => 'https://[fe80::a%25en1]/foo/bar',
            'compressedUri' => 'https://[fe80::a%25en1]/foo/bar',
            'expandedUri' => 'https://[fe80:0000:0000:0000:0000:0000:0000:000a%25en1]/foo/bar',
        ];

        yield 'IPv6 gets compressed if needed' => [
            'inputUri' => 'https://[0000:0000:0000:0000:0000:0000:0000:0001]/foo/bar',
            'compressedUri' => 'https://[::1]/foo/bar',
            'expandedUri' => 'https://[0000:0000:0000:0000:0000:0000:0000:0001]/foo/bar',
        ];
    }

    #[Test]
    public function it_will_remove_empty_pairs_fix_issue_133(): void
    {
        $removeEmptyPairs = fn (string $str): ?string => Modifier::wrap($str)
            ->removeEmptyQueryPairs()
            ->unwrap()
            ->getQuery();

        self::assertNull($removeEmptyPairs('https://a.b/c?d='));
        self::assertNull($removeEmptyPairs('https://a.b/c?=d'));
        self::assertNull($removeEmptyPairs('https://a.b/c?='));
    }

    #[Test]
    #[DataProvider('normalizedHostProvider')]
    public function it_will_convert_uri_host_following_whatwg_rules(?string $expectedHost, UriInterface|Psr7UriInterface|string $uri): void
    {
        $newUri = Modifier::wrap($uri)->normalizeHost()->unwrap();

        self::assertSame($expectedHost, $newUri instanceof Url ? $newUri->getAsciiHost() : $newUri->getHost());
    }

    public static function normalizedHostProvider(): iterable
    {
        yield 'null host with league uri interface' => [
            'expectedHost' => null,
            'uri' => Uri::new('mailto:foo@example.com'),
        ];

        yield 'empty host with psr7 uri interface' => [
            'expectedHost' => '',
            'uri' => Http::new('/uri/without/host'),
        ];

        yield 'non decimal IPv4 with psr7 uri interface' => [
            'expectedHost' => '192.168.2.13',
            'uri' => Http::new('https://0:0@0xc0a8020d/0?0#0'),
        ];

        yield 'non decimal IPv4 with league uri interface' => [
            'expectedHost' => '192.168.2.13',
            'uri' => Uri::new('https://0:0@0xc0a8020d/0?0#0'),
        ];

        yield 'non decimal IPv4 with string' => [
            'expectedHost' => '192.168.2.13',
            'uri' => 'https://0:0@0xc0a8020d/0?0#0',
        ];

        yield 'unicode host with string' => [
            'expectedHost' => 'xn--bb-bjab.be',
            'uri' => 'https://bébé.be/0?0#0',
        ];

        yield 'unicode host with league uri interface' => [
            'expectedHost' => 'xn--bb-bjab.be',
            'uri' => Uri::new('https://bébé.be/0?0#0'),
        ];

        yield 'unicode host with uri interface' => [
            'expectedHost' => 'xn--bb-bjab.be',
            'uri' => Http::new('https://bébé.be/0?0#0'),
        ];

        yield 'IPv6 host with PSR7 uri interface' => [
            'expectedHost' => '[::1]',
            'uri' => Http::new('https://[0000:0000:0000:0000:0000:0000:0000:0001]/0?0#0'),
        ];

        yield 'IPv6 host with league uri interface' => [
            'expectedHost' => '[::1]',
            'uri' => Uri::new('https://[0000:0000:0000:0000:0000:0000:0000:0001]/0?0#0'),
        ];

        yield 'IPv6 host with string uri' => [
            'expectedHost' => '[::1]',
            'uri' => 'https://[0000:0000:0000:0000:0000:0000:0000:0001]/0?0#0',
        ];
    }

    #[Test]
    #[DataProvider('providesUriToDisplay')]
    public function it_will_allow_direct_string_conversion(
        string $uri,
        string $expectedString,
        string $expectedDisplayString
    ): void {
        self::assertSame($expectedString, Modifier::wrap($uri)->toString());
        self::assertSame($expectedDisplayString, Modifier::wrap($uri)->toDisplayString());
    }

    public static function providesUriToDisplay(): iterable
    {
        yield 'uri is unchanged' => [
            'uri' => 'https://127.0.0.1/foo/bar',
            'expectedString' => 'https://127.0.0.1/foo/bar',
            'expectedDisplayString' => 'https://127.0.0.1/foo/bar',
        ];

        yield 'idn host are changed' => [
            'uri' => 'http://bébé.be',
            'expectedString' => 'http://xn--bb-bjab.be',
            'expectedDisplayString' => 'http://bébé.be',
        ];

        yield 'other components are changed' => [
            'uri' => 'http://bébé.be:80?q=toto%20le%20h%C3%A9ros',
            'expectedString' => 'http://xn--bb-bjab.be?q=toto%20le%20h%C3%A9ros',
            'expectedDisplayString' => 'http://bébé.be?q=toto le héros',
        ];
    }

    private function getHost(Modifier $modifier): ?string
    {
        $uri = $modifier->unwrap();

        return $uri instanceof Url ? $uri->getAsciiHost() : $uri->getHost();
    }

    #[DataProvider('providesDirectivesToAppend')]
    public function test_it_can_append_the_fragment(
        string $uri,
        Directive|Stringable|string $directive,
        string $expectedFragment
    ): void {
        self::assertSame(
            $expectedFragment,
            Modifier::wrap($uri)->appendFragmentDirectives($directive)->unwrap()->getFragment()
        );
    }

    public static function providesDirectivesToAppend(): iterable
    {
        yield 'add new string directive on null fragment' => [
            'uri' => 'http://host/path',
            'directive' => 'foo=bar',
            'expectedFragment' => ':~:foo=bar',
        ];

        yield 'add a directive instance on null fragment' => [
            'uri' => 'http://host/path',
            'directive' => new TextDirective(start: 'start', end: "en'd"),
            'expectedFragment' => ":~:text=start,en'd",
        ];

        yield 'append a directive on existing fragment directive' => [
            'uri' => 'http://host/path#:~:unknownDirective',
            'directive' => new TextDirective(start: 'start', end: "en'd"),
            'expectedFragment' => ":~:unknownDirective&text=start,en'd",
        ];
    }

    #[DataProvider('providesDirectivesToPrepend')]
    public function test_it_can_prepend_the_fragment(
        string $uri,
        Directive|Stringable|string $directive,
        string $expectedFragment
    ): void {
        self::assertSame(
            $expectedFragment,
            Modifier::wrap($uri)->prependFragmentDirectives($directive)->unwrap()->getFragment()
        );
    }

    public static function providesDirectivesToPrepend(): iterable
    {
        yield 'add new string directive on null fragment' => [
            'uri' => 'http://host/path',
            'directive' => 'foo=bar',
            'expectedFragment' => ':~:foo=bar',
        ];

        yield 'add a directive instance on null fragment' => [
            'uri' => 'http://host/path',
            'directive' => new TextDirective(start: 'start', end: "en'd"),
            'expectedFragment' => ":~:text=start,en'd",
        ];

        yield 'append a directive on existing fragment directive' => [
            'uri' => 'http://host/path#:~:unknownDirective',
            'directive' => new TextDirective(start: 'start', end: "en'd"),
            'expectedFragment' => ":~:text=start,en'd&unknownDirective",
        ];
    }

    #[DataProvider('resolveProvider')]
    public function testCreateResolve(string $baseUri, string $uri, string $expected): void
    {
        self::assertSame($expected, Modifier::wrap(Utils::uriFor($baseUri))->resolve($uri)->toString());
    }

    public static function resolveProvider(): array
    {
        return [
            'base uri'                => [self::BASE_URI, '',              self::BASE_URI],
            'scheme'                  => [self::BASE_URI, 'http://d/e/f',  'http://d/e/f'],
            'path 1'                  => [self::BASE_URI, 'g',             'http://a/b/c/g'],
            'path 2'                  => [self::BASE_URI, './g',           'http://a/b/c/g'],
            'path 3'                  => [self::BASE_URI, 'g/',            'http://a/b/c/g/'],
            'path 4'                  => [self::BASE_URI, '/g',            'http://a/g'],
            'authority'               => [self::BASE_URI, '//g',           'http://g'],
            'query'                   => [self::BASE_URI, '?y',            'http://a/b/c/d;p?y'],
            'path + query'            => [self::BASE_URI, 'g?y',           'http://a/b/c/g?y'],
            'fragment'                => [self::BASE_URI, '#s',            'http://a/b/c/d;p?q#s'],
            'path + fragment'         => [self::BASE_URI, 'g#s',           'http://a/b/c/g#s'],
            'path + query + fragment' => [self::BASE_URI, 'g?y#s',         'http://a/b/c/g?y#s'],
            'single dot 1'            => [self::BASE_URI, '.',             'http://a/b/c/'],
            'single dot 2'            => [self::BASE_URI, './',            'http://a/b/c/'],
            'single dot 3'            => [self::BASE_URI, './g/.',         'http://a/b/c/g/'],
            'single dot 4'            => [self::BASE_URI, 'g/./h',         'http://a/b/c/g/h'],
            'double dot 1'            => [self::BASE_URI, '..',            'http://a/b/'],
            'double dot 2'            => [self::BASE_URI, '../',           'http://a/b/'],
            'double dot 3'            => [self::BASE_URI, '../g',          'http://a/b/g'],
            'double dot 4'            => [self::BASE_URI, '../..',         'http://a/'],
            'double dot 5'            => [self::BASE_URI, '../../',        'http://a/'],
            'double dot 6'            => [self::BASE_URI, '../../g',       'http://a/g'],
            'double dot 7'            => [self::BASE_URI, '../../../g',    'http://a/g'],
            'double dot 8'            => [self::BASE_URI, '../../../../g', 'http://a/g'],
            'double dot 9'            => [self::BASE_URI, 'g/../h' ,       'http://a/b/c/h'],
            'mulitple slashes'        => [self::BASE_URI, 'foo////g',      'http://a/b/c/foo////g'],
            'complex path 1'          => [self::BASE_URI, ';x',            'http://a/b/c/;x'],
            'complex path 2'          => [self::BASE_URI, 'g;x',           'http://a/b/c/g;x'],
            'complex path 3'          => [self::BASE_URI, 'g;x?y#s',       'http://a/b/c/g;x?y#s'],
            'complex path 4'          => [self::BASE_URI, 'g;x=1/./y',     'http://a/b/c/g;x=1/y'],
            'complex path 5'          => [self::BASE_URI, 'g;x=1/../y',    'http://a/b/c/y'],
            'dot segments presence 1' => [self::BASE_URI, '/./g',          'http://a/g'],
            'dot segments presence 2' => [self::BASE_URI, '/../g',         'http://a/g'],
            'dot segments presence 3' => [self::BASE_URI, 'g.',            'http://a/b/c/g.'],
            'dot segments presence 4' => [self::BASE_URI, '.g',            'http://a/b/c/.g'],
            'dot segments presence 5' => [self::BASE_URI, 'g..',           'http://a/b/c/g..'],
            'dot segments presence 6' => [self::BASE_URI, '..g',           'http://a/b/c/..g'],
            'origin uri without path' => ['http://h:b@a', 'b/../y',        'http://h:b@a/y'],
            'not same origin'         => [self::BASE_URI, 'ftp://a/b/c/d', 'ftp://a/b/c/d'],
        ];
    }

    #[Test]
    #[DataProvider('providesUriToMarkdown')]
    public function it_will_generate_the_markdown_code_for_the_instance(string $uri, ?string $content, string $expected): void
    {
        self::assertSame($expected, Modifier::wrap($uri)->toMarkdownAnchor($content));
    }

    public static function providesUriToMarkdown(): iterable
    {
        yield 'empty string' => [
            'uri' => '',
            'content' => '',
            'expected' => '[]()',
        ];

        yield 'URI with a specific content' => [
            'uri' => 'http://example.com/foo/bar',
            'content' => 'this is a link',
            'expected' => '[this is a link](http://example.com/foo/bar)',
        ];

        yield 'URI without content' => [
            'uri' => 'http://Bébé.be',
            'content' => null,
            'expected' => '[http://bébé.be](http://xn--bb-bjab.be)',
        ];
    }

    #[Test]
    #[DataProvider('providesUriToAnchorTagHTML')]
    public function it_will_generate_the_html_anchor_tag_code_for_the_instance(string $uri, ?string $content, array $parameters, string $expected): void
    {
        self::assertSame($expected, Modifier::wrap($uri)->toHtmlAnchor($content, $parameters));
    }

    public static function providesUriToAnchorTagHTML(): iterable
    {
        yield 'empty string' => [
            'uri' => '',
            'content' => '',
            'parameters' => [],
            'expected' => '<a href=""></a>',
        ];

        yield 'URI with a specific content' => [
            'uri' => 'http://example.com/foo/bar',
            'content' => 'this is a link',
            'parameters' => [],
            'expected' => '<a href="http://example.com/foo/bar">this is a link</a>',
        ];

        yield 'URI without content' => [
            'uri' => 'http://Bébé.be',
            'content' => null,
            'parameters' => [],
            'expected' => '<a href="http://xn--bb-bjab.be">http://bébé.be</a>',
        ];

        yield 'URI without content and with class' => [
            'uri' => 'http://Bébé.be',
            'content' => null,
            'parameters' => [
                'class' => ['foo', 'bar'],
                'target' => null,
            ],
            'expected' => '<a href="http://xn--bb-bjab.be" class="foo bar">http://bébé.be</a>',
        ];

        yield 'URI without content and with target' => [
            'uri' => 'http://Bébé.be',
            'content' => null,
            'parameters' => [
                'class' => null,
                'target' => '_blank',
            ],
            'expected' => '<a href="http://xn--bb-bjab.be" target="_blank">http://bébé.be</a>',
        ];

        yield 'URI without content, with target and class' => [
            'uri' => 'http://Bébé.be',
            'content' => null,
            'parameters' => [
                'class' => 'foo bar',
                'target' => '_blank',
            ],
            'expected' => '<a href="http://xn--bb-bjab.be" class="foo bar" target="_blank">http://bébé.be</a>',
        ];
    }

    #[Test]
    public function it_will_fail_to_generate_an_anchor_tag_html_for_the_instance(): void
    {
        $this->expectException(DOMException::class);

        Modifier::wrap('https://example.com')->toHtmlAnchor(attributes: ["bébé\r\n" => 'yes']);
    }
}

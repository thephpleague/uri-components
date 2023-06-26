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
use League\Uri\Exceptions\OffsetOutOfBounds;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Http;
use League\Uri\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use function iterator_to_array;

/**
 * @group path
 * @group hierarchicalpath
 * @coversDefaultClass \League\Uri\Components\HierarchicalPath
 */
final class HierarchicalPathTest extends TestCase
{
    public function testIterator(): void
    {
        $path = HierarchicalPath::new('/5.0/components/path');

        self::assertEquals(['5.0', 'components', 'path'], iterator_to_array($path));
    }

    /**
     * @dataProvider validPathProvider
     */
    public function testValidPath(string $raw, string $expected): void
    {
        self::assertSame($expected, (string) HierarchicalPath::new($raw));
    }

    public static function validPathProvider(): array
    {
        $unreserved = 'a-zA-Z0-9.-_~!$&\'()*+,;=:@';

        return [
            'empty' => ['', ''],
            'root path' => ['/', '/'],
            'absolute path' => ['/path/to/my/file.csv', '/path/to/my/file.csv'],
            'relative path' => ['you', 'you'],
            'relative path with ending slash' => ['foo/bar/', 'foo/bar/'],
            'path with a space' => ['/shop/rev iew/', '/shop/rev%20iew/'],
            'path with an encoded char in lowercase' => ['/master/toto/a%c2%b1b', '/master/toto/a%C2%B1b'],
            'path with an encoded char in uppercase' => ['/master/toto/%7Eetc', '/master/toto/%7Eetc'],
            'path with character to encode' => ['/foo^bar', '/foo%5Ebar'],
            'path with a reserved char encoded' => ['%2Ffoo^bar', '%2Ffoo%5Ebar'],
            'Percent encode spaces' => ['/pa th', '/pa%20th'],
            'Percent encode multibyte' => ['/€', '/%E2%82%AC'],
            "Don't encode something that's already encoded" => ['/pa%20th', '/pa%20th'],
            'Percent encode invalid percent encodings' => ['/pa%2-th', '/pa%252-th'],
            "Don't encode path segments" => ['/pa/th//two', '/pa/th//two'],
            "Don't encode unreserved chars or sub-delimiters" => ["/$unreserved", "/$unreserved"],
            'Encoded unreserved chars are not decoded' => ['/p%61th', '/p%61th'],
        ];
    }

    /**
     * @dataProvider isAbsoluteProvider
     */
    public function testIsAbsolute(string $raw, bool $expected): void
    {
        $path = HierarchicalPath::new($raw);

        self::assertSame($expected, $path->isAbsolute());
    }

    public static function isAbsoluteProvider(): array
    {
        return [
            ['', false],
            ['/', true],
            ['../..', false],
            ['/a/b/c', true],
        ];
    }

    /**
     * @dataProvider getProvider
     */
    public function testget(string $raw, int $key, ?string $expected): void
    {
        self::assertSame($expected, HierarchicalPath::new($raw)->get($key));
    }

    public static function getProvider(): array
    {
        return [
            ['/shop/rev iew/', 1, 'rev iew'],
            ['/shop/rev%20iew/', 1, 'rev iew'],
            ['/shop/rev iew/', -2, 'rev iew'],
            ['/shop/rev%20iew/', -2, 'rev iew'],
            ['/shop/rev%20iew/', 28, null],
        ];
    }

    /**
     * Test Removing Dot Segment.
     *
     * @dataProvider normalizeProvider
     */
    public function testWithoutDotSegments(string $path, string $expected): void
    {
        self::assertSame($expected, (string) HierarchicalPath::new($path)->withoutDotSegments());
    }

    /**
     * Provides different segment to be normalized.
     */
    public static function normalizeProvider(): array
    {
        return [
            ['/a/b/c/./../../g', '/a/g'],
            ['mid/content=5/../6', 'mid/6'],
            ['a/b/c', 'a/b/c'],
            ['a/b/c/.', 'a/b/c/'],
            ['/a/b/c', '/a/b/c'],
        ];
    }

    /**
     * @dataProvider withLeadingSlashProvider
     */
    public function testWithLeadingSlash(string $path, string $expected): void
    {
        self::assertSame($expected, (string) HierarchicalPath::new($path)->withLeadingSlash());
    }

    public static function withLeadingSlashProvider(): array
    {
        return [
            'relative path without leading slash' => ['toto', '/toto'],
            'absolute path' => ['/toto', '/toto'],
            'root path' => ['/', '/'],
            'empty path' => ['', '/'],
            'relative path with ending slash' => ['toto/', '/toto/'],
            'absolute path with ending slash' => ['/toto/', '/toto/'],
        ];
    }

    /**
     * @dataProvider withoutLeadingSlashProvider
     */
    public function testWithoutLeadingSlash(string $path, string $expected): void
    {
        self::assertSame($expected, (string) HierarchicalPath::new($path)->withoutLeadingSlash());
    }

    public static function withoutLeadingSlashProvider(): array
    {
        return [
            'relative path without ending slash' => ['toto', 'toto'],
            'absolute path without ending slash' => ['/toto', 'toto'],
            'root path' => ['/', ''],
            'empty path' => ['', ''],
            'absolute path with ending slash' => ['/toto/', 'toto/'],
        ];
    }

    /**
     * @dataProvider createFromRelativeSegmentsValid
     */
    public function testCreateRelativeFromSegments(iterable $input, string $expected): void
    {
        self::assertSame($expected, (string) HierarchicalPath::fromRelative(...$input));
    }

    public static function createFromRelativeSegmentsValid(): array
    {
        return [
            'array (1)' => [['www', 'example', 'com'], 'www/example/com'],
            'ending delimiter' => [['foo/bar/baz', ''], 'foo/bar/baz/'],
            'use reserved characters ?' => [['all', 'i%3fs', 'good'], 'all/i%3Fs/good'],
            'enforce path status (1)' => [['', 'toto', 'yeah', ''], 'toto/yeah/'],
            'enforce path status (3)' => [['', '', 'toto', 'yeah', ''], 'toto/yeah/'],
        ];
    }

    /**
     * @dataProvider createFromAbsoluteSegmentsValid
     */
    public function testCreateAbsoluteFromSegments(iterable $input, string $expected): void
    {
        self::assertSame($expected, (string) HierarchicalPath::fromAbsolute(...$input));
    }

    public static function createFromAbsoluteSegmentsValid(): array
    {
        return [
            'array (2)' => [['www', 'example', 'com'], '/www/example/com'],
            'iterator' => [new ArrayIterator(['www', 'example', 'com']), '/www/example/com'],
            'Path object' => [HierarchicalPath::new('/foo/bar/baz'), '/foo/bar/baz'],
            'arbitrary cut 1' => [['foo', 'bar', 'baz'], '/foo/bar/baz'],
            'arbitrary cut 2' => [['foo/bar', 'baz'], '/foo/bar/baz'],
            'arbitrary cut 3' => [['foo/bar/baz'], '/foo/bar/baz'],
            'use reserved characters #' => [['all', 'i%23s', 'good'], '/all/i%23s/good'],
            'enforce path status (2)' => [['', 'toto', 'yeah', ''], '/toto/yeah/'],
            'enforce path status (4)' => [['', '', 'toto', 'yeah', ''], '//toto/yeah/'],
        ];
    }

    /**
     * @dataProvider prependData
     */
    public function testPrepend(string $source, string $prepend, string $res): void
    {
        self::assertSame($res, (string) HierarchicalPath::new($source)->prepend($prepend));
    }

    public static function prependData(): array
    {
        return [
            ['/test/query.php', '/master',  '/master/test/query.php'],
            ['/test/query.php', '/master/', '/master/test/query.php'],
            ['/test/query.php', '',         '/test/query.php'],
            ['/test/query.php', '/',        '/test/query.php'],
            ['test',            '/',        '/test'],
            ['/',               'test',     'test/'],
        ];
    }

    /**
     * @dataProvider appendData
     */
    public function testAppend(string $source, string $append, string $res): void
    {
        self::assertSame($res, (string) HierarchicalPath::new($source)->append($append));
    }

    public static function appendData(): array
    {
        return [
            ['/test/', '/master/', '/test/master/'],
            ['/test/', '/master',  '/test/master'],
            ['/test',  'master',   '/test/master'],
            ['test',   'master',   'test/master'],
            ['test',   '/master',  'test/master'],
            ['test',   'master/',  'test/master/'],
            ['test',   '/',        'test/'],
            ['/',      'test',     '/test'],
        ];
    }

    public function testWithSegmentUseAppend(): void
    {
        $path = HierarchicalPath::new('foo/bar');

        self::assertEquals($path->withSegment(2, 'baz'), $path->append('baz'));
    }


    /**
     * @dataProvider withoutEmptySegmentsProvider
     */
    public function testWithoutEmptySegments(string $path, string $expected): void
    {
        self::assertSame($expected, (string) HierarchicalPath::new($path)->withoutEmptySegments());
    }

    public static function withoutEmptySegmentsProvider(): array
    {
        return [
            ['/a/b/c', '/a/b/c'],
            ['//a//b//c', '/a/b/c'],
            ['a//b/c//', 'a/b/c/'],
            ['/a/b/c//', '/a/b/c/'],
        ];
    }

    /**
     * @dataProvider replaceValid
     */
    public function testReplace(string $raw, string $input, int $offset, string $expected): void
    {
        self::assertSame($expected, (string) HierarchicalPath::new($raw)->withSegment($offset, $input));
    }

    public static function replaceValid(): array
    {
        return [
            ['/path/to/the/sky', 'shop', 0, '/shop/to/the/sky'],
            ['', 'shoki', 0, 'shoki'],
            ['', 'shoki/', 0, 'shoki/'],
            ['', '/shoki/', 0, '/shoki/'],
            ['/path/to/paradise', 'path', 0, '/path/to/paradise'],
            ['/path/to/paradise', 'path', -1, '/path/to/path'],
            ['/path/to/paradise', 'path', -4, 'path/path/to/paradise'],
            ['/path/to/paradise', 'path', -3, '/path/to/paradise'],
            ['/foo', 'bar', -1, '/bar'],
            ['foo', 'bar', -1, 'bar'],
        ];
    }

    public function testWithSegmentThrowsException(): void
    {
        $this->expectException(OffsetOutOfBounds::class);

        HierarchicalPath::new('/test/')->withSegment(23, 'bar');
    }

    /**
     * Test AbstractSegment::without.
     *
     * @dataProvider withoutProvider
     *
     * @param int[] $without
     */
    public function testWithout(string $origin, array $without, string $result): void
    {
        self::assertSame($result, (string) HierarchicalPath::new($origin)->withoutSegment(...$without));
    }

    public static function withoutProvider(): array
    {
        return [
            ['/master/test/query.php', [2], '/master/test'],
            ['/master/test/query.php', [-1], '/master/test'],
            ['/toto/le/heros/masson', [0], '/le/heros/masson'],
            ['/toto', [-1], '/'],
            ['toto/le/heros/masson', [2, 3], 'toto/le'],
        ];
    }

    public function testWithoutSegmentThrowsException(): void
    {
        $this->expectException(OffsetOutOfBounds::class);

        HierarchicalPath::new('/test/')->withoutSegment(23);
    }

    public function testWithoutSegmentVariadicArgument(): void
    {
        $path = HierarchicalPath::new('www/example/com');

        self::assertSame($path, $path->withoutSegment());
    }

    public function testKeys(): void
    {
        $path = HierarchicalPath::new('/bar/3/troll/3');

        self::assertCount(0, $path->keys('foo'));
        self::assertSame([0], $path->keys('bar'));
        self::assertCount(2, $path->keys('3'));
        self::assertSame([1, 3], $path->keys('3'));
        self::assertSame([0, 1, 2, 3], $path->keys());
    }

    public function testSegments(): void
    {
        $path = HierarchicalPath::new('/bar/3/troll/3');

        self::assertSame(['bar', '3', 'troll', '3'], $path->segments());
        self::assertSame([''], HierarchicalPath::new()->segments());
        self::assertSame([''], HierarchicalPath::new('/')->segments());
    }

    /**
     * @dataProvider arrayProvider
     */
    public function testCountable(string $input, array $gets, int $nbSegment): void
    {
        $path = HierarchicalPath::new($input);

        self::assertCount($nbSegment, $path);
    }

    public static function arrayProvider(): array
    {
        return [
            ['/toto/le/heros/masson', ['toto', 'le', 'heros', 'masson'], 4],
            ['toto/le/heros/masson', ['toto', 'le', 'heros', 'masson'], 4],
            ['/toto/le/heros/masson/', ['toto', 'le', 'heros', 'masson', ''], 5],
        ];
    }

    public function testGetBasemane(): void
    {
        $path = HierarchicalPath::new('/path/to/my/file.txt');

        self::assertSame('file.txt', $path->getBasename());
    }

    public function testGetBasemaneWithEmptyBasename(): void
    {
        $path = HierarchicalPath::new('/path/to/my/');

        self::assertEmpty($path->getBasename());
    }

    /**
     * @dataProvider dirnameProvider
     */
    public function testGetDirmane(string $path, string $dirname): void
    {
        self::assertSame($dirname, HierarchicalPath::new($path)->getDirname());
    }

    public static function dirnameProvider(): array
    {
        return [
            ['/path/to/my/file.txt', '/path/to/my'],
            ['/path/to/my/file/', '/path/to/my'],
            ['/path/to/my\\file/', '/path/to'],
            ['.', '.'],
            ['/path/to/my//file/', '/path/to/my'],
            ['', ''],
            ['/', '/'],
            ['/path/to/my/../file.txt', '/path/to/my/..'],
        ];
    }

    /**
     * @dataProvider extensionProvider
     */
    public function testGetExtension(string $raw, string $parsed): void
    {
        self::assertSame($parsed, HierarchicalPath::new($raw)->getExtension());
    }

    public static function extensionProvider(): array
    {
        return [
            ['/path/to/my/', ''],
            ['/path/to/my/file', ''],
            ['/path/to/my/file.txt', 'txt'],
            ['/path/to/my/file.csv.txt', 'txt'],
        ];
    }

    /**
     * @dataProvider withExtensionProvider
     */
    public function testWithExtension(string $raw, string $raw_ext, string $new_path, string $parsed_ext): void
    {
        $newPath = HierarchicalPath::new($raw)->withExtension($raw_ext);

        self::assertSame($new_path, (string) $newPath);
        self::assertSame($parsed_ext, $newPath->getExtension());
    }

    public static function withExtensionProvider(): array
    {
        return [
            ['/path/to/my/file.txt', 'csv', '/path/to/my/file.csv', 'csv'],
            ['/path/to/my/file.txt;foo=bar', 'csv', '/path/to/my/file.csv;foo=bar', 'csv'],
            ['/path/to/my/file', 'csv', '/path/to/my/file.csv', 'csv'],
            ['/path/to/my/file;foo', 'csv', '/path/to/my/file.csv;foo', 'csv'],
            ['/path/to/my/file.csv', '', '/path/to/my/file', ''],
            ['/path/to/my/file.csv;foo=bar,baz', '', '/path/to/my/file;foo=bar,baz', ''],
            ['/path/to/my/file.tar.gz', 'bz2', '/path/to/my/file.tar.bz2', 'bz2'],
            ['/path/to/my/file.tar.gz;foo', 'bz2', '/path/to/my/file.tar.bz2;foo', 'bz2'],
            ['', 'csv', '', ''],
            [';foo=bar', 'csv', ';foo=bar', ''],
            ['toto.', 'csv', 'toto.csv', 'csv'],
            ['toto.;foo', 'csv', 'toto.csv;foo', 'csv'],
            ['toto.csv;foo', 'csv', 'toto.csv;foo', 'csv'],
        ];
    }

    /**
     * @dataProvider invalidExtension
     */
    public function testWithExtensionWithInvalidExtension(?string $extension): void
    {
        $this->expectException(SyntaxError::class);

        HierarchicalPath::new()->withExtension($extension);
    }

    public static function invalidExtension(): array
    {
        return [
            'invalid format' => ['t/xt'],
            'starting with a dot' => ['.csv'],
            'invalid chars' => ["\0"],
            'null chars' => [null],
        ];
    }

    /**
     * @dataProvider withExtensionProvider2
     */
    public function testWithExtensionPreserveTypeCode(string $uri, string $extension, string $expected): void
    {
        self::assertSame(
            $expected,
            (string) HierarchicalPath::new($uri)->withExtension($extension)
        );
    }

    public static function withExtensionProvider2(): array
    {
        return [
            'no typecode' => ['/foo/bar.csv', 'txt', '/foo/bar.txt'],
            'with typecode' => ['/foo/bar.csv;type=a', 'txt', '/foo/bar.txt;type=a'],
            'remove extension with no typecode' => ['/foo/bar.csv', '', '/foo/bar'],
            'remove extension with typecode' => ['/foo/bar.csv;type=a', '', '/foo/bar;type=a'],
        ];
    }

    /**
     * @dataProvider getExtensionProvider
     */
    public function testGetExtensionPreserveTypeCode(string $uri, string $extension): void
    {
        self::assertSame($extension, HierarchicalPath::new($uri)->getExtension());
    }

    public static function getExtensionProvider(): array
    {
        return [
            'no typecode' => ['/foo/bar.csv', 'csv'],
            'with typecode' => ['/foo/bar.csv;type=a', 'csv'],
        ];
    }

    public static function geValueProvider(): array
    {
        return [
            ['', ''],
            ['0', '0'],
            ['azAZ0-9/%3F-._~!$&\'()*+,;=:@%^/[]{}\"<>\\', 'azAZ0-9/?-._~!$&\'()*+,;=:@%^/[]{}\"<>\\'],
            ['€', '€'],
            ['%E2%82%AC', '€'],
            ['frag ment', 'frag ment'],
            ['frag%20ment', 'frag ment'],
            ['frag%2-ment', 'frag%2-ment'],
            ['fr%61gment', 'fr%61gment'],
        ];
    }

    /**
     * @dataProvider getDirnameProvider
     */
    public function testWithDirname(string $path, string $dirname, string $expected): void
    {
        self::assertSame($expected, (string) HierarchicalPath::new($path)->withDirname($dirname));
    }

    public static function getDirnameProvider(): array
    {
        return [
            'path with basename and absolute dirname' => [
                'path' => '/foo/bar/baz',
                'dirname' => '/bar',
                'expected' => '/bar/baz',
            ],
            'path with basename and rootless dirname' => [
                'path' => '/foo/bar/baz',
                'dirname' => 'bar',
                'expected' => 'bar/baz',
            ],
            'path with basename and empty dirname' => [
                'path' => '/foo/bar/baz',
                'dirname' => '',
                'expected' => '/baz',
            ],
            'empty path and empty dirname' => [
                'path' => '',
                'dirname' => '',
                'expected' => '',
            ],
            'empty path and non empty dirname' => [
                'path' => '',
                'dirname' => '/foo/bar',
                'expected' => '/foo/bar/',
            ],
            'dirname with trailing slash' => [
                'path' => '',
                'dirname' => 'bar/baz/',
                'expected' => 'bar/baz/',
            ],
        ];
    }

    /**
     * @dataProvider getBasenameProvider
     */
    public function testWithBasename(string $path, string $basename, string $expected): void
    {
        self::assertSame($expected, (string) HierarchicalPath::new($path)->withBasename($basename));
    }

    public static function getBasenameProvider(): array
    {
        return [
            [
                'path' => '/foo/bar/baz',
                'basename' => 'bar',
                'expected' => '/foo/bar/bar',
            ],
            [
                'path' => 'foo/bar/baz',
                'basename' => 'bar',
                'expected' => 'foo/bar/bar',
            ],
            [
                'path' => '/foo/bar/',
                'basename' => '',
                'expected' => '/foo/bar/',
            ],
            [
                'path' => '',
                'basename' => '',
                'expected' => '',
            ],
            [
                'path' => '',
                'basename' => 'bar',
                'expected' => 'bar',
            ],
        ];
    }

    /**
     * @dataProvider basenameInvalidProvider
     */
    public function testWithBasenameThrowException(?string $path): void
    {
        $this->expectException(SyntaxError::class);

        HierarchicalPath::new('foo/bar')->withBasename($path);
    }

    public static function basenameInvalidProvider(): array
    {
        return [
            ['foo/bar'],
            [null],
        ];
    }


    /**
     * @dataProvider getURIProvider
     */
    public function testCreateFromUri(Psr7UriInterface|UriInterface $uri, ?string $expected): void
    {
        $path = HierarchicalPath::fromUri($uri);

        self::assertSame($expected, $path->value());
    }

    public static function getURIProvider(): iterable
    {
        return [
            'PSR-7 URI object' => [
                'uri' => Http::new('http://example.com/path'),
                'expected' => '/path',
            ],
            'PSR-7 URI object with no path' => [
                'uri' => Http::new('toto://example.com'),
                'expected' => '',
            ],
            'PSR-7 URI object with no authority' => [
                'uri' => Http::new('path/to/sky?toto'),
                'expected' => 'path/to/sky',
            ],
            'League URI object' => [
                'uri' => Uri::new('http://example.com/path'),
                'expected' => '/path',
            ],
            'League URI object with no path' => [
                'uri' => Uri::new('toto://example.com'),
                'expected' => '',
            ],
            'League URI object with no authority' => [
                'uri' => Uri::new('path/to/sky?toto'),
                'expected' => 'path/to/sky',
            ],
        ];
    }

    public function testCreateFromUriWithPSR7Implementation(): void
    {
        $uri = Uri::new('http://example.com')
            ->withPath('/path');

        self::assertSame('/path', $uri->getPath());
        self::assertSame('/path', HierarchicalPath::fromUri($uri)->toString());
    }

    /**
     * @dataProvider trailingSlashProvider
     */
    public function testHasTrailingSlash(string $path, bool $expected): void
    {
        self::assertSame($expected, HierarchicalPath::new($path)->hasTrailingSlash());
    }

    public static function trailingSlashProvider(): array
    {
        return [
            ['/path/to/my/', true],
            ['/path/to/my', false],
            ['path/to/my', false],
            ['path/to/my/', true],
            ['/', true],
            ['', false],
        ];
    }

    /**
     * @dataProvider withTrailingSlashProvider
     */
    public function testWithTrailingSlash(string $path, string $expected): void
    {
        self::assertSame($expected, (string) HierarchicalPath::new($path)->withTrailingSlash());
    }

    public static function withTrailingSlashProvider(): array
    {
        return [
            'relative path without ending slash' => ['toto', 'toto/'],
            'absolute path without ending slash' => ['/toto', '/toto/'],
            'root path' => ['/', '/'],
            'empty path' => ['', '/'],
            'relative path with ending slash' => ['toto/', 'toto/'],
            'absolute path with ending slash' => ['/toto/', '/toto/'],
        ];
    }

    /**
     * @dataProvider withoutTrailingSlashProvider
     */
    public function testWithoutTrailingSlash(string $path, string $expected): void
    {
        self::assertSame($expected, (string) HierarchicalPath::new($path)->withoutTrailingSlash());
    }

    public static function withoutTrailingSlashProvider(): array
    {
        return [
            'relative path without ending slash' => ['toto', 'toto'],
            'absolute path without ending slash' => ['/toto', '/toto'],
            'root path' => ['/', ''],
            'empty path' => ['', ''],
            'relative path with ending slash' => ['toto/', 'toto'],
            'absolute path with ending slash' => ['/toto/', '/toto'],
        ];
    }

    /**
     * @dataProvider validPathEncoding
     */
    public function testGetUriComponent(string $decoded, string $encoded): void
    {
        $path = HierarchicalPath::new($decoded);

        self::assertSame($decoded, $path->decoded());
        self::assertSame($encoded, $path->value());
        self::assertSame($encoded, $path->getUriComponent());
    }

    public static function validPathEncoding(): array
    {
        return [
            [
                'toto',
                'toto',
            ],
            [
                'bar---',
                'bar---',
            ],
            [
                '',
                '',
                '',
            ],
            [
                '"bad"',
                '%22bad%22',
            ],
            [
                '<not good>',
                '%3Cnot%20good%3E',
            ],
            [
                '{broken}',
                '%7Bbroken%7D',
            ],
            [
                '`oops`',
                '%60oops%60',
            ],
            [
                '\\slashy',
                '%5Cslashy',
            ],
            [
                'foo^bar',
                'foo%5Ebar',
            ],
            [
                'foo^bar/baz',
                'foo%5Ebar/baz',
            ],
            [
                'foo%2Fbar',
                'foo%2Fbar',
            ],
            [
                '/v1/people/%7E:(first-name,last-name,email-address,picture-url)',
                '/v1/people/%7E:(first-name,last-name,email-address,picture-url)',
            ],
            [
                '/v1/people/~:(first-name,last-name,email-address,picture-url)',
                '/v1/people/~:(first-name,last-name,email-address,picture-url)',
            ],
            [
                'foo%2520bar',
                'foo%2520bar',
            ],
        ];
    }
}

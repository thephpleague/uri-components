<?php

/**
 * League.Uri (http://uri.thephpleague.com/components)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.2
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Uri\Components;

use ArrayIterator;
use League\Uri\Components\HierarchicalPath;
use League\Uri\Components\Path;
use League\Uri\Exceptions\OffsetOutOfBounds;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Http;
use League\Uri\Uri;
use PHPUnit\Framework\TestCase;
use TypeError;
use Zend\Diactoros\Uri as ZendPsr7Uri;
use function date_create;
use function iterator_to_array;
use function var_export;

/**
 * @group path
 * @group hierarchicalpath
 * @coversDefaultClass \League\Uri\Components\HierarchicalPath
 */
class HierarchicalPathTest extends TestCase
{
    public function testSetState(): void
    {
        $component = HierarchicalPath::createFromString('yolo');
        $generateComponent = eval('return '.var_export($component, true).';');

        self::assertEquals($component, $generateComponent);
    }

    /**
     * @covers ::getIterator
     */
    public function testIterator(): void
    {
        $path = HierarchicalPath::createFromString('/5.0/components/path');

        self::assertEquals(['5.0', 'components', 'path'], iterator_to_array($path));
    }

    /**
     * @dataProvider validPathProvider
     *
     * @covers ::__toString
     * @covers ::__construct
     * @covers ::getContent
     */
    public function testValidPath(string $raw, string $expected): void
    {
        self::assertSame($expected, (string) HierarchicalPath::createFromString($raw));
    }

    public function validPathProvider(): array
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
     * @covers ::createFromPath
     * @covers ::withContent
     */
    public function testWithContent(): void
    {
        $str = '/path/to/the/sky';
        $path = HierarchicalPath::createFromPath(new Path($str));

        self::assertSame($path, $path->withContent($str));
        self::assertNotEquals($path, $path->withContent('foo/bar'));
    }

    /**
     * @dataProvider isAbsoluteProvider
     *
     * @covers ::isAbsolute
     */
    public function testIsAbsolute(string $raw, bool $expected): void
    {
        $path = HierarchicalPath::createFromString($raw);

        self::assertSame($expected, $path->isAbsolute());
    }

    public function isAbsoluteProvider(): array
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
     *
     * @covers ::get
     *
     * @param string|null $expected
     */
    public function testget(string $raw, int $key, $expected): void
    {
        self::assertSame($expected, HierarchicalPath::createFromString($raw)->get($key));
    }

    public function getProvider(): array
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
        self::assertSame($expected, (string) HierarchicalPath::createFromString($path)->withoutDotSegments());
    }

    /**
     * Provides different segment to be normalized.
     */
    public function normalizeProvider(): array
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
        self::assertSame($expected, (string) HierarchicalPath::createFromString($path)->withLeadingSlash());
    }

    public function withLeadingSlashProvider(): array
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
        self::assertSame($expected, (string) HierarchicalPath::createFromString($path)->withoutLeadingSlash());
    }

    public function withoutLeadingSlashProvider(): array
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
     *
     * @covers ::createRelativeFromSegments
     */
    public function testCreateRelativeFromSegments(iterable $input, string $expected): void
    {
        self::assertSame($expected, (string) HierarchicalPath::createRelativeFromSegments($input));
    }

    public function createFromRelativeSegmentsValid(): array
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
     *
     * @covers ::createAbsoluteFromSegments
     */
    public function testCreateAbsoluteFromSegments(iterable $input, string $expected): void
    {
        self::assertSame($expected, (string) HierarchicalPath::createAbsoluteFromSegments($input));
    }

    public function createFromAbsoluteSegmentsValid(): array
    {
        return [
            'array (2)' => [['www', 'example', 'com'], '/www/example/com'],
            'iterator' => [new ArrayIterator(['www', 'example', 'com']), '/www/example/com'],
            'Path object' => [HierarchicalPath::createFromString('/foo/bar/baz'), '/foo/bar/baz'],
            'arbitrary cut 1' => [['foo', 'bar', 'baz'], '/foo/bar/baz'],
            'arbitrary cut 2' => [['foo/bar', 'baz'], '/foo/bar/baz'],
            'arbitrary cut 3' => [['foo/bar/baz'], '/foo/bar/baz'],
            'use reserved characters #' => [['all', 'i%23s', 'good'], '/all/i%23s/good'],
            'enforce path status (2)' => [['', 'toto', 'yeah', ''], '/toto/yeah/'],
            'enforce path status (4)' => [['', '', 'toto', 'yeah', ''], '//toto/yeah/'],
        ];
    }

    /**
     * @covers ::createRelativeFromSegments
     */
    public function testCreateRelativeFromSegmentsFailed(): void
    {
        $this->expectException(TypeError::class);
        HierarchicalPath::createRelativeFromSegments([date_create()]);
    }

    /**
     * @covers ::createAbsoluteFromSegments
     */
    public function testCreateAbsoluteFromSegmentsFailed(): void
    {
        $this->expectException(TypeError::class);
        HierarchicalPath::createAbsoluteFromSegments([date_create()]);
    }

    /**
     * @dataProvider prependData
     *
     * @covers ::prepend
     * @covers ::withSegment
     */
    public function testPrepend(string $source, string $prepend, string $res): void
    {
        self::assertSame($res, (string) HierarchicalPath::createFromString($source)->prepend($prepend));
    }

    public function prependData(): array
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

    public function testPrependThrowsTypeError(): void
    {
        $this->expectException(TypeError::class);

        HierarchicalPath::createFromString('')->prepend(null);
    }

    /**
     * @dataProvider appendData
     *
     * @covers ::append
     * @covers ::withSegment
     */
    public function testAppend(string $source, string $append, string $res): void
    {
        self::assertSame($res, (string) HierarchicalPath::createFromString($source)->append($append));
    }

    public function appendData(): array
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

    public function testAppendThrowsTypeError(): void
    {
        $this->expectException(TypeError::class);

        HierarchicalPath::createFromString('')->append(null);
    }

    /**
     * @covers ::append
     * @covers ::withSegment
     */
    public function testWithSegmentUseAppend(): void
    {
        $path = HierarchicalPath::createFromString('foo/bar');

        self::assertEquals($path->withSegment(2, 'baz'), $path->append('baz'));
    }


    /**
     * @dataProvider withoutEmptySegmentsProvider
     */
    public function testWithoutEmptySegments(string $path, string $expected): void
    {
        self::assertSame($expected, (string) HierarchicalPath::createFromString($path)->withoutEmptySegments());
    }

    public function withoutEmptySegmentsProvider(): array
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
     *
     * @covers ::withSegment
     */
    public function testReplace(string $raw, string $input, int $offset, string $expected): void
    {
        self::assertSame($expected, (string) HierarchicalPath::createFromString($raw)->withSegment($offset, $input));
    }

    public function replaceValid(): array
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

    /**
     * @covers ::withSegment
     */
    public function testWithSegmentThrowsException(): void
    {
        $this->expectException(OffsetOutOfBounds::class);

        HierarchicalPath::createFromString('/test/')->withSegment(23, 'bar');
    }

    /**
     * Test AbstractSegment::without.
     *
     * @dataProvider withoutProvider
     *
     * @covers ::withoutSegment
     *
     * @param int[] $without
     */
    public function testWithout(string $origin, array $without, string $result): void
    {
        self::assertSame($result, (string) HierarchicalPath::createFromString($origin)->withoutSegment(...$without));
    }

    public function withoutProvider(): array
    {
        return [
            ['/master/test/query.php', [2], '/master/test'],
            ['/master/test/query.php', [-1], '/master/test'],
            ['/toto/le/heros/masson', [0], '/le/heros/masson'],
            ['/toto', [-1], '/'],
            ['toto/le/heros/masson', [2, 3], 'toto/le'],
        ];
    }

    /**
     * @covers ::withoutSegment
     */
    public function testWithoutSegmentThrowsException(): void
    {
        $this->expectException(OffsetOutOfBounds::class);

        HierarchicalPath::createFromString('/test/')->withoutSegment(23);
    }

    /**
     * @covers ::withoutSegment
     */
    public function testWithoutSegmentVariadicArgument(): void
    {
        $path = HierarchicalPath::createFromString('www/example/com');

        self::assertSame($path, $path->withoutSegment());
    }

    /**
     * @covers ::keys
     */
    public function testKeys(): void
    {
        $path = HierarchicalPath::createFromString('/bar/3/troll/3');

        self::assertCount(0, $path->keys('foo'));
        self::assertSame([0], $path->keys('bar'));
        self::assertCount(2, $path->keys('3'));
        self::assertSame([1, 3], $path->keys('3'));
        self::assertSame([0, 1, 2, 3], $path->keys());
    }

    /**
     * @covers ::segments
     */
    public function testSegments(): void
    {
        $path = HierarchicalPath::createFromString('/bar/3/troll/3');

        self::assertSame(['bar', '3', 'troll', '3'], $path->segments());
        self::assertSame([''], HierarchicalPath::createFromString()->segments());
        self::assertSame([''], HierarchicalPath::createFromString('/')->segments());
    }

    /**
     * @dataProvider arrayProvider
     *
     * @covers ::count
     */
    public function testCountable(string $input, array $gets, int $nbSegment): void
    {
        $path = HierarchicalPath::createFromString($input);

        self::assertCount($nbSegment, $path);
    }

    public function arrayProvider(): array
    {
        return [
            ['/toto/le/heros/masson', ['toto', 'le', 'heros', 'masson'], 4],
            ['toto/le/heros/masson', ['toto', 'le', 'heros', 'masson'], 4],
            ['/toto/le/heros/masson/', ['toto', 'le', 'heros', 'masson', ''], 5],
        ];
    }

    /**
     * @covers ::getBasename
     */
    public function testGetBasemane(): void
    {
        $path = HierarchicalPath::createFromString('/path/to/my/file.txt');

        self::assertSame('file.txt', $path->getBasename());
    }

    /**
     * @covers ::getBasename
     */
    public function testGetBasemaneWithEmptyBasename(): void
    {
        $path = HierarchicalPath::createFromString('/path/to/my/');

        self::assertEmpty($path->getBasename());
    }

    /**
     * @dataProvider dirnameProvider
     *
     * @covers ::getDirname
     */
    public function testGetDirmane(string $path, string $dirname): void
    {
        self::assertSame($dirname, HierarchicalPath::createFromString($path)->getDirname());
    }

    public function dirnameProvider(): array
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
     *
     * @covers ::getExtension
     */
    public function testGetExtension(string $raw, string $parsed): void
    {
        self::assertSame($parsed, HierarchicalPath::createFromString($raw)->getExtension());
    }

    public function extensionProvider(): array
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
     *
     * @covers ::withExtension
     * @covers ::getExtension
     */
    public function testWithExtension(string $raw, string $raw_ext, string $new_path, string $parsed_ext): void
    {
        $newPath = HierarchicalPath::createFromString($raw)->withExtension($raw_ext);

        self::assertSame($new_path, (string) $newPath);
        self::assertSame($parsed_ext, $newPath->getExtension());
    }

    public function withExtensionProvider(): array
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
     *
     * @covers ::withExtension
     * @covers ::withBasename
     * @covers ::withSegment
     * @covers ::buildBasename
     * @param ?string $extension
     */
    public function testWithExtensionWithInvalidExtension(?string $extension): void
    {
        $this->expectException(SyntaxError::class);

        HierarchicalPath::createFromString()->withExtension($extension);
    }

    public function invalidExtension(): array
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
     *
     * @covers ::withExtension
     * @covers ::withBasename
     * @covers ::withSegment
     * @covers ::buildBasename
     */
    public function testWithExtensionPreserveTypeCode(string $uri, string $extension, string $expected): void
    {
        self::assertSame(
            $expected,
            (string) HierarchicalPath::createFromString($uri)->withExtension($extension)
        );
    }

    public function withExtensionProvider2(): array
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
     *
     * @covers ::getExtension
     */
    public function testGetExtensionPreserveTypeCode(string $uri, string $extension): void
    {
        self::assertSame($extension, HierarchicalPath::createFromString($uri)->getExtension());
    }

    public function getExtensionProvider(): array
    {
        return [
            'no typecode' => ['/foo/bar.csv', 'csv'],
            'with typecode' => ['/foo/bar.csv;type=a', 'csv'],
        ];
    }

    public function geValueProvider(): array
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
     *
     * @covers ::withDirname
     * @covers ::withSegment
     */
    public function testWithDirname(string $path, string $dirname, string $expected): void
    {
        self::assertSame($expected, (string) HierarchicalPath::createFromString($path)->withDirname($dirname));
    }

    public function getDirnameProvider(): array
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
     *
     * @covers ::withBasename
     * @covers ::withSegment
     * @covers ::buildBasename
     */
    public function testWithBasename(string $path, string $basename, string $expected): void
    {
        self::assertSame($expected, (string) HierarchicalPath::createFromString($path)->withBasename($basename));
    }

    public function getBasenameProvider(): array
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
     *
     * @covers ::withBasename
     * @param ?string $path
     */
    public function testWithBasenameThrowException(?string $path): void
    {
        $this->expectException(SyntaxError::class);

        HierarchicalPath::createFromString('foo/bar')->withBasename($path);
    }

    /**
     * @covers ::withBasename
     */
    public function basenameInvalidProvider(): array
    {
        return [
            ['foo/bar'],
            [null],
        ];
    }


    /**
     * @dataProvider getURIProvider
     * @covers ::createFromUri
     *
     * @param mixed   $uri      an URI object
     * @param ?string $expected
     */
    public function testCreateFromUri($uri, ?string $expected): void
    {
        $path = HierarchicalPath::createFromUri($uri);

        self::assertSame($expected, $path->getContent());
    }

    public function getURIProvider(): iterable
    {
        return [
            'PSR-7 URI object' => [
                'uri' => Http::createFromString('http://example.com/path'),
                'expected' => '/path',
            ],
            'PSR-7 URI object with no path' => [
                'uri' => Http::createFromString('toto://example.com'),
                'expected' => '',
            ],
            'PSR-7 URI object with no authority' => [
                'uri' => Http::createFromString('path/to/sky?toto'),
                'expected' => 'path/to/sky',
            ],
            'League URI object' => [
                'uri' => Uri::createFromString('http://example.com/path'),
                'expected' => '/path',
            ],
            'League URI object with no path' => [
                'uri' => Uri::createFromString('toto://example.com'),
                'expected' => '',
            ],
            'League URI object with no authority' => [
                'uri' => Uri::createFromString('path/to/sky?toto'),
                'expected' => 'path/to/sky',
            ],
        ];
    }

    /**
     * @covers ::createFromUri
     * @covers \League\Uri\Components\HierarchicalPath::createFromUri
     */
    public function testCreateFromUriWithPSR7Implementation(): void
    {
        $uri = (new ZendPsr7Uri('http://example.com'))
            ->withPath('path');

        self::assertSame('path', $uri->getPath());
        self::assertSame('/path', HierarchicalPath::createFromUri($uri)->__toString());
    }

    public function testCreateFromUriThrowsTypeError(): void
    {
        $this->expectException(TypeError::class);

        HierarchicalPath::createFromUri('http://example.com:80');
    }

    public function testCreateFromStringThrowsTypeError(): void
    {
        $this->expectException(TypeError::class);

        HierarchicalPath::createFromString(new \stdClass());
    }

    /**
     * @dataProvider trailingSlashProvider
     */
    public function testHasTrailingSlash(string $path, bool $expected): void
    {
        self::assertSame($expected, HierarchicalPath::createFromString($path)->hasTrailingSlash());
    }

    public function trailingSlashProvider(): array
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
        self::assertSame($expected, (string) HierarchicalPath::createFromString($path)->withTrailingSlash());
    }

    public function withTrailingSlashProvider(): array
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
        self::assertSame($expected, (string) HierarchicalPath::createFromString($path)->withoutTrailingSlash());
    }

    public function withoutTrailingSlashProvider(): array
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
     *
     * @covers ::decoded
     * @covers ::getContent
     */
    public function testGetUriComponent(string $decoded, string $encoded): void
    {
        $path = HierarchicalPath::createFromString($decoded);

        self::assertSame($decoded, $path->decoded());
        self::assertSame($encoded, $path->getContent());
    }

    public function validPathEncoding(): array
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

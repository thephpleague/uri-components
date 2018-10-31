<?php

/**
 * League.Uri (http://uri.thephpleague.com/components).
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.0
 * @link       https://github.com/thephpleague/uri-schemes
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Uri\Component;

use ArrayIterator;
use League\Uri\Component\HierarchicalPath as Path;
use League\Uri\Exception\InvalidKey;
use League\Uri\Exception\InvalidUriComponent;
use PHPUnit\Framework\TestCase;
use function date_create;
use function iterator_to_array;
use function var_export;

/**
 * @group path
 * @group hierarchicalpath
 * @coversDefaultClass \League\Uri\Component\HierarchicalPath
 */
class HierarchicalPathTest extends TestCase
{
    public function testSetState(): void
    {
        $component = new Path('yolo');
        $generateComponent = eval('return '.var_export($component, true).';');
        self::assertEquals($component, $generateComponent);
    }

    /**
     * @covers ::getIterator
     */
    public function testIterator(): void
    {
        $path = new Path('/5.0/components/path');
        self::assertEquals(['5.0', 'components', 'path'], iterator_to_array($path));
    }

    /**
     * @dataProvider validPathProvider
     *
     * @covers ::__toString
     * @covers ::parse
     * @covers ::getContent
     */
    public function testValidPath(string $raw, string $expected): void
    {
        self::assertSame($expected, (string) (new Path($raw)));
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
     * @covers ::withContent
     */
    public function testWithContent(): void
    {
        $str = '/path/to/the/sky';
        $path = new Path($str);
        self::assertSame($path, $path->withContent($str));
    }

    /**
     * @dataProvider isAbsoluteProvider
     *
     * @covers ::isAbsolute
     */
    public function testIsAbsolute(string $raw, bool $expected): void
    {
        $path = new Path($raw);
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
        self::assertSame($expected, (new Path($raw))->get($key));
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
     * @dataProvider createFromSegmentsValid
     *
     * @covers ::createFromSegments
     */
    public function testCreateFromSegments(iterable $input, int $has_front_delimiter, string $expected): void
    {
        self::assertSame($expected, (string) Path::createFromSegments($input, $has_front_delimiter));
    }

    public function createFromSegmentsValid(): array
    {
        return [
            'array (1)' => [['www', 'example', 'com'], Path::IS_RELATIVE, 'www/example/com'],
            'array (2)' => [['www', 'example', 'com'], Path::IS_ABSOLUTE, '/www/example/com'],
            'iterator' => [new ArrayIterator(['www', 'example', 'com']), Path::IS_ABSOLUTE, '/www/example/com'],
            'Path object' => [new Path('/foo/bar/baz'), Path::IS_ABSOLUTE, '/foo/bar/baz'],
            'arbitrary cut 1' => [['foo', 'bar', 'baz'], Path::IS_ABSOLUTE, '/foo/bar/baz'],
            'arbitrary cut 2' => [['foo/bar', 'baz'], Path::IS_ABSOLUTE, '/foo/bar/baz'],
            'arbitrary cut 3' => [['foo/bar/baz'], Path::IS_ABSOLUTE, '/foo/bar/baz'],
            'ending delimiter' => [['foo/bar/baz', ''], Path::IS_RELATIVE, 'foo/bar/baz/'],
            'use reserved characters #' => [['all', 'i%23s', 'good'], Path::IS_ABSOLUTE, '/all/i%23s/good'],
            'use reserved characters ?' => [['all', 'i%3fs', 'good'], Path::IS_RELATIVE,  'all/i%3Fs/good'],
            'enforce path status (1)' => [['', 'toto', 'yeah', ''], Path::IS_RELATIVE, 'toto/yeah/'],
            'enforce path status (2)' => [['', 'toto', 'yeah', ''], Path::IS_ABSOLUTE, '/toto/yeah/'],
            'enforce path status (3)' => [['', '', 'toto', 'yeah', ''], Path::IS_RELATIVE, 'toto/yeah/'],
            'enforce path status (4)' => [['', '', 'toto', 'yeah', ''], Path::IS_ABSOLUTE, '//toto/yeah/'],
        ];
    }

    /**
     * @covers ::createFromSegments
     */
    public function testCreateFromSegmentsFailedWithInvalidType(): void
    {
        self::expectException(InvalidUriComponent::class);
        Path::createFromSegments(['all', 'is', 'good'], 23);
    }

    /**
     * @covers ::createFromSegments
     */
    public function testCreateFromSegmentsFailed(): void
    {
        self::expectException(InvalidUriComponent::class);
        Path::createFromSegments([date_create()], Path::IS_RELATIVE);
    }

    /**
     * @dataProvider prependData
     *
     * @covers ::prepend
     * @covers ::withSegment
     */
    public function testPrepend(string $source, string $prepend, string $res): void
    {
        self::assertSame($res, (string) (new Path($source))->prepend($prepend));
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

    /**
     * @dataProvider appendData
     *
     * @covers ::append
     * @covers ::withSegment
     */
    public function testAppend(string $source, string $append, string $res): void
    {
        self::assertSame($res, (string) (new Path($source))->append($append));
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

    /**
     * @dataProvider replaceValid
     *
     * @covers ::withSegment
     */
    public function testReplace(string $raw, string $input, int $offset, string $expected): void
    {
        self::assertSame($expected, (string) (new Path($raw))->withSegment($offset, $input));
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
        self::expectException(InvalidKey::class);
        (new Path('/test/'))->withSegment(23, 'bar');
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
        self::assertSame($result, (string) (new Path($origin))->withoutSegment(...$without));
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
        self::expectException(InvalidKey::class);
        (new Path('/test/'))->withoutSegment(23);
    }

    /**
     * @covers ::keys
     */
    public function testKeys(): void
    {
        $path = new Path('/bar/3/troll/3');
        self::assertCount(0, $path->keys('foo'));
        self::assertSame([0], $path->keys('bar'));
        self::assertCount(2, $path->keys('3'));
        self::assertSame([1, 3], $path->keys('3'));
    }

    /**
     * @dataProvider arrayProvider
     *
     * @covers ::count
     */
    public function testCountable(string $input, array $gets, int $nbSegment): void
    {
        $path = new Path($input);
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
        $path = new Path('/path/to/my/file.txt');
        self::assertSame('file.txt', $path->getBasename());
    }

    /**
     * @covers ::getBasename
     */
    public function testGetBasemaneWithEmptyBasename(): void
    {
        $path = new Path('/path/to/my/');
        self::assertEmpty($path->getBasename());
    }

    /**
     * @dataProvider dirnameProvider
     *
     * @covers ::getDirname
     */
    public function testGetDirmane(string $path, string $dirname): void
    {
        self::assertSame($dirname, (new Path($path))->getDirname());
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
        self::assertSame($parsed, (new Path($raw))->getExtension());
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
        $newPath = (new Path($raw))->withExtension($raw_ext);
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
     * @covers ::withExtension
     * @covers ::buildBasename
     */
    public function testWithExtensionWithInvalidExtension(string $extension): void
    {
        self::expectException(InvalidUriComponent::class);
        (new Path())->withExtension($extension);
    }

    public function invalidExtension(): array
    {
        return [
            'invalid format' => ['t/xt'],
            'starting with a dot' => ['.csv'],
            'invali chars' => ["\0"],
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
            (string) (new Path($uri))->withExtension($extension)
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
        self::assertSame($extension, (new Path($uri))->getExtension());
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
        self::assertSame($expected, (string) (new Path($path))->withDirname($dirname));
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
        $path = new Path($path);
        self::assertSame($expected, (string) $path->withBasename($basename));
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
     * @covers ::withBasename
     */
    public function testWithBasenameThrowException(): void
    {
        self::expectException(InvalidUriComponent::class);
        (new Path('foo/bar'))->withBasename('foo/bar');
    }
}

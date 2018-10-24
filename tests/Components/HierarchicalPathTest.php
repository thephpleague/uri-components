<?php

/**
 * League.Uri (https://uri.thephpleague.com/components/).
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.8.2
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Uri\Components;

use ArrayIterator;
use League\Uri\Components\Exception;
use League\Uri\Components\HierarchicalPath as Path;
use PHPUnit\Framework\TestCase;
use Traversable;

/**
 * @group path
 * @group hierarchicalpath
 */
final class HierarchicalPathTest extends TestCase
{
    public function testDebugInfo()
    {
        $component = new Path('yolo');
        self::assertInternalType('array', $component->__debugInfo());
    }

    public function testSetState()
    {
        $component = new Path('yolo');
        $generateComponent = eval('return '.var_export($component, true).';');
        self::assertEquals($component, $generateComponent);
    }

    public function testDefined()
    {
        $component = new Path('yolo');
        self::assertFalse($component->isNull());
        self::assertFalse($component->withContent(null)->isNull());
    }

    /**
     * @param string $raw
     * @param string $parsed
     * @dataProvider validPathProvider
     */
    public function testValidPath($raw, $parsed)
    {
        $path = new Path($raw);
        self::assertSame($parsed, $path->__toString());
    }

    public function validPathProvider()
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

    public function testWithContent()
    {
        $path = new Path('/path/to/the/sky');
        $alt_path = $path->withContent('/path/to/the/sky');
        self::assertSame($alt_path, $path);
    }

    /**
     * @param string $raw
     * @param bool   $expected
     * @dataProvider isAbsoluteProvider
     */
    public function testIsAbsolute($raw, $expected)
    {
        $path = new Path($raw);
        self::assertSame($expected, $path->isAbsolute());
    }

    public function isAbsoluteProvider()
    {
        return [
            ['', false],
            ['/', true],
            ['../..', false],
            ['/a/b/c', true],
        ];
    }

    /**
     * @param string $raw
     * @param int    $key
     * @param string $value
     * @dataProvider getSegmentProvider
     */
    public function testGetSegment($raw, $key, $value, $default)
    {
        $path = new Path($raw);
        self::assertSame($value, $path->getSegment($key, $default));
    }

    public function getSegmentProvider()
    {
        return [
            ['/shop/rev iew/', 1, 'rev iew', null],
            ['/shop/rev%20iew/', 1, 'rev iew', null],
            ['/shop/rev iew/', -2, 'rev iew', null],
            ['/shop/rev%20iew/', -2, 'rev iew', null],
            ['/shop/rev%20iew/', 28, 'foo', 'foo'],
        ];
    }

    /**
     * @param array|Traversable $input
     * @param int               $has_front_delimiter
     * @param string            $expected
     * @dataProvider createFromSegmentsValid
     */
    public function testCreateFromSegments($input, $has_front_delimiter, $expected)
    {
        self::assertSame($expected, (string) Path::createFromSegments($input, $has_front_delimiter));
    }

    public function createFromSegmentsValid()
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
     * @param array $input
     * @param int   $flags
     * @dataProvider createFromSegmentsInvalid
     */
    public function testCreateFromSegmentsFailed($input, $flags)
    {
        self::expectException(Exception::class);
        Path::createFromSegments($input, $flags);
    }

    public function createFromSegmentsInvalid()
    {
        return [
            'unknown flag' => [['all', 'is', 'good'], 23],
        ];
    }

    /**
     * @param string $source
     * @param string $prepend
     * @param string $res
     * @dataProvider prependData
     */
    public function testPrepend($source, $prepend, $res)
    {
        self::assertSame($res, (string) (new Path($source))->prepend($prepend));
    }

    public function prependData()
    {
        return [
            ['/test/query.php', '/master', '/master/test/query.php'],
            ['/test/query.php', '/master/', '/master/test/query.php'],
            ['/test/query.php', '', '/test/query.php'],
            ['/test/query.php', '/', '/test/query.php'],
        ];
    }

    /**
     * @param string $source
     * @param string $append
     * @param string $res
     * @dataProvider appendData
     */
    public function testAppend($source, $append, $res)
    {
        self::assertSame($res, (string) (new Path($source))->append($append));
    }

    public function appendData()
    {
        return [
            ['/test/', '/master/', '/test/master/'],
            ['/test/', '/master',  '/test/master'],
            ['/test',  'master',   '/test/master'],
            ['test',   'master',   'test/master'],
            ['test',   '/master',  'test/master'],
            ['test',   'master/',  'test/master/'],
        ];
    }

    /**
     * Test AbstractSegment::without.
     *
     * @param string $origin
     * @param array  $without
     * @param string $result
     *
     * @dataProvider withoutProvider
     */
    public function testWithout($origin, $without, $result)
    {
        self::assertSame($result, (string) (new Path($origin))->withoutSegments($without));
    }

    public function withoutProvider()
    {
        return [
            ['/test/query.php', [4], '/test/query.php'],
            ['/master/test/query.php', [2], '/master/test'],
            ['/master/test/query.php', [-1], '/master/test'],
            ['/toto/le/heros/masson', [0], '/le/heros/masson'],
            ['/toto/le/heros/masson', [2, 3], '/toto/le'],
            ['/toto/le/heros/masson', [1, 2], '/toto/masson'],
            ['/toto', [-1], '/'],
        ];
    }

    public function testWithoutTriggersException()
    {
        self::expectException(Exception::class);
        (new Path('/path/where'))->withoutSegments(['where']);
    }

    /**
     * @param string $raw
     * @param string $input
     * @param int    $offset
     * @param string $expected
     * @dataProvider replaceValid
     */
    public function testReplace($raw, $input, $offset, $expected)
    {
        $path = new Path($raw);
        $newPath = $path->replaceSegment($offset, $input);
        self::assertSame($expected, $newPath->__toString());
    }

    public function replaceValid()
    {
        return [
            ['/path/to/the/sky', 'shop', 0, '/shop/to/the/sky'],
            ['', 'shoki', 0, 'shoki'],
            ['', 'shoki/', 0, 'shoki'],
            ['', '/shoki/', 0, 'shoki'],
            ['/path/to/paradise', '::1', 42, '/path/to/paradise'],
            ['/path/to/paradise', 'path', 0, '/path/to/paradise'],
            ['/path/to/paradise', 'path', -1, '/path/to/path'],
            ['/path/to/paradise', 'path', -4, '/path/to/paradise'],
            ['/path/to/paradise', 'path', -3, '/path/to/paradise'],
            ['/foo', 'bar', -1, '/bar'],
            ['foo', 'bar', -1, 'bar'],
        ];
    }

    public function testKeys()
    {
        $path = new Path('/bar/3/troll/3');
        self::assertCount(4, $path->keys());
        self::assertCount(0, $path->keys('foo'));
        self::assertSame([0], $path->keys('bar'));
        self::assertCount(2, $path->keys('3'));
        self::assertSame([1, 3], $path->keys('3'));
    }

    /**
     * @param string $input
     * @param array  $getSegments
     * @param int    $nbSegment
     * @dataProvider arrayProvider
     */
    public function testCountable($input, $getSegments, $nbSegment)
    {
        $path = new Path($input);
        self::assertCount($nbSegment, $path);
        self::assertSame($getSegments, $path->getSegments());
    }

    public function arrayProvider()
    {
        return [
            ['/toto/le/heros/masson', ['toto', 'le', 'heros', 'masson'], 4],
            ['toto/le/heros/masson', ['toto', 'le', 'heros', 'masson'], 4],
            ['/toto/le/heros/masson/', ['toto', 'le', 'heros', 'masson', ''], 5],
        ];
    }

    public function testGetBasemane()
    {
        $path = new Path('/path/to/my/file.txt');
        self::assertSame('file.txt', $path->getBasename());
    }

    /**
     * @param string $path
     * @param string $dirname
     * @dataProvider dirnameProvider
     */
    public function testGetDirmane($path, $dirname)
    {
        self::assertSame($dirname, (new Path($path))->getDirname());
    }

    public function dirnameProvider()
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

    public function testGetBasemaneWithEmptyBasename()
    {
        $path = new Path('/path/to/my/');
        self::assertEmpty($path->getBasename());
    }

    /**
     * @param string $raw
     * @param string $parsed
     * @dataProvider extensionProvider
     */
    public function testGetExtension($raw, $parsed)
    {
        self::assertSame($parsed, (new Path($raw))->getExtension());
    }

    public function extensionProvider()
    {
        return [
            ['/path/to/my/', ''],
            ['/path/to/my/file', ''],
            ['/path/to/my/file.txt', 'txt'],
            ['/path/to/my/file.csv.txt', 'txt'],
        ];
    }

    /**
     * @param string $raw
     * @param string $raw_ext
     * @param string $new_path
     * @param string $parsed_ext
     * @dataProvider withExtensionProvider
     */
    public function testWithExtension($raw, $raw_ext, $new_path, $parsed_ext)
    {
        $newPath = (new Path($raw))->withExtension($raw_ext);
        self::assertSame($new_path, (string) $newPath);
        self::assertSame($parsed_ext, $newPath->getExtension());
    }

    public function withExtensionProvider()
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
     * @param string $extension
     */
    public function testWithExtensionWithInvalidExtension($extension)
    {
        self::expectException(Exception::class);
        (new Path())->withExtension($extension);
    }

    public function invalidExtension()
    {
        return [
            'invalid format' => ['t/xt'],
            'starting with a dot' => ['.csv'],
        ];
    }

    /**
     * @dataProvider withExtensionProvider2
     *
     * @param string $uri
     * @param string $extension
     * @param string $expected
     */
    public function testWithExtensionPreserveTypeCode($uri, $extension, $expected)
    {
        self::assertSame(
            $expected,
            (string) (new Path($uri))->withExtension($extension)
        );
    }

    public function withExtensionProvider2()
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
     * @param string $uri
     * @param string $extension
     */
    public function testGetExtensionPreserveTypeCode($uri, $extension)
    {
        $ftp = new Path($uri);
        self::assertSame($extension, $ftp->getExtension());
    }

    public function getExtensionProvider()
    {
        return [
            'no typecode' => ['/foo/bar.csv', 'csv'],
            'with typecode' => ['/foo/bar.csv;type=a', 'csv'],
        ];
    }

    /**
     * @dataProvider geValueProvider
     *
     * @param string $expected
     * @param string $path
     */
    public function testGetContent($expected, $path)
    {
        self::assertSame($expected, (new Path($path))->getContent(Path::RFC3987_ENCODING));
    }

    public function geValueProvider()
    {
        return [
            ['', ''],
            ['0', '0'],
            ['azAZ0-9/%3F-._~!$&\'()*+,;=:@%^/[]{}\"<>\\', 'azAZ0-9/?-._~!$&\'()*+,;=:@%^/[]{}\"<>\\'],
            ['€', '€'],
            ['€', '%E2%82%AC'],
            ['frag ment', 'frag ment'],
            ['frag ment', 'frag%20ment'],
            ['frag%2-ment', 'frag%2-ment'],
            ['fr%61gment', 'fr%61gment'],
        ];
    }

    public function testContentPreserveEncodedChars()
    {
        $path = new Path('/hi%2520');
        self::assertSame('/hi%2520', (string) $path);

        $src = new Path('/');
        self::assertSame('/hi%2520', (string) $src->append((string) $path));
    }

    public function testAppendDoestNotValidatedPrematurelyString()
    {
        $src = new Path('/');
        self::assertSame('/hi%11', (string) $src->append('hi%11'));
    }

    /**
     * @dataProvider getDirnameProvider
     */
    public function testWithDirname($path, $dirname, $expected)
    {
        $path = new Path($path);
        self::assertSame($expected, (string) $path->withDirname($dirname));
    }

    public function getDirnameProvider()
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
    public function testWithBasename($path, $basename, $expected)
    {
        $path = new Path($path);
        self::assertSame($expected, (string) $path->withBasename($basename));
    }

    public function getBasenameProvider()
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

    public function testWithBasenameThrowException()
    {
        self::expectException(Exception::class);
        (new Path('foo/bar'))->withBasename('foo/bar');
    }
}

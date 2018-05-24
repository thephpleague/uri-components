<?php

/**
 * League.Uri (http://uri.thephpleague.com).
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
use Traversable;
use TypeError;

/**
 * @group path
 * @group hierarchicalpath
 * @coversDefaultClass \League\Uri\Component\HierarchicalPath
 */
class HierarchicalPathTest extends TestCase
{
    public function testSetState()
    {
        $component = new Path('yolo');
        $generateComponent = eval('return '.var_export($component, true).';');
        $this->assertEquals($component, $generateComponent);
    }

    /**
     * @covers ::__debugInfo
     */
    public function testDebugInfo()
    {
        $component = new Path('yolo');
        $debugInfo = $component->__debugInfo();
        $this->assertArrayHasKey('component', $debugInfo);
        $this->assertSame($component->getContent(), $debugInfo['component']);
    }

    /**
     * @covers ::getIterator
     */
    public function testIterator()
    {
        $path = new Path('/5.0/components/path');
        $this->assertEquals(['5.0', 'components', 'path'], iterator_to_array($path));
    }

    /**
     * @param string $raw
     * @param string $parsed
     * @dataProvider validPathProvider
     */
    public function testValidPath($raw, $parsed)
    {
        $this->assertSame($parsed, (string) (new Path($raw)));
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
        $this->assertSame($alt_path, $path);
    }

    /**
     * @param string $raw
     * @param bool   $expected
     * @dataProvider isAbsoluteProvider
     */
    public function testIsAbsolute($raw, $expected)
    {
        $path = new Path($raw);
        $this->assertSame($expected, $path->isAbsolute());
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
     * @param string $expected
     * @dataProvider getProvider
     * @covers ::filterSegments
     * @covers ::get
     */
    public function testget($raw, $key, $expected)
    {
        $this->assertSame($expected, (new Path($raw))->get($key));
    }

    public function getProvider()
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
     * @param array|Traversable $input
     * @param int               $has_front_delimiter
     * @param string            $expected
     * @dataProvider createFromSegmentsValid
     * @covers ::createFromSegments
     */
    public function testCreateFromSegments($input, $has_front_delimiter, $expected)
    {
        $this->assertSame($expected, (string) Path::createFromSegments($input, $has_front_delimiter));
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
     * @covers ::createFromSegments
     */
    public function testCreateFromSegmentsFailedWithInvalidType()
    {
        $this->expectException(InvalidUriComponent::class);
        Path::createFromSegments(['all', 'is', 'good'], 23);
    }

    /**
     * @covers ::createFromSegments
     */
    public function testCreateFromSegmentsFailed()
    {
        $this->expectException(InvalidUriComponent::class);
        Path::createFromSegments([date_create()], Path::IS_RELATIVE);
    }

    /**
     * @covers ::createFromSegments
     */
    public function testCreateFromSegmentsFailed3()
    {
        $this->expectException(TypeError::class);
        Path::createFromSegments(date_create(), Path::IS_RELATIVE);
    }

    /**
     * @param string $source
     * @param string $prepend
     * @param string $res
     * @dataProvider prependData
     * @covers ::prepend
     * @covers ::withSegment
     */
    public function testPrepend($source, $prepend, $res)
    {
        $path = new Path($source);

        $this->assertSame($res, (string) $path->prepend($prepend));
    }

    public function prependData()
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
     * @param string $source
     * @param string $append
     * @param string $res
     * @dataProvider appendData
     * @covers ::append
     * @covers ::withSegment
     */
    public function testAppend($source, $append, $res)
    {
        $path = new Path($source);

        $this->assertSame($res, (string) $path->append($append));
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
            ['test',   '/',        'test/'],
            ['/',      'test',     '/test'],
        ];
    }

    /**
     * @param string $raw
     * @param string $input
     * @param int    $offset
     * @param string $expected
     * @dataProvider replaceValid
     * @covers ::withSegment
     */
    public function testReplace($raw, $input, $offset, $expected)
    {
        $this->assertSame($expected, (string) (new Path($raw))->withSegment($offset, $input));
    }

    public function replaceValid()
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
    public function testWithSegmentThrowsException()
    {
        $this->expectException(InvalidKey::class);
        (new Path('/test/'))->withSegment(23, 'bar');
    }

    /**
     * Test AbstractSegment::without.
     *
     * @param string $origin
     * @param mixed  $without
     * @param string $result
     *
     * @dataProvider withoutProvider
     * @covers ::withoutSegment
     */
    public function testWithout($origin, $without, $result)
    {
        $rest = [];
        if (is_array($without)) {
            $tmp = array_shift($without);
            $rest = $without;
            $without = $tmp;
        }

        $this->assertSame($result, (string) (new Path($origin))->withoutSegment($without, ...$rest));
    }

    public function withoutProvider()
    {
        return [
            ['/master/test/query.php', 2, '/master/test'],
            ['/master/test/query.php', -1, '/master/test'],
            ['/toto/le/heros/masson', 0, '/le/heros/masson'],
            ['/toto', -1, '/'],
            ['/toto/le/heros/masson', [2, 3], '/toto/le'],
        ];
    }

    /**
     * @covers ::withoutSegment
     */
    public function testWithoutSegmentThrowsException()
    {
        $this->expectException(InvalidKey::class);
        (new Path('/test/'))->withoutSegment(23);
    }

    /**
     * @covers ::keys
     */
    public function testKeys()
    {
        $path = new Path('/bar/3/troll/3');
        $this->assertCount(0, $path->keys('foo'));
        $this->assertSame([0], $path->keys('bar'));
        $this->assertCount(2, $path->keys('3'));
        $this->assertSame([1, 3], $path->keys('3'));
    }

    /**
     * @param string $input
     * @param array  $gets
     * @param int    $nbSegment
     * @dataProvider arrayProvider
     * @covers ::count
     */
    public function testCountable($input, $gets, $nbSegment)
    {
        $path = new Path($input);
        $this->assertCount($nbSegment, $path);
    }

    public function arrayProvider()
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
    public function testGetBasemane()
    {
        $path = new Path('/path/to/my/file.txt');
        $this->assertSame('file.txt', $path->getBasename());
    }

    /**
     * @covers ::getBasename
     */
    public function testGetBasemaneWithEmptyBasename()
    {
        $path = new Path('/path/to/my/');
        $this->assertEmpty($path->getBasename());
    }

    /**
     * @param string $path
     * @param string $dirname
     * @dataProvider dirnameProvider
     * @covers ::getDirname
     */
    public function testGetDirmane($path, $dirname)
    {
        $this->assertSame($dirname, (new Path($path))->getDirname());
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

    /**
     * @param string $raw
     * @param string $parsed
     * @dataProvider extensionProvider
     * @covers ::getExtension
     */
    public function testGetExtension($raw, $parsed)
    {
        $this->assertSame($parsed, (new Path($raw))->getExtension());
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
     * @covers ::withExtension
     * @covers ::getExtension
     */
    public function testWithExtension($raw, $raw_ext, $new_path, $parsed_ext)
    {
        $newPath = (new Path($raw))->withExtension($raw_ext);
        $this->assertSame($new_path, (string) $newPath);
        $this->assertSame($parsed_ext, $newPath->getExtension());
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
     * @covers ::withExtension
     * @covers ::withBasename
     * @covers ::withSegment
     * @covers ::withExtension
     * @covers ::buildBasename
     *
     * @param string $extension
     */
    public function testWithExtensionWithInvalidExtension($extension)
    {
        $this->expectException(InvalidUriComponent::class);
        (new Path())->withExtension($extension);
    }

    public function invalidExtension()
    {
        return [
            'invalid format' => ['t/xt'],
            'starting with a dot' => ['.csv'],
            'invali chars' => ["\0"],
        ];
    }

    /**
     * @dataProvider withExtensionProvider2
     * @covers ::withExtension
     * @covers ::withBasename
     * @covers ::withSegment
     * @covers ::buildBasename
     *
     * @param string $uri
     * @param string $extension
     * @param string $expected
     */
    public function testWithExtensionPreserveTypeCode($uri, $extension, $expected)
    {
        $this->assertSame(
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
     * @covers ::getExtension
     *
     * @param string $uri
     * @param string $extension
     */
    public function testGetExtensionPreserveTypeCode($uri, $extension)
    {
        $this->assertSame($extension, (new Path($uri))->getExtension());
    }

    public function getExtensionProvider()
    {
        return [
            'no typecode' => ['/foo/bar.csv', 'csv'],
            'with typecode' => ['/foo/bar.csv;type=a', 'csv'],
        ];
    }

    public function geValueProvider()
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
     * @param mixed $path
     * @param mixed $dirname
     * @param mixed $expected
     * @covers ::withDirname
     * @covers ::withSegment
     */
    public function testWithDirname($path, $dirname, $expected)
    {
        $path = new Path($path);
        $this->assertSame($expected, (string) $path->withDirname($dirname));
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
     * @param mixed $path
     * @param mixed $basename
     * @param mixed $expected
     * @covers ::withBasename
     * @covers ::withSegment
     * @covers ::buildBasename
     */
    public function testWithBasename($path, $basename, $expected)
    {
        $path = new Path($path);
        $this->assertSame($expected, (string) $path->withBasename($basename));
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

    /**
     * @covers ::withBasename
     */
    public function testWithBasenameThrowException()
    {
        $this->expectException(InvalidUriComponent::class);
        (new Path('foo/bar'))->withBasename('foo/bar');
    }
}

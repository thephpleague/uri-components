<?php

namespace LeagueTest\Uri\Components;

use ArrayIterator;
use League\Uri\Components\Exception;
use League\Uri\Components\HierarchicalPath as Path;

/**
 * @group path
 * @group hierarchicalpath
 */
class HierarchicalPathTest extends AbstractTestCase
{
    /**
     * @supportsDebugInfo
     */
    public function testDebugInfo()
    {
        $component = new Path('yolo');
        $this->assertInternalType('array', $component->__debugInfo());
    }

    public function testSetState()
    {
        $component = new Path('yolo');
        $generateComponent = eval('return '.var_export($component, true).';');
        $this->assertEquals($component, $generateComponent);
    }

    public function testDefined()
    {
        $component = new Path('yolo');
        $this->assertFalse($component->isNull());
        $this->assertFalse($component->withContent(null)->isNull());
    }

    /**
     * @param string $raw
     * @param string $parsed
     * @dataProvider validPathProvider
     */
    public function testValidPath($raw, $parsed)
    {
        $path = new Path($raw);
        $this->assertSame($parsed, $path->__toString());
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

    /**
     * @param $str
     * @dataProvider failedConstructor
     */
    public function testInvalidPath($str)
    {
        $this->expectException(Exception::class);
        new Path($str);
    }

    public function failedConstructor()
    {
        return [
            'bool' => [true],
            'Std Class' => [(object) 'foo'],
            'float' => [1.2],
            'reserved chars' => ['foo?bar'],
        ];
    }

    public function testWithContent()
    {
        $path = new Path('/path/to/the/sky');
        $alt_path = $path->withContent('/path/to/the/sky');
        $this->assertSame($alt_path, $path);
    }

    /**
     * @param $raw
     * @param $expected
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
     * @param string $value
     * @param mixed  $default
     * @dataProvider getSegmentProvider
     */
    public function testGetSegment($raw, $key, $value, $default)
    {
        $path = new Path($raw);
        $this->assertSame($value, $path->getSegment($key, $default));
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
     * @param $input
     * @param $has_front_delimiter
     * @param $expected
     * @dataProvider createFromSegmentsValid
     */
    public function testCreateFromSegments($input, $has_front_delimiter, $expected)
    {
        $this->assertSame($expected, Path::createFromSegments($input, $has_front_delimiter)->__toString());
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
        ];
    }

    /**
     * @param $input
     * @param $flags
     * @dataProvider createFromSegmentsInvalid
     */
    public function testCreateFromSegmentsFailed($input, $flags)
    {
        $this->expectException(Exception::class);
        Path::createFromSegments($input, $flags);
    }

    public function createFromSegmentsInvalid()
    {
        return [
            'string' => ['www.example.com', Path::IS_RELATIVE],
            'bool' => [true, Path::IS_RELATIVE],
            'integer' => [1, Path::IS_RELATIVE],
            'object' => [new \stdClass(), Path::IS_RELATIVE],
            'unknown flag' => [['all', 'is', 'good'], 23],
        ];
    }

    /**
     * @param $source
     * @param $prepend
     * @param $res
     * @dataProvider prependData
     */
    public function testPrepend($source, $prepend, $res)
    {
        $path = new Path($source);
        $newPath = $path->prepend($prepend);
        $this->assertSame($res, $newPath->__toString());
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
     * @param $source
     * @param $append
     * @param $res
     * @dataProvider appendData
     */
    public function testAppend($source, $append, $res)
    {
        $path = new Path($source);
        $newPath = $path->append($append);
        $this->assertSame($res, $newPath->__toString());
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
     * Test AbstractSegment::without
     *
     * @param $origin
     * @param $without
     * @param $result
     *
     * @dataProvider withoutProvider
     */
    public function testWithout($origin, $without, $result)
    {
        $this->assertSame($result, (string) (new Path($origin))->without($without));
    }

    public function withoutProvider()
    {
        return [
            ['/test/query.php', [4], '/test/query.php'],
            ['/master/test/query.php', [2], '/master/test'],
            ['/toto/le/heros/masson', [0], '/le/heros/masson'],
            ['/toto/le/heros/masson', [2, 3], '/toto/le'],
            ['/toto/le/heros/masson', [1, 2], '/toto/masson'],
        ];
    }

    public function testWithoutThrowException()
    {
        $this->expectException(Exception::class);
        (new Path('/toto/le/heros/masson'))->without(['toto']);
    }

    /**
     * @param $raw
     * @param $input
     * @param $offset
     * @param $expected
     * @dataProvider replaceValid
     */
    public function testReplace($raw, $input, $offset, $expected)
    {
        $path = new Path($raw);
        $newPath = $path->replace($offset, $input);
        $this->assertSame($expected, $newPath->__toString());
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
        ];
    }

    public function testKeys()
    {
        $path = new Path('/bar/3/troll/3');
        $this->assertCount(4, $path->keys());
        $this->assertCount(0, $path->keys('foo'));
        $this->assertSame([0], $path->keys('bar'));
        $this->assertCount(2, $path->keys('3'));
        $this->assertSame([1, 3], $path->keys('3'));
    }

    /**
     * @param $input
     * @param $getSegments
     * @param $nbSegment
     * @dataProvider arrayProvider
     */
    public function testCountable($input, $getSegments, $nbSegment)
    {
        $path = new Path($input);
        $this->assertCount($nbSegment, $path);
        $this->assertSame($getSegments, $path->getSegments());
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
        $this->assertSame('file.txt', $path->getBasename());
    }

    /**
     * @param $path
     * @param $dirname
     * @dataProvider dirnameProvider
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

    public function testGetBasemaneWithEmptyBasename()
    {
        $path = new Path('/path/to/my/');
        $this->assertEmpty($path->getBasename());
    }

    /**
     * @param $raw
     * @param $parsed
     * @dataProvider extensionProvider
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
     * @param $raw
     * @param $raw_ext
     * @param $new_path
     * @param $parsed_ext
     * @dataProvider withExtensionProvider
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
     *
     * @param string $extension
     */
    public function testWithExtensionWithInvalidExtension($extension)
    {
        $this->expectException(Exception::class);
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
     * @param $params
     * @param $callable
     * @param $expected
     * @dataProvider filterProvider
     */
    public function testFilter($params, $callable, $expected)
    {
        $obj = Path::createFromSegments($params, Path::IS_ABSOLUTE)->filter($callable, 0);
        $this->assertSame($expected, $obj->__toString());
    }

    public function filterProvider()
    {
        $func = function ($value) {
            return stripos($value, '.') !== false;
        };

        return [
            'empty query' => [[], $func, '/'],
            'remove One' => [['toto', 'foo.bar', 'st.ay'], $func, '/foo.bar/st.ay'],
            'remove All' => [['foobar', 'stay'], $func, '/'],
            'remove None' => [['foo.bar', 'st.ay'], $func, '/foo.bar/st.ay'],
        ];
    }

    public function testFilterThrowException()
    {
        $func = function ($value) {
            return stripos($value, '.') !== false;
        };

        $this->expectException(Exception::class);
        (new Path())->filter($func, 34);
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
     *
     * @param $uri
     * @param $extension
     */
    public function testGetExtensionPreserveTypeCode($uri, $extension)
    {
        $ftp = new Path($uri);
        $this->assertSame($extension, $ftp->getExtension());
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
        ];
    }


    /**
     * @dataProvider getBasenameProvider
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

    public function testWithBasenameThrowException()
    {
        $this->expectException(Exception::class);
        (new Path('foo/bar'))->withBasename('foo/bar');
    }
}

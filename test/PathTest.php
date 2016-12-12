<?php

namespace LeagueTest\Uri\Components;

use League\Uri\Components\Exception;
use League\Uri\Components\Path;

/**
 * @group path
 * @group segmentmodifier
 */
class PathTest extends AbstractTestCase
{
    /**
     * @dataProvider validPathEncoding
     *
     * @param string $raw
     * @param string $parsed
     */
    public function testGetUriComponent($raw, $parsed)
    {
        $path = new Path($raw);
        $this->assertSame($parsed, $path->getUriComponent());
        $this->assertSame($raw, $path->getContent(Path::RFC3987_ENCODING));
        $this->assertSame($raw, $path->getContent(Path::NO_ENCODING));
        $this->assertTrue($path->isNull());
    }

    public function validPathEncoding()
    {
        return [
            ['toto', 'toto'],
            ['bar---', 'bar---'],
            ['', ''],
            ['"bad"', '%22bad%22'],
            ['<not good>', '%3Cnot%20good%3E'],
            ['{broken}', '%7Bbroken%7D'],
            ['`oops`', '%60oops%60'],
            ['\\slashy', '%5Cslashy'],
            ['foo^bar', 'foo%5Ebar'],
            ['foo^bar/baz', 'foo%5Ebar/baz'],
            ['foo%2Fbar', 'foo%2Fbar', 'foo%2Fbar'],
        ];
    }

    public function testNullConstructor()
    {
        $path = new Path();
        $this->assertEquals(new Path(''), $path);
        $this->assertTrue($path->isNull());
        $this->assertTrue($path->isEmpty());
    }

    /**
     * @param $raw
     * @dataProvider invalidDataProvider
     */
    public function testFailedConstructor($raw)
    {
        $this->expectException(Exception::class);
        new Path($raw);
    }

    public function testInvalidEncodingTypeThrowException()
    {
        $this->expectException(Exception::class);
        (new Path('query'))->getContent('RFC1738');
    }

    public function invalidDataProvider()
    {
        return [
            'bool' => [true],
            'Std Class' => [(object) 'foo'],
            'float' => [1.2],
            'array' => [['foo']],
        ];
    }

    /**
     * Test Removing Dot Segment
     *
     * @param $expected
     * @param $path
     * @dataProvider normalizeProvider
     */
    public function testWithoutDotSegments($path, $expected)
    {
        $this->assertSame($expected, (new Path($path))->withoutDotSegments()->__toString());
    }

    /**
     * Provides different segment to be normalized
     *
     * @return array
     */
    public function normalizeProvider()
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
     * @param $path
     * @param $expected
     * @dataProvider withoutEmptySegmentsProvider
     */
    public function testWithoutEmptySegments($path, $expected)
    {
        $this->assertSame($expected, (new Path($path))->withoutEmptySegments()->__toString());
    }

    public function withoutEmptySegmentsProvider()
    {
        return [
            ['/a/b/c', '/a/b/c'],
            ['//a//b//c', '/a/b/c'],
            ['a//b/c//', 'a/b/c/'],
            ['/a/b/c//', '/a/b/c/'],
        ];
    }

    /**
     * @param $path
     * @param $expected
     * @dataProvider trailingSlashProvider
     */
    public function testHasTrailingSlash($path, $expected)
    {
        $this->assertSame($expected, (new Path($path))->hasTrailingSlash());
    }

    public function trailingSlashProvider()
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
     * @param $path
     * @param $expected
     * @dataProvider withTrailingSlashProvider
     */
    public function testWithTrailingSlash($path, $expected)
    {
        $this->assertSame($expected, (string) (new Path($path))->withTrailingSlash());
    }

    public function withTrailingSlashProvider()
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
     * @param $path
     * @param $expected
     * @dataProvider withoutTrailingSlashProvider
     */
    public function testWithoutTrailingSlash($path, $expected)
    {
        $this->assertSame($expected, (string) (new Path($path))->withoutTrailingSlash());
    }

    public function withoutTrailingSlashProvider()
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
     * @param $path
     * @param $expected
     * @dataProvider withLeadingSlashProvider
     */
    public function testWithLeadingSlash($path, $expected)
    {
        $this->assertSame($expected, (string) (new Path($path))->withLeadingSlash());
    }

    public function withLeadingSlashProvider()
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
     * @param $path
     * @param $expected
     * @dataProvider withoutLeadingSlashProvider
     */
    public function testWithoutLeadingSlash($path, $expected)
    {
        $this->assertSame($expected, (string) (new Path($path))->withoutLeadingSlash());
    }

    public function withoutLeadingSlashProvider()
    {
        return [
            'relative path without ending slash' => ['toto', 'toto'],
            'absolute path without ending slash' => ['/toto', 'toto'],
            'root path' => ['/', ''],
            'empty path' => ['', ''],
            'absolute path with ending slash' => ['/toto/', 'toto/'],
        ];
    }
}

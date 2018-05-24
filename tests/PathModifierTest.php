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

namespace LeagueTest\Uri;

use GuzzleHttp\Psr7;
use League\Uri;
use League\Uri\Components\DataPath;
use League\Uri\Components\Path;
use League\Uri\Exception\InvalidUriComponent;
use League\Uri\Schemes\Data;
use League\Uri\Schemes\Http;
use PHPUnit\Framework\TestCase;

/**
 * @group path
 */
class PathModifierTest extends TestCase
{
    /**
     * @var Http
     */
    private $uri;

    protected function setUp()
    {
        $this->uri = Http::createFromString(
            'http://www.example.com/path/to/the/sky.php?kingkong=toto&foo=bar+baz#doc3'
        );
    }

    /**
     * @covers \League\Uri\datapath_to_binary
     * @covers \League\Uri\normalize_path
     *
     * @dataProvider fileProvider
     *
     * @param Data $binary
     * @param Data $ascii
     */
    public function testToBinary(Data $binary, Data $ascii)
    {
        $this->assertSame((string) $binary, (string) Uri\datapath_to_binary($ascii));
    }

    /**
     * @covers \League\Uri\datapath_to_ascii
     * @covers \League\Uri\normalize_path
     *
     * @dataProvider fileProvider
     *
     * @param Data $binary
     * @param Data $ascii
     */
    public function testToAscii(Data $binary, Data $ascii)
    {
        $this->assertSame((string) $ascii, (string) Uri\datapath_to_ascii($binary));
    }

    public function fileProvider()
    {
        $textPath = new DataPath('text/plain;charset=us-ascii,Bonjour%20le%20monde%21');
        $binPath = DataPath::createFromPath(__DIR__.'/data/red-nose.gif');

        $ascii = Data::createFromString('data:text/plain;charset=us-ascii,Bonjour%20le%20monde%21');
        $binary = Data::createFromString('data:'.$textPath->toBinary());

        $pathBin = Data::createFromPath(__DIR__.'/data/red-nose.gif');
        $pathAscii = Data::createFromString('data:'.$binPath->toAscii());

        return [
            [$pathBin, $pathAscii],
            [$binary, $ascii],
        ];
    }

    /**
     * @covers \League\Uri\replace_data_uri_parameters
     * @covers \League\Uri\normalize_path
     */
    public function testDataUriWithParameters()
    {
        $uri = Data::createFromString('data:text/plain;charset=us-ascii,Bonjour%20le%20monde!');
        $this->assertSame(
            'text/plain;coco=chanel,Bonjour%20le%20monde!',
            Uri\replace_data_uri_parameters($uri, 'coco=chanel')->getPath()
        );
    }

    /**
     * @covers \League\Uri\append_path
     * @covers \League\Uri\normalize_path
     *
     * @dataProvider validPathProvider
     *
     * @param string $segment
     * @param int    $key
     * @param string $append
     * @param string $prepend
     * @param string $replace
     */
    public function testAppendProcess(string $segment, int $key, string $append, string $prepend, string $replace)
    {
        $this->assertSame($append, Uri\append_path($this->uri, $segment)->getPath());
    }

    /**
     * @covers \League\Uri\append_path
     * @covers \League\Uri\normalize_path
     *
     * @dataProvider validAppendPathProvider
     *
     * @param string $uri
     * @param string $segment
     * @param string $expected
     */
    public function testAppendProcessWithRelativePath(string $uri, string $segment, string $expected)
    {
        $this->assertSame($expected, (string) Uri\append_path(Http::createFromString($uri), $segment));
    }

    public function validAppendPathProvider()
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

    /**
     * @covers \League\Uri\replace_basename
     * @covers \League\Uri\normalize_path
     *
     * @dataProvider validBasenameProvider
     *
     * @param string $path
     * @param string $uri
     * @param string $expected
     */
    public function testBasename(string $path, string $uri, string $expected)
    {
        $this->assertSame($expected, (string) Uri\replace_basename(Psr7\uri_for($uri), $path));
    }

    public function validBasenameProvider()
    {
        return [
            ['baz', 'http://example.com', 'http://example.com/baz'],
            ['baz', 'http://example.com/foo/bar', 'http://example.com/foo/baz'],
            ['baz', 'http://example.com/foo/', 'http://example.com/foo/baz'],
            ['baz', 'http://example.com/foo', 'http://example.com/baz'],
        ];
    }

    /**
     * @covers \League\Uri\replace_basename
     * @covers \League\Uri\normalize_path
     */
    public function testBasenameThrowException()
    {
        $this->expectException(InvalidUriComponent::class);
        Uri\replace_basename(Psr7\uri_for('http://example.com'), 'foo/baz');
    }

    /**
     * @covers \League\Uri\replace_dirname
     * @covers \League\Uri\normalize_path
     *
     * @dataProvider validDirnameProvider
     *
     * @param string $path
     * @param string $uri
     * @param string $expected
     */
    public function testDirname(string $path, string $uri, string $expected)
    {
        $this->assertSame($expected, (string) Uri\replace_dirname(Psr7\uri_for($uri), $path));
    }

    public function validDirnameProvider()
    {
        return [
            ['baz', 'http://example.com', 'http://example.com/baz/'],
            ['baz/', 'http://example.com', 'http://example.com/baz/'],
            ['baz', 'http://example.com/foo', 'http://example.com/baz/foo'],
            ['baz', 'http://example.com/foo/yes', 'http://example.com/baz/yes'],
        ];
    }

    /**
     * @covers \League\Uri\prepend_path
     * @covers \League\Uri\normalize_path
     *
     * @dataProvider validPathProvider
     *
     * @param string $segment
     * @param int    $key
     * @param string $append
     * @param string $prepend
     * @param string $replace
     */
    public function testPrependProcess(string $segment, int $key, string $append, string $prepend, string $replace)
    {
        $this->assertSame($prepend, Uri\prepend_path($this->uri, $segment)->getPath());
    }

    /**
     * @covers \League\Uri\replace_segment
     * @covers \League\Uri\normalize_path
     *
     * @dataProvider validPathProvider
     *
     * @param string $segment
     * @param int    $key
     * @param string $append
     * @param string $prepend
     * @param string $replace
     */
    public function testReplaceSegmentProcess(string $segment, int $key, string $append, string $prepend, string $replace)
    {
        $this->assertSame($replace, Uri\replace_segment($this->uri, $key, $segment)->getPath());
    }

    public function validPathProvider()
    {
        return [
            ['toto', 2, '/path/to/the/sky.php/toto', '/toto/path/to/the/sky.php', '/path/to/toto/sky.php'],
            ['le blanc', 2, '/path/to/the/sky.php/le%20blanc', '/le%20blanc/path/to/the/sky.php', '/path/to/le%20blanc/sky.php'],
        ];
    }

    /**
     * @covers \League\Uri\add_basepath
     * @covers \League\Uri\normalize_path
     *
     * @dataProvider addBasePathProvider
     *
     * @param string $basepath
     * @param string $expected
     */
    public function testAddBasePath(string $basepath, string $expected)
    {
        $this->assertSame($expected, Uri\add_basepath($this->uri, $basepath)->getPath());
    }

    public function addBasePathProvider()
    {
        return [
            ['/', '/path/to/the/sky.php'],
            ['', '/path/to/the/sky.php'],
            ['/path/to', '/path/to/the/sky.php'],
            ['/route/to', '/route/to/path/to/the/sky.php'],
        ];
    }

    /**
     * @covers \League\Uri\add_basepath
     * @covers \League\Uri\normalize_path
     */
    public function testAddBasePathWithRelativePath()
    {
        $uri = Http::createFromString('base/path');
        $this->assertSame('/base/path', Uri\add_basepath($uri, '/base/path')->getPath());
    }

    /**
     * @covers \League\Uri\remove_basepath
     * @covers \League\Uri\normalize_path
     *
     * @dataProvider removeBasePathProvider
     *
     * @param string $basepath
     * @param string $expected
     */
    public function testRemoveBasePath(string $basepath, string $expected)
    {
        $this->assertSame($expected, Uri\remove_basepath($this->uri, $basepath)->getPath());
    }

    public function removeBasePathProvider()
    {
        return [
            ['/', '/path/to/the/sky.php'],
            ['', '/path/to/the/sky.php'],
            ['/path/to', '/the/sky.php'],
            ['/route/to', '/path/to/the/sky.php'],
        ];
    }

    /**
     * @covers \League\Uri\remove_basepath
     * @covers \League\Uri\normalize_path
     */
    public function testRemoveBasePathWithRelativePath()
    {
        $uri = Http::createFromString('base/path');
        $this->assertSame('base/path', Uri\remove_basepath($uri, '/base/path')->getPath());
    }

    /**
     * @covers \League\Uri\remove_segments
     * @covers \League\Uri\normalize_path
     *
     * @dataProvider validwithoutSegmentProvider
     *
     * @param array  $keys
     * @param string $expected
     */
    public function testwithoutSegment(array $keys, string $expected)
    {
        $this->assertSame($expected, Uri\remove_segments($this->uri, $keys)->getPath());
    }

    public function validwithoutSegmentProvider()
    {
        return [
            [[1], '/path/the/sky.php'],
        ];
    }

    /**
     * @covers \League\Uri\remove_dot_segments
     * @covers \League\Uri\normalize_path
     */
    public function testWithoutDotSegmentsProcess()
    {
        $uri = Http::createFromString(
            'http://www.example.com/path/../to/the/./sky.php?kingkong=toto&foo=bar+baz#doc3'
        );
        $this->assertSame('/to/the/sky.php', Uri\remove_dot_segments($uri)->getPath());
    }

    /**
     * @covers \League\Uri\remove_empty_segments
     * @covers \League\Uri\normalize_path
     */
    public function testWithoutEmptySegmentsProcess()
    {
        $uri = Http::createFromString(
            'http://www.example.com/path///to/the//sky.php?kingkong=toto&foo=bar+baz#doc3'
        );
        $this->assertSame('/path/to/the/sky.php', Uri\remove_empty_segments($uri)->getPath());
    }

    /**
     * @covers \League\Uri\remove_trailing_slash
     * @covers \League\Uri\normalize_path
     */
    public function testWithoutTrailingSlashProcess()
    {
        $uri = Http::createFromString('http://www.example.com/');
        $this->assertSame('', Uri\remove_trailing_slash($uri)->getPath());
    }

    /**
     * @covers \League\Uri\replace_extension
     *
     * @dataProvider validExtensionProvider
     *
     * @param string $extension
     * @param string $expected
     */
    public function testExtensionProcess(string $extension, string $expected)
    {
        $this->assertSame($expected, Uri\replace_extension($this->uri, $extension)->getPath());
    }

    public function validExtensionProvider()
    {
        return [
            ['csv', '/path/to/the/sky.csv'],
            ['', '/path/to/the/sky'],
        ];
    }

    /**
     * @covers \League\Uri\add_trailing_slash
     */
    public function testWithTrailingSlashProcess()
    {
        $this->assertSame('/path/to/the/sky.php/', Uri\add_trailing_slash($this->uri)->getPath());
    }

    /**
     * @covers \League\Uri\remove_leading_slash
     */
    public function testWithoutLeadingSlashProcess()
    {
        $uri = Http::createFromString('/foo/bar?q=b#h');

        $this->assertSame('foo/bar?q=b#h', (string) Uri\remove_leading_slash($uri));
    }

    /**
     * @covers \League\Uri\add_leading_slash
     */
    public function testWithLeadingSlashProcess()
    {
        $uri = Http::createFromString('foo/bar?q=b#h');

        $this->assertSame('/foo/bar?q=b#h', (string) Uri\add_leading_slash($uri));
    }

    /**
     * @covers \League\Uri\replace_segment
     */
    public function testReplaceSegmentConstructorFailed2()
    {
        $this->expectException(InvalidUriComponent::class);
        Uri\replace_segment($this->uri, 2, "whyno\0t");
    }

    /**
     * @covers \League\Uri\replace_extension
     */
    public function testExtensionProcessFailed()
    {
        $this->expectException(InvalidUriComponent::class);
        Uri\replace_extension($this->uri, 'to/to');
    }
}

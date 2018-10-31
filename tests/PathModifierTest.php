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

namespace LeagueTest\Uri;

use GuzzleHttp\Psr7;
use League\Uri\Component\DataPath;
use League\Uri\Component\Path;
use League\Uri\Data;
use League\Uri\Exception\InvalidUriComponent;
use League\Uri\Http;
use PHPUnit\Framework\TestCase;
use TypeError;
use function League\Uri\add_basepath;
use function League\Uri\add_leading_slash;
use function League\Uri\add_trailing_slash;
use function League\Uri\append_path;
use function League\Uri\datapath_to_ascii;
use function League\Uri\datapath_to_binary;
use function League\Uri\prepend_path;
use function League\Uri\remove_basepath;
use function League\Uri\remove_dot_segments;
use function League\Uri\remove_empty_segments;
use function League\Uri\remove_leading_slash;
use function League\Uri\remove_segments;
use function League\Uri\remove_trailing_slash;
use function League\Uri\replace_basename;
use function League\Uri\replace_data_uri_parameters;
use function League\Uri\replace_dirname;
use function League\Uri\replace_extension;
use function League\Uri\replace_segment;

/**
 * @group path
 */
class PathModifierTest extends TestCase
{
    /**
     * @var Http
     */
    private $uri;

    protected function setUp(): void
    {
        $this->uri = Http::createFromString(
            'http://www.example.com/path/to/the/sky.php?kingkong=toto&foo=bar+baz#doc3'
        );
    }

    /**
     * @covers \League\Uri\datapath_to_binary
     * @covers \League\Uri\filter_uri
     * @covers \League\Uri\normalize_path
     *
     * @dataProvider fileProvider
     *
     */
    public function testToBinary(Data $binary, Data $ascii): void
    {
        self::assertSame((string) $binary, (string) datapath_to_binary($ascii));
    }

    /**
     * @covers \League\Uri\datapath_to_ascii
     * @covers \League\Uri\normalize_path
     *
     * @dataProvider fileProvider
     *
     */
    public function testToAscii(Data $binary, Data $ascii): void
    {
        self::assertSame((string) $ascii, (string) datapath_to_ascii($binary));
    }

    public function fileProvider(): array
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
    public function testDataUriWithParameters(): void
    {
        $uri = Data::createFromString('data:text/plain;charset=us-ascii,Bonjour%20le%20monde!');
        self::assertSame(
            'text/plain;coco=chanel,Bonjour%20le%20monde!',
            replace_data_uri_parameters($uri, 'coco=chanel')->getPath()
        );
    }

    /**
     * @covers \League\Uri\append_path
     * @covers \League\Uri\normalize_path
     *
     * @dataProvider validPathProvider
     *
     */
    public function testAppendProcess(string $segment, int $key, string $append, string $prepend, string $replace): void
    {
        self::assertSame($append, append_path($this->uri, $segment)->getPath());
    }

    /**
     * @covers \League\Uri\append_path
     * @covers \League\Uri\filter_uri
     * @covers \League\Uri\normalize_path
     *
     * @dataProvider validAppendPathProvider
     *
     */
    public function testAppendProcessWithRelativePath(string $uri, string $segment, string $expected): void
    {
        self::assertSame($expected, (string) append_path(Http::createFromString($uri), $segment));
    }

    public function validAppendPathProvider(): array
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
     */
    public function testBasename(string $path, string $uri, string $expected): void
    {
        self::assertSame($expected, (string) replace_basename(Psr7\uri_for($uri), $path));
    }

    public function validBasenameProvider(): array
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
     * @covers \League\Uri\filter_uri
     * @covers \League\Uri\normalize_path
     */
    public function testBasenameThrowTypeError(): void
    {
        self::expectException(TypeError::class);
        replace_basename('http://example.com', 'foo/baz');
    }

    /**
     * @covers \League\Uri\replace_basename
     * @covers \League\Uri\filter_uri
     * @covers \League\Uri\normalize_path
     */
    public function testBasenameThrowException(): void
    {
        self::expectException(InvalidUriComponent::class);
        replace_basename(Psr7\uri_for('http://example.com'), 'foo/baz');
    }

    /**
     * @covers \League\Uri\replace_dirname
     * @covers \League\Uri\normalize_path
     *
     * @dataProvider validDirnameProvider
     *
     */
    public function testDirname(string $path, string $uri, string $expected): void
    {
        self::assertSame($expected, (string) replace_dirname(Psr7\uri_for($uri), $path));
    }

    public function validDirnameProvider(): array
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
     */
    public function testPrependProcess(string $segment, int $key, string $append, string $prepend, string $replace): void
    {
        self::assertSame($prepend, prepend_path($this->uri, $segment)->getPath());
    }

    /**
     * @covers \League\Uri\replace_segment
     * @covers \League\Uri\normalize_path
     *
     * @dataProvider validPathProvider
     *
     */
    public function testReplaceSegmentProcess(string $segment, int $key, string $append, string $prepend, string $replace): void
    {
        self::assertSame($replace, replace_segment($this->uri, $key, $segment)->getPath());
    }

    public function validPathProvider(): array
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
     */
    public function testAddBasePath(string $basepath, string $expected): void
    {
        self::assertSame($expected, add_basepath($this->uri, $basepath)->getPath());
    }

    public function addBasePathProvider(): array
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
    public function testAddBasePathWithRelativePath(): void
    {
        $uri = Http::createFromString('base/path');
        self::assertSame('/base/path', add_basepath($uri, '/base/path')->getPath());
    }

    /**
     * @covers \League\Uri\remove_basepath
     * @covers \League\Uri\normalize_path
     *
     * @dataProvider removeBasePathProvider
     *
     */
    public function testRemoveBasePath(string $basepath, string $expected): void
    {
        self::assertSame($expected, remove_basepath($this->uri, $basepath)->getPath());
    }

    public function removeBasePathProvider(): array
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
    public function testRemoveBasePathWithRelativePath(): void
    {
        $uri = Http::createFromString('base/path');
        self::assertSame('base/path', remove_basepath($uri, '/base/path')->getPath());
    }

    /**
     * @covers \League\Uri\remove_segments
     * @covers \League\Uri\normalize_path
     *
     * @dataProvider validwithoutSegmentProvider
     *
     */
    public function testwithoutSegment(array $keys, string $expected): void
    {
        self::assertSame($expected, remove_segments($this->uri, $keys)->getPath());
    }

    public function validwithoutSegmentProvider(): array
    {
        return [
            [[1], '/path/the/sky.php'],
        ];
    }

    /**
     * @covers \League\Uri\remove_dot_segments
     * @covers \League\Uri\normalize_path
     */
    public function testWithoutDotSegmentsProcess(): void
    {
        $uri = Http::createFromString(
            'http://www.example.com/path/../to/the/./sky.php?kingkong=toto&foo=bar+baz#doc3'
        );
        self::assertSame('/to/the/sky.php', remove_dot_segments($uri)->getPath());
    }

    /**
     * @covers \League\Uri\remove_empty_segments
     * @covers \League\Uri\normalize_path
     */
    public function testWithoutEmptySegmentsProcess(): void
    {
        $uri = Http::createFromString(
            'http://www.example.com/path///to/the//sky.php?kingkong=toto&foo=bar+baz#doc3'
        );
        self::assertSame('/path/to/the/sky.php', remove_empty_segments($uri)->getPath());
    }

    /**
     * @covers \League\Uri\remove_trailing_slash
     * @covers \League\Uri\normalize_path
     */
    public function testWithoutTrailingSlashProcess(): void
    {
        $uri = Http::createFromString('http://www.example.com/');
        self::assertSame('', remove_trailing_slash($uri)->getPath());
    }

    /**
     * @covers \League\Uri\replace_extension
     *
     * @dataProvider validExtensionProvider
     *
     */
    public function testExtensionProcess(string $extension, string $expected): void
    {
        self::assertSame($expected, replace_extension($this->uri, $extension)->getPath());
    }

    public function validExtensionProvider(): array
    {
        return [
            ['csv', '/path/to/the/sky.csv'],
            ['', '/path/to/the/sky'],
        ];
    }

    /**
     * @covers \League\Uri\add_trailing_slash
     */
    public function testWithTrailingSlashProcess(): void
    {
        self::assertSame('/path/to/the/sky.php/', add_trailing_slash($this->uri)->getPath());
    }

    /**
     * @covers \League\Uri\remove_leading_slash
     */
    public function testWithoutLeadingSlashProcess(): void
    {
        $uri = Http::createFromString('/foo/bar?q=b#h');

        self::assertSame('foo/bar?q=b#h', (string) remove_leading_slash($uri));
    }

    /**
     * @covers \League\Uri\add_leading_slash
     */
    public function testWithLeadingSlashProcess(): void
    {
        $uri = Http::createFromString('foo/bar?q=b#h');

        self::assertSame('/foo/bar?q=b#h', (string) add_leading_slash($uri));
    }

    /**
     * @covers \League\Uri\replace_segment
     */
    public function testReplaceSegmentConstructorFailed2(): void
    {
        self::expectException(InvalidUriComponent::class);
        replace_segment($this->uri, 2, "whyno\0t");
    }

    /**
     * @covers \League\Uri\replace_extension
     */
    public function testExtensionProcessFailed(): void
    {
        self::expectException(InvalidUriComponent::class);
        replace_extension($this->uri, 'to/to');
    }
}

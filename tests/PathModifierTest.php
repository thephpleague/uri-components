<?php

/**
 * League.Uri (http://uri.thephpleague.com/components)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.0
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Uri;

use GuzzleHttp\Psr7;
use League\Uri\Component\DataPath;
use League\Uri\Component\Path;
use League\Uri\Exception\MalformedUriComponent;
use League\Uri\Http;
use League\Uri\Uri;
use League\Uri\UriModifier;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * @group path
 * @group resolution
 * @coversDefaultClass \League\Uri\UriModifier
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
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::datapathToBinary
     *
     * @dataProvider fileProvider
     */
    public function testToBinary(Uri $binary, Uri $ascii): void
    {
        self::assertSame((string) $binary, (string) UriModifier::datapathToBinary($ascii));
    }

    /**
     * @covers ::normalizePath
     * @covers ::datapathToAscii
     *
     * @dataProvider fileProvider
     *
     */
    public function testToAscii(Uri $binary, Uri $ascii): void
    {
        self::assertSame((string) $ascii, (string) UriModifier::datapathToAscii($binary));
    }

    public function fileProvider(): array
    {
        $textPath = new DataPath('text/plain;charset=us-ascii,Bonjour%20le%20monde%21');
        $binPath = DataPath::createFromPath(__DIR__.'/Component/data/red-nose.gif');

        $ascii = Uri::createFromString('data:text/plain;charset=us-ascii,Bonjour%20le%20monde%21');
        $binary = Uri::createFromString('data:'.$textPath->toBinary());

        $pathBin = Uri::createFromDataPath(__DIR__.'/Component/data/red-nose.gif');
        $pathAscii = Uri::createFromString('data:'.$binPath->toAscii());

        return [
            [$pathBin, $pathAscii],
            [$binary, $ascii],
        ];
    }

    /**
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::replaceDataUriParameters
     */
    public function testDataUriWithParameters(): void
    {
        $uri = Uri::createFromString('data:text/plain;charset=us-ascii,Bonjour%20le%20monde!');
        self::assertSame(
            'text/plain;coco=chanel,Bonjour%20le%20monde!',
            UriModifier::replaceDataUriParameters($uri, 'coco=chanel')->getPath()
        );
    }

    /**
     * @dataProvider validPathProvider
     *
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::appendSegment
     */
    public function testAppendProcess(string $segment, int $key, string $append, string $prepend, string $replace): void
    {
        self::assertSame($append, UriModifier::appendSegment($this->uri, $segment)->getPath());
    }

    /**
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::appendSegment
     *
     * @dataProvider validappendSegmentProvider
     */
    public function testAppendProcessWithRelativePath(string $uri, string $segment, string $expected): void
    {
        self::assertSame($expected, (string) UriModifier::appendSegment(Http::createFromString($uri), $segment));
    }

    public function validappendSegmentProvider(): array
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
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::replaceBasename
     *
     * @dataProvider validBasenameProvider
     *
     */
    public function testBasename(string $path, string $uri, string $expected): void
    {
        self::assertSame($expected, (string) UriModifier::replaceBasename(Psr7\uri_for($uri), $path));
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
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::replaceBasename
     */
    public function testBasenameThrowTypeError(): void
    {
        self::expectException(TypeError::class);
        UriModifier::replaceBasename('http://example.com', 'foo/baz');
    }

    /**
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::replaceBasename
     */
    public function testBasenameThrowException(): void
    {
        self::expectException(MalformedUriComponent::class);
        UriModifier::replaceBasename(Psr7\uri_for('http://example.com'), 'foo/baz');
    }

    /**
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::replaceDirname
     *
     * @dataProvider validDirnameProvider
     *
     */
    public function testDirname(string $path, string $uri, string $expected): void
    {
        self::assertSame($expected, (string) UriModifier::replaceDirname(Psr7\uri_for($uri), $path));
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
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::prependSegment
     *
     * @dataProvider validPathProvider
     *
     */
    public function testPrependProcess(string $segment, int $key, string $append, string $prepend, string $replace): void
    {
        self::assertSame($prepend, UriModifier::prependSegment($this->uri, $segment)->getPath());
    }

    /**
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::replaceSegment
     *
     * @dataProvider validPathProvider
     *
     */
    public function testReplaceSegmentProcess(string $segment, int $key, string $append, string $prepend, string $replace): void
    {
        self::assertSame($replace, UriModifier::replaceSegment($this->uri, $key, $segment)->getPath());
    }

    public function validPathProvider(): array
    {
        return [
            ['toto', 2, '/path/to/the/sky.php/toto', '/toto/path/to/the/sky.php', '/path/to/toto/sky.php'],
            ['le blanc', 2, '/path/to/the/sky.php/le%20blanc', '/le%20blanc/path/to/the/sky.php', '/path/to/le%20blanc/sky.php'],
        ];
    }

    /**
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::addBasepath
     *
     * @dataProvider addBasepathProvider
     */
    public function testaddBasepath(string $basepath, string $expected): void
    {
        self::assertSame($expected, UriModifier::addBasepath($this->uri, $basepath)->getPath());
    }

    public function addBasepathProvider(): array
    {
        return [
            ['/', '/path/to/the/sky.php'],
            ['', '/path/to/the/sky.php'],
            ['/path/to', '/path/to/the/sky.php'],
            ['/route/to', '/route/to/path/to/the/sky.php'],
        ];
    }

    /**
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::addBasepath
     */
    public function testaddBasepathWithRelativePath(): void
    {
        $uri = Http::createFromString('base/path');
        self::assertSame('/base/path', UriModifier::addBasepath($uri, '/base/path')->getPath());
    }

    /**
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::removeBasepath
     *
     * @dataProvider removeBasePathProvider
     */
    public function testRemoveBasePath(string $basepath, string $expected): void
    {
        self::assertSame($expected, UriModifier::removeBasepath($this->uri, $basepath)->getPath());
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
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::removeBasepath
     */
    public function testRemoveBasePathWithRelativePath(): void
    {
        $uri = Http::createFromString('base/path');
        self::assertSame('base/path', UriModifier::removeBasepath($uri, '/base/path')->getPath());
    }

    /**
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::removeSegments
     *
     * @dataProvider validwithoutSegmentProvider
     *
     */
    public function testwithoutSegment(array $keys, string $expected): void
    {
        self::assertSame($expected, UriModifier::removeSegments($this->uri, ...$keys)->getPath());
    }

    public function validwithoutSegmentProvider(): array
    {
        return [
            [[1], '/path/the/sky.php'],
        ];
    }

    /**
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::removeDotSegments
     */
    public function testWithoutDotSegmentsProcess(): void
    {
        $uri = Http::createFromString(
            'http://www.example.com/path/../to/the/./sky.php?kingkong=toto&foo=bar+baz#doc3'
        );
        self::assertSame('/to/the/sky.php', UriModifier::removeDotSegments($uri)->getPath());
    }

    /**
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::removeEmptySegments
     */
    public function testWithoutEmptySegmentsProcess(): void
    {
        $uri = Http::createFromString(
            'http://www.example.com/path///to/the//sky.php?kingkong=toto&foo=bar+baz#doc3'
        );
        self::assertSame('/path/to/the/sky.php', UriModifier::removeEmptySegments($uri)->getPath());
    }

    /**
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::removeTrailingSlash
     */
    public function testWithoutTrailingSlashProcess(): void
    {
        $uri = Http::createFromString('http://www.example.com/');
        self::assertSame('', UriModifier::removeTrailingSlash($uri)->getPath());
    }

    /**
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::replaceExtension
     *
     * @dataProvider validExtensionProvider
     *
     */
    public function testExtensionProcess(string $extension, string $expected): void
    {
        self::assertSame($expected, UriModifier::replaceExtension($this->uri, $extension)->getPath());
    }

    public function validExtensionProvider(): array
    {
        return [
            ['csv', '/path/to/the/sky.csv'],
            ['', '/path/to/the/sky'],
        ];
    }

    /**
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::addTrailingSlash
     */
    public function testWithTrailingSlashProcess(): void
    {
        self::assertSame('/path/to/the/sky.php/', UriModifier::addTrailingSlash($this->uri)->getPath());
    }

    /**
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::removeLeadingSlash
     */
    public function testWithoutLeadingSlashProcess(): void
    {
        $uri = Http::createFromString('/foo/bar?q=b#h');

        self::assertSame('foo/bar?q=b#h', (string) UriModifier::removeLeadingSlash($uri));
    }

    /**
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::addLeadingSlash
     */
    public function testWithLeadingSlashProcess(): void
    {
        $uri = Http::createFromString('foo/bar?q=b#h');

        self::assertSame('/foo/bar?q=b#h', (string) UriModifier::addLeadingSlash($uri));
    }

    /**
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::replaceSegment
     */
    public function testReplaceSegmentConstructorFailed2(): void
    {
        self::expectException(MalformedUriComponent::class);
        UriModifier::replaceSegment($this->uri, 2, "whyno\0t");
    }

    /**
     * @covers ::filterUri
     * @covers ::normalizePath
     * @covers ::replaceExtension
     */
    public function testExtensionProcessFailed(): void
    {
        self::expectException(MalformedUriComponent::class);
        UriModifier::replaceExtension($this->uri, 'to/to');
    }
}

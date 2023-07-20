<?php

/**
 * League.Uri (https://uri.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Uri;

use League\Uri\Components\DataPath;
use League\Uri\Exceptions\SyntaxError;
use PHPUnit\Framework\TestCase;
use function dirname;

/**
 * @group path
 * @group resolution
 * @coversDefaultClass \League\Uri\UriModifier
 */
final class PathModifierTest extends TestCase
{
    private readonly string $uri;
    private readonly Modifier $modifier;

    protected function setUp(): void
    {
        $this->uri = 'http://www.example.com/path/to/the/sky.php?kingkong=toto&foo=bar+baz#doc3';
        $this->modifier = Modifier::from($this->uri);
    }

    /**
     * @dataProvider fileProvider
     */
    public function testToBinary(Uri $binary, Uri $ascii): void
    {
        self::assertSame((string) $binary, (string) Modifier::from($ascii)->dataPathToBinary());
    }

    /**
     * @dataProvider fileProvider
     */
    public function testToAscii(Uri $binary, Uri $ascii): void
    {
        self::assertSame((string) $ascii, (string) Modifier::from($binary)->dataPathToAscii());
    }

    public static function fileProvider(): array
    {
        $rootPath = dirname(__DIR__).'/test_files';

        $textPath = DataPath::new('text/plain;charset=us-ascii,Bonjour%20le%20monde%21');
        $binPath = DataPath::fromFileContents($rootPath.'/red-nose.gif');

        $ascii = Uri::new('data:text/plain;charset=us-ascii,Bonjour%20le%20monde%21');
        $binary = Uri::new('data:'.$textPath->toBinary());

        $pathBin = Uri::fromFileContents($rootPath.'/red-nose.gif');
        $pathAscii = Uri::new('data:'.$binPath->toAscii());

        return [
            [$pathBin, $pathAscii],
            [$binary, $ascii],
        ];
    }

    public function testDataUriWithParameters(): void
    {
        $uri = Uri::new('data:text/plain;charset=us-ascii,Bonjour%20le%20monde!');
        self::assertSame(
            'text/plain;coco=chanel,Bonjour%20le%20monde!',
            Modifier::from($uri)->replaceDataUriParameters('coco=chanel')->getUri()->getPath()
        );
    }

    /**
     * @dataProvider appendSegmentProvider
     */
    public function testAppendProcess(string $segment, string $append): void
    {
        self::assertSame($append, $this->modifier->appendSegment($segment)->getUri()->getPath());
    }

    public static function appendSegmentProvider(): array
    {
        return [
            ['toto', '/path/to/the/sky.php/toto'],
            ['le blanc', '/path/to/the/sky.php/le%20blanc'],
        ];
    }

    /**
     * @dataProvider validAppendSegmentProvider
     */
    public function testAppendProcessWithRelativePath(string $uri, string $segment, string $expected): void
    {
        self::assertSame($expected, (string) Modifier::from($uri)->appendSegment($segment)->getUri());
    }

    public static function validAppendSegmentProvider(): array
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
     * @dataProvider validBasenameProvider
     */
    public function testBasename(string $path, string $uri, string $expected): void
    {
        self::assertSame($expected, (string) Modifier::from(Uri::new($uri))->replaceBasename($path));
    }

    public static function validBasenameProvider(): array
    {
        return [
            ['baz', 'http://example.com', 'http://example.com/baz'],
            ['baz', 'http://example.com/foo/bar', 'http://example.com/foo/baz'],
            ['baz', 'http://example.com/foo/', 'http://example.com/foo/baz'],
            ['baz', 'http://example.com/foo', 'http://example.com/baz'],
        ];
    }

    public function testBasenameThrowException(): void
    {
        $this->expectException(SyntaxError::class);

        Modifier::from(Uri::new('http://example.com'))->replaceBasename('foo/baz');
    }

    /**
     * @dataProvider validDirnameProvider
     */
    public function testDirname(string $path, string $uri, string $expected): void
    {
        self::assertSame($expected, (string) Modifier::from(Uri::new($uri))->replaceDirname($path));
    }

    public static function validDirnameProvider(): array
    {
        return [
            ['baz', 'http://example.com', 'http://example.com/baz/'],
            ['baz/', 'http://example.com', 'http://example.com/baz/'],
            ['baz', 'http://example.com/foo', 'http://example.com/baz/foo'],
            ['baz', 'http://example.com/foo/yes', 'http://example.com/baz/yes'],
        ];
    }

    /**
     * @dataProvider prependSegmentProvider
     */
    public function testPrependProcess(string $uri, string $segment, string $prepend): void
    {
        $uri = Uri::new($uri);
        self::assertSame($prepend, Modifier::from($uri)->prependSegment($segment)->getUri()->getPath());
    }

    public static function prependSegmentProvider(): array
    {
        return [
            [
                'uri' => 'http://www.example.com/path/to/the/sky.php?kingkong=toto&foo=bar+baz#doc3',
                'segment' => 'toto',
                'expectedPath' => '/toto/path/to/the/sky.php',
            ],
            [
                'uri' => 'http://www.example.com/path/to/the/sky.php?kingkong=toto&foo=bar+baz#doc3',
                'segment' => 'le blanc',
                'expectedPath' => '/le%20blanc/path/to/the/sky.php',
            ],
            [
                'uri' => 'http://example.com/',
                'segment' => 'toto',
                'expectedPath' => '/toto/',
            ],
            [
                'uri' => 'http://example.com',
                'segment' => '/toto',
                'expectedPath' => '/toto/',
            ],
        ];
    }

    /**
     * @dataProvider replaceSegmentProvider
     */
    public function testReplaceSegmentProcess(string $segment, int $key, string $append, string $prepend, string $replace): void
    {
        self::assertSame($replace, $this->modifier->replaceSegment($key, $segment)->getUri()->getPath());
    }

    public static function replaceSegmentProvider(): array
    {
        return [
            ['toto', 2, '/path/to/the/sky.php/toto', '/toto/path/to/the/sky.php', '/path/to/toto/sky.php'],
            ['le blanc', 2, '/path/to/the/sky.php/le%20blanc', '/le%20blanc/path/to/the/sky.php', '/path/to/le%20blanc/sky.php'],
        ];
    }

    /**
     * @dataProvider addBasepathProvider
     */
    public function testaddBasepath(string $basepath, string $expected): void
    {
        self::assertSame($expected, $this->modifier->addBasePath($basepath)->getUri()->getPath());
    }

    public static function addBasepathProvider(): array
    {
        return [
            ['/', '/path/to/the/sky.php'],
            ['', '/path/to/the/sky.php'],
            ['/path/to', '/path/to/the/sky.php'],
            ['/route/to', '/route/to/path/to/the/sky.php'],
        ];
    }

    public function testaddBasepathWithRelativePath(): void
    {
        $uri = Http::new('base/path');
        self::assertSame('/base/path', Modifier::from($uri)->addBasePath('/base/path')->getUri()->getPath());
    }

    /**
     * @dataProvider removeBasePathProvider
     */
    public function testRemoveBasePath(string $basepath, string $expected): void
    {
        self::assertSame($expected, $this->modifier->removeBasePath($basepath)->getUri()->getPath());
    }

    public static function removeBasePathProvider(): array
    {
        return [
            'base path is the leading slash' => ['/', '/path/to/the/sky.php'],
            'base path is the empty string' => ['', '/path/to/the/sky.php'],
            'base path is included in the current path' => ['/path/to', '/the/sky.php'],
            'base path is not included in the current path' => ['/route/to', '/path/to/the/sky.php'],
        ];
    }

    public function testRemoveBasePathWithRelativePath(): void
    {
        $uri = Http::new('base/path');
        self::assertSame('base/path', Modifier::from($uri)->removeBasePath('/base/path')->getUri()->getPath());
    }

    /**
     * @dataProvider validwithoutSegmentProvider
     */
    public function testwithoutSegment(array $keys, string $expected): void
    {
        self::assertSame($expected, $this->modifier->removeSegments(...$keys)->getUri()->getPath());
    }

    public static function validwithoutSegmentProvider(): array
    {
        return [
            [[1], '/path/the/sky.php'],
        ];
    }

    public function testWithoutDotSegmentsProcess(): void
    {
        $uri = Http::new(
            'http://www.example.com/path/../to/the/./sky.php?kingkong=toto&foo=bar+baz#doc3'
        );
        self::assertSame('/to/the/sky.php', Modifier::from($uri)->removeDotSegments()->getUri()->getPath());
    }

    public function testWithoutEmptySegmentsProcess(): void
    {
        $uri = Http::new(
            'http://www.example.com/path///to/the//sky.php?kingkong=toto&foo=bar+baz#doc3'
        );
        self::assertSame('/path/to/the/sky.php', Modifier::from($uri)->removeEmptySegments()->getUri()->getPath());
    }

    public function testWithoutTrailingSlashProcess(): void
    {
        $uri = Http::new('http://www.example.com/');
        self::assertSame('', Modifier::from($uri)->removeTrailingSlash()->getUri()->getPath());
    }

    /**
     * @dataProvider validExtensionProvider
     */
    public function testExtensionProcess(string $extension, string $expected): void
    {
        self::assertSame($expected, $this->modifier->replaceExtension($extension)->getUri()->getPath());
    }

    public static function validExtensionProvider(): array
    {
        return [
            ['csv', '/path/to/the/sky.csv'],
            ['', '/path/to/the/sky'],
        ];
    }

    public function testWithTrailingSlashProcess(): void
    {
        self::assertSame('/path/to/the/sky.php/', $this->modifier->addTrailingSlash()->getUri()->getPath());
    }

    public function testWithoutLeadingSlashProcess(): void
    {
        $uri = Http::new('/foo/bar?q=b#h');

        self::assertSame('foo/bar?q=b#h', (string) Modifier::from($uri)->removeLeadingSlash());
    }

    public function testWithLeadingSlashProcess(): void
    {
        $uri = Http::new('foo/bar?q=b#h');

        self::assertSame('/foo/bar?q=b#h', (string) Modifier::from($uri)->addLeadingSlash());
    }

    public function testReplaceSegmentConstructorFailed2(): void
    {
        $this->expectException(SyntaxError::class);

        $this->modifier->replaceSegment(2, "whyno\0t");
    }

    public function testExtensionProcessFailed(): void
    {
        $this->expectException(SyntaxError::class);

        $this->modifier->replaceExtension('to/to');
    }

    public static function providesInvalidMethodNames(): iterable
    {
        yield 'unknown method' => ['method' => 'unknownMethod'];
        yield 'case sensitive method' => ['method' => 'rePLAceExtenSIOn'];
    }
}

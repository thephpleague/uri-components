<?php

/**
 * League.Uri (https://uri.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Uri\Components;

use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Http;
use League\Uri\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use function base64_encode;
use function dirname;
use function file_get_contents;
use function var_export;

/**
 * @group path
 * @group datapath
 * @coversDefaultClass \League\Uri\Components\DataPath
 */
final class DataPathTest extends TestCase
{
    private string $rootPath;

    public function setUp(): void
    {
        $this->rootPath = dirname(__DIR__, 2).'/test_files';
    }

    /**
     * @covers ::isAbsolute
     */
    public function testIsAbsolute(): void
    {
        $path = DataPath::createFromString(';,Bonjour%20le%20monde!');

        self::assertFalse($path->isAbsolute());
    }

    /**
     * @covers ::withoutDotSegments
     */
    public function testWithoutDotSegments(): void
    {
        $path = DataPath::createFromString(';,Bonjour%20le%20monde%21');

        self::assertEquals($path, $path->withoutDotSegments());
    }

    /**
     * @covers ::withLeadingSlash
     */
    public function testWithLeadingSlash(): void
    {
        $this->expectException(SyntaxError::class);

        DataPath::createFromString(';,Bonjour%20le%20monde%21')->withLeadingSlash();
    }

    /**
     * @covers ::withoutLeadingSlash
     */
    public function testWithoutLeadingSlash(): void
    {
        $path = DataPath::createFromString(';,Bonjour%20le%20monde%21');

        self::assertEquals($path, $path->withoutLeadingSlash());
    }

    /**
     * @covers ::__construct
     */
    public function testConstructorFailedMalformePath(): void
    {
        $this->expectException(SyntaxError::class);

        DataPath::createFromString('€');
    }

    /**
     * @dataProvider invalidDataUriPath
     *
     * @covers ::createFromFilePath
     */
    public function testCreateFromPathFailed(string $path): void
    {
        $this->expectException(SyntaxError::class);

        DataPath::createFromFilePath($path);
    }

    /**
     * @dataProvider invalidDataUriPath
     * @covers ::__construct
     */
    public function testConstructorFailed(string $path): void
    {
        $this->expectException(SyntaxError::class);

        DataPath::createFromString($path);
    }

    public static function invalidDataUriPath(): array
    {
        return [
            'invalid format' => ['/usr/bin/yeah'],
        ];
    }

    /**
     * @covers ::__set_state
     * @covers ::filterPath
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateDocument
     */
    public function testSetState(): void
    {
        $component = DataPath::createFromString(';,Bonjour%20le%20monde%21');
        $generateComponent = eval('return '.var_export($component, true).';');

        self::assertEquals($component, $generateComponent);
    }

    /**
     * @dataProvider validPathContent
     *
     * @covers ::filterPath
     * @covers ::value
     * @covers ::__toString
     */
    public function testDefaultConstructor(string $path, string $expected): void
    {
        self::assertSame($expected, (string) DataPath::createFromString($path));
    }

    public static function validPathContent(): array
    {
        return [
            [
                'path' => 'text/plain;,',
                'expected' => 'text/plain;charset=us-ascii,',
            ],
            [
                'path' => ',',
                'expected' => 'text/plain;charset=us-ascii,',
            ],
            [
                'path' => '',
                'expected' => 'text/plain;charset=us-ascii,',
            ],
        ];
    }

    /**
     * @dataProvider validFilePath
     *
     * @covers ::createFromFilePath
     * @covers ::filterPath
     * @covers ::formatComponent
     * @covers ::getMimeType
     * @covers ::getMediaType
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     */
    public function testCreateFromPath(string $path, string $mimetype, string $mediatype): void
    {
        $uri = DataPath::createFromFilePath($path);

        self::assertSame($mimetype, $uri->getMimeType());
        self::assertSame($mediatype, $uri->getMediaType());
    }

    public static function validFilePath(): array
    {
        $rootPath = dirname(__DIR__, 2).'/test_files';

        return [
            'text file' => [$rootPath.'/hello-world.txt', 'text/plain', 'text/plain;charset=us-ascii'],
            'img file' => [$rootPath.'/red-nose.gif', 'image/gif', 'image/gif;charset=binary'],
        ];
    }

    /**
     * @covers ::withParameters
     * @covers ::filterPath
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     */
    public function testWithParameters(): void
    {
        $uri = DataPath::createFromString('text/plain;charset=us-ascii,Bonjour%20le%20monde%21');
        $newUri = $uri->withParameters('charset=us-ascii');

        self::assertSame($newUri, $uri);
    }

    /**
     * @covers ::withParameters
     * @covers ::filterPath
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     * @covers ::getParameters
     */
    public function testWithParametersOnBinaryData(): void
    {
        $expected = 'charset=binary;foo=bar';
        $uri = DataPath::createFromFilePath($this->rootPath.'/red-nose.gif');
        $newUri = $uri->withParameters($expected);

        self::assertSame($expected, $newUri->getParameters());
    }

    /**
     * @dataProvider invalidParametersString
     *
     * @covers ::withParameters
     * @covers ::filterPath
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     * @covers ::__construct
     */
    public function testWithParametersFailedWithInvalidParameters(string $path, string $parameters): void
    {
        $this->expectException(SyntaxError::class);

        DataPath::createFromFilePath($path)->withParameters($parameters);
    }

    public static function invalidParametersString(): array
    {
        return [
            [
                'path' => __DIR__.'/data/red-nose.gif',
                'parameters' => 'charset=binary;base64',
            ],
            [
                'path' => __DIR__.'/data/hello-world.txt',
                'parameters' => 'charset=binary;base64;foo=bar',
            ],
        ];
    }

    /**
     * @dataProvider fileProvider
     *
     * @covers ::isBinaryData
     * @covers ::formatComponent
     * @covers ::toBinary
     */
    public function testToBinary(DataPath $uri): void
    {
        self::assertTrue($uri->toBinary()->isBinaryData());
    }

    /**
     * @dataProvider fileProvider
     *
     * @covers ::isBinaryData
     * @covers ::formatComponent
     * @covers ::toAscii
     */
    public function testToAscii(DataPath $uri): void
    {
        self::assertFalse($uri->toAscii()->isBinaryData());
    }

    public static function fileProvider(): array
    {
        $rootPath = dirname(__DIR__, 2).'/test_files';

        return [
            'with a file' => [DataPath::createFromFilePath($rootPath.'/red-nose.gif')],
            'with a text' => [DataPath::createFromString('text/plain;charset=us-ascii,Bonjour%20le%20monde%21')],
        ];
    }

    /**
     * @dataProvider invalidParameters
     *
     * @covers ::formatComponent
     * @covers ::withParameters
     * @covers ::__construct
     */
    public function testUpdateParametersFailed(string $parameters): void
    {
        $this->expectException(SyntaxError::class);
        $uri = DataPath::createFromString('text/plain;charset=us-ascii,Bonjour%20le%20monde%21');
        $uri->withParameters($parameters);
    }

    public static function invalidParameters(): array
    {
        return [
            'can not modify binary flag' => ['base64=3'],
            'can not add non empty flag' => ['image/jpg'],
        ];
    }

    /**
     * @covers ::save
     */
    public function testBinarySave(): void
    {
        $newFilePath = $this->rootPath.'/temp.gif';
        $uri = DataPath::createFromFilePath($this->rootPath.'/red-nose.gif');
        $res = $uri->save($newFilePath);

        self::assertSame((string) $uri, (string) DataPath::createFromFilePath($newFilePath));

        // Ensure file handle of \SplFileObject gets closed.
        unset($res);
        unlink($newFilePath);
    }

    /**
     * @covers ::createFromFilePath
     * @covers ::save
     * @covers ::getData
     */
    public function testRawSave(): void
    {
        $context = stream_context_create([
            'http'=> [
                'method' => 'GET',
                'header' => "Accept-language: en\r\nCookie: foo=bar\r\n",
            ],
        ]);

        $newFilePath = $this->rootPath.'/temp.txt';
        $uri = DataPath::createFromFilePath($this->rootPath.'/hello-world.txt', $context);

        $res = $uri->save($newFilePath);
        self::assertSame((string) $uri, (string) DataPath::createFromFilePath($newFilePath));
        $data = file_get_contents($newFilePath);
        self::assertSame(base64_encode((string) $data), $uri->getData());

        // Ensure file handle of \SplFileObject gets closed.
        unset($res);
        unlink($newFilePath);
    }

    /**
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     * @covers ::__construct
     */
    public function testDataPathConstructor(): void
    {
        self::assertSame('text/plain;charset=us-ascii,', (string) new DataPath());
    }

    /**
     * @covers ::filterPath
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     * @covers ::__construct
     */
    public function testInvalidBase64Encoded(): void
    {
        $this->expectException(SyntaxError::class);

        DataPath::createFromString('text/plain;charset=us-ascii;base64,boulook%20at%20me');
    }

    /**
     * @covers ::filterPath
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     * @covers ::__construct
     */
    public function testInvalidComponent(): void
    {
        $this->expectException(SyntaxError::class);

        DataPath::createFromString("data:text/plain;charset=us-ascii,bou\nlook%20at%20me");
    }

    /**
     * @covers ::filterPath
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     * @covers ::__construct
     */
    public function testInvalidString(): void
    {
        $this->expectException(SyntaxError::class);

        DataPath::createFromString('text/plain;boulook€');
    }

    /**
     * @covers ::filterPath
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     * @covers ::__construct
     */
    public function testInvalidMimetype(): void
    {
        $this->expectException(SyntaxError::class);

        DataPath::createFromString('data:toto\\bar;foo=bar,');
    }


    /**
     * @dataProvider getURIProvider
     *
     * @covers ::createFromUri
     */
    public function testCreateFromUri(Psr7UriInterface|UriInterface $uri, ?string $expected): void
    {
        $path = DataPath::createFromUri($uri);

        self::assertSame($expected, $path->value());
    }

    public static function getURIProvider(): iterable
    {
        return [
            'PSR-7 URI object' => [
                'uri' => Http::createFromString('data:text/plain;charset=us-ascii,Bonjour%20le%20monde%21'),
                'expected' => 'text/plain;charset=us-ascii,Bonjour%20le%20monde%21',
            ],
            'PSR-7 URI object with no path' => [
                'uri' => Http::createFromString(),
                'expected' => 'text/plain;charset=us-ascii,',
            ],
            'League URI object' => [
                'uri' => Uri::createFromString('data:text/plain;charset=us-ascii,Bonjour%20le%20monde%21'),
                'expected' => 'text/plain;charset=us-ascii,Bonjour%20le%20monde%21',
            ],
            'League URI object with no path' => [
                'uri' => Uri::createFromString(),
                'expected' => 'text/plain;charset=us-ascii,',
            ],
        ];
    }

    public function testHasTrailingSlash(): void
    {
        self::assertFalse(DataPath::createFromString('text/plain;charset=us-ascii,')->hasTrailingSlash());
    }

    public function testWithTrailingSlash(): void
    {
        $path = DataPath::createFromString('text/plain;charset=us-ascii,')->withTrailingSlash();

        self::assertSame('text/plain;charset=us-ascii,/', (string) $path);
        self::assertSame($path, $path->withTrailingSlash());
    }

    public function testWithoutTrailingSlash(): void
    {
        $path = DataPath::createFromString('text/plain;charset=us-ascii,/')->withoutTrailingSlash();

        self::assertSame('text/plain;charset=us-ascii,', (string) $path);
        self::assertSame($path, $path->withoutTrailingSlash());
    }

    public function testDecoded(): void
    {
        $encodedPath = 'text/plain;charset=us-ascii,Bonjour%20le%20monde%21';
        $decodedPath = 'text/plain;charset=us-ascii,Bonjour le monde%21';
        $path = DataPath::createFromString($encodedPath);

        self::assertSame($encodedPath, $path->value());
        self::assertSame($decodedPath, $path->decoded());
    }
}

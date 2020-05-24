<?php

/**
 * League.Uri (http://uri.thephpleague.com/components)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.2
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Uri\Components;

use League\Uri\Components\DataPath;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Http;
use League\Uri\Uri;
use PHPUnit\Framework\TestCase;
use TypeError;
use function base64_encode;
use function file_get_contents;
use function var_export;

/**
 * @group path
 * @group datapath
 * @coversDefaultClass \League\Uri\Components\DataPath
 */
class DataPathTest extends TestCase
{
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
        self::expectException(SyntaxError::class);

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
     * @covers ::filterPath
     * @covers ::__construct
     */
    public function testConstructorFailedWithNullValue(): void
    {
        self::expectException(SyntaxError::class);

        new DataPath(null);
    }

    /**
     * @covers ::__construct
     */
    public function testConstructorFailedMalformePath(): void
    {
        self::expectException(SyntaxError::class);

        DataPath::createFromString('€');
    }

    /**
     * @dataProvider invalidDataUriPath
     *
     * @covers ::createFromFilePath
     * @covers ::createFromPath
     *
     */
    public function testCreateFromPathFailed(string $path): void
    {
        self::expectException(SyntaxError::class);

        DataPath::createFromPath($path);
    }

    /**
     * @dataProvider invalidDataUriPath
     * @param string $path
     * @covers ::__construct
     */
    public function testConstructorFailed($path): void
    {
        self::expectException(SyntaxError::class);

        DataPath::createFromString($path);
    }

    public function invalidDataUriPath(): array
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
     * @covers ::withContent
     * @covers ::filterPath
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateDocument
     */
    public function testWithPath(): void
    {
        $path = DataPath::createFromString('text/plain;charset=us-ascii,Bonjour%20le%20monde%21');

        self::assertSame($path, $path->withContent($path));
        self::assertNotSame($path, $path->withContent(''));
    }

    /**
     * @dataProvider validPathContent
     *
     * @covers ::filterPath
     * @covers ::getContent
     * @covers ::__toString
     */
    public function testDefaultConstructor(string $path, string $expected): void
    {
        self::assertSame($expected, (string) DataPath::createFromString($path));
    }

    public function validPathContent(): array
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
     * @covers ::createFromPath
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
        $uri = DataPath::createFromPath(__DIR__.'/data/'.$path);

        self::assertSame($mimetype, $uri->getMimeType());
        self::assertSame($mediatype, $uri->getMediaType());
    }

    public function validFilePath(): array
    {
        return [
            'text file' => ['hello-world.txt', 'text/plain', 'text/plain;charset=us-ascii'],
            'img file' => ['red-nose.gif', 'image/gif', 'image/gif;charset=binary'],
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
        $uri = DataPath::createFromPath(__DIR__.'/data/red-nose.gif');
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
        self::expectException(SyntaxError::class);

        DataPath::createFromPath($path)->withParameters($parameters);
    }

    public function invalidParametersString(): array
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

    public function testWithParametersFailsWithWrongType(): void
    {
        self::expectException(TypeError::class);

        DataPath::createFromFilePath(__DIR__.'/data/red-nose.gif')->withParameters([]);
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

    public function fileProvider(): array
    {
        return [
            'with a file' => [DataPath::createFromPath(__DIR__.'/data/red-nose.gif')],
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
        self::expectException(SyntaxError::class);
        $uri = DataPath::createFromString('text/plain;charset=us-ascii,Bonjour%20le%20monde%21');
        $uri->withParameters($parameters);
    }

    public function invalidParameters(): array
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
        $newFilePath = __DIR__.'/data/temp.gif';
        $uri = DataPath::createFromPath(__DIR__.'/data/red-nose.gif');
        $res = $uri->save($newFilePath);

        self::assertSame((string) $uri, (string) DataPath::createFromPath($newFilePath));

        // Ensure file handle of \SplFileObject gets closed.
        unset($res);
        unlink($newFilePath);
    }

    /**
     * @covers ::createFromFilePath
     * @covers ::createFromPath
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

        $newFilePath = __DIR__.'/data/temp.txt';
        $uri = DataPath::createFromPath(__DIR__.'/data/hello-world.txt', $context);

        $res = $uri->save($newFilePath);
        self::assertSame((string) $uri, (string) DataPath::createFromPath($newFilePath));
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
        self::expectException(SyntaxError::class);

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
        self::expectException(SyntaxError::class);

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
        self::expectException(SyntaxError::class);

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
        self::expectException(SyntaxError::class);

        DataPath::createFromString('data:toto\\bar;foo=bar,');
    }


    /**
     * @dataProvider getURIProvider
     *
     * @covers ::createFromUri
     *
     * @param mixed   $uri      an URI object
     * @param ?string $expected
     */
    public function testCreateFromUri($uri, ?string $expected): void
    {
        $path = DataPath::createFromUri($uri);

        self::assertSame($expected, $path->getContent());
    }

    public function getURIProvider(): iterable
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

    public function testCreateFromUriThrowsTypeError(): void
    {
        self::expectException(TypeError::class);

        DataPath::createFromUri('http://example.com:80');
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

        self::assertSame($encodedPath, $path->getContent());
        self::assertSame($decodedPath, $path->decoded());
    }

    public function testCreateFromStringThrowsTypeError(): void
    {
        self::expectException(TypeError::class);

        DataPath::createFromString(new \stdClass());
    }
}

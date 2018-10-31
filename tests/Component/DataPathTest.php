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

namespace LeagueTest\Uri\Component;

use League\Uri\Component\DataPath as Path;
use League\Uri\Exception\InvalidUriComponent;
use League\Uri\Exception\PathNotFound;
use PHPUnit\Framework\TestCase;
use SplFileObject;

/**
 * @group path
 * @group datapath
 * @coversDefaultClass \League\Uri\Component\DataPath
 */
class DataPathTest extends TestCase
{
    /**
     * @dataProvider invalidDataUriPath
     * @covers ::createFromPath
     * @param string $path
     */
    public function testCreateFromPathFailed($path): void
    {
        self::expectException(PathNotFound::class);
        Path::createFromPath($path);
    }

    /**
     * @dataProvider invalidDataUriPath
     * @covers ::parse
     * @param string $path
     */
    public function testConstructorFailed($path): void
    {
        self::expectException(InvalidUriComponent::class);
        new Path($path);
    }

    public function invalidDataUriPath(): array
    {
        return [
            'invalid format' => ['/usr/bin/yeah'],
        ];
    }

    /**
     * @covers ::__set_state
     * @covers ::validate
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateDocument
     */
    public function testSetState(): void
    {
        $component = new Path(';,Bonjour%20le%20monde%21');
        $generateComponent = eval('return '.var_export($component, true).';');
        self::assertEquals($component, $generateComponent);
    }

    /**
     * @covers ::withContent
     * @covers ::parse
     * @covers ::validate
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateDocument
     */
    public function testWithPath(): void
    {
        $path = new Path('text/plain;charset=us-ascii,Bonjour%20le%20monde%21');
        self::assertSame($path, $path->withContent($path));
        self::assertNotSame($path, $path->withContent(''));
    }

    /**
     * @dataProvider validPathContent
     * @param string $path
     * @param string $expected
     * @covers ::validate
     * @covers ::__toString
     */
    public function testDefaultConstructor($path, $expected): void
    {
        self::assertSame($expected, (string) (new Path($path)));
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
     * @param string $path
     * @param string $mimetype
     * @param string $mediatype
     * @covers ::createFromPath
     * @covers ::parse
     * @covers ::validate
     * @covers ::formatComponent
     * @covers ::getMimeType
     * @covers ::getMediaType
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     */
    public function testCreateFromPath($path, $mimetype, $mediatype)
    {
        $uri = Path::createFromPath(__DIR__.'/data/'.$path);
        self::assertSame($mimetype, $uri->getMimeType());
        self::assertSame($mediatype, $uri->getMediaType());
    }

    public function validFilePath()
    {
        return [
            'text file' => ['hello-world.txt', 'text/plain', 'text/plain;charset=us-ascii'],
            'img file' => ['red-nose.gif', 'image/gif', 'image/gif;charset=binary'],
        ];
    }

    /**
     * @covers ::withParameters
     * @covers ::validate
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     */
    public function testWithParameters()
    {
        $uri = new Path('text/plain;charset=us-ascii,Bonjour%20le%20monde%21');
        $newUri = $uri->withParameters('charset=us-ascii');
        self::assertSame($newUri, $uri);
    }

    /**
     * @covers ::withParameters
     * @covers ::validate
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     * @covers ::getParameters
     */
    public function testWithParametersOnBinaryData()
    {
        $expected = 'charset=binary;foo=bar';
        $uri = Path::createFromPath(__DIR__.'/data/red-nose.gif');
        $newUri = $uri->withParameters($expected);
        self::assertSame($expected, $newUri->getParameters());
    }

    /**
     * @dataProvider invalidParametersString
     *
     * @param string $path
     * @param string $parameters
     * @covers ::parse
     * @covers ::withParameters
     * @covers ::validate
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     */
    public function testWithParametersFailedWithInvalidParameters($path, $parameters)
    {
        self::expectException(InvalidUriComponent::class);
        Path::createFromPath($path)->withParameters($parameters);
    }

    public function invalidParametersString()
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
     * @param Path $uri
     * @covers ::isBinaryData
     * @covers ::formatComponent
     * @covers ::toBinary
     */
    public function testToBinary($uri)
    {
        self::assertTrue($uri->toBinary()->isBinaryData());
    }

    /**
     * @dataProvider fileProvider
     * @param Path $uri
     * @covers ::isBinaryData
     * @covers ::formatComponent
     * @covers ::toAscii
     */
    public function testToAscii($uri)
    {
        self::assertFalse($uri->toAscii()->isBinaryData());
    }

    public function fileProvider()
    {
        return [
            'with a file' => [Path::createFromPath(__DIR__.'/data/red-nose.gif')],
            'with a text' => [new Path('text/plain;charset=us-ascii,Bonjour%20le%20monde%21')],
        ];
    }

    /**
     * @dataProvider invalidParameters
     * @param string $parameters
     * @covers ::parse
     * @covers ::formatComponent
     * @covers ::withParameters
     */
    public function testUpdateParametersFailed($parameters)
    {
        self::expectException(InvalidUriComponent::class);
        $uri = new Path('text/plain;charset=us-ascii,Bonjour%20le%20monde%21');
        $uri->withParameters($parameters);
    }

    public function invalidParameters()
    {
        return [
            'can not modify binary flag' => ['base64=3'],
            'can not add non empty flag' => ['image/jpg'],
        ];
    }

    /**
     * @covers ::save
     */
    public function testBinarySave()
    {
        $newFilePath = __DIR__.'/data/temp.gif';
        $uri = Path::createFromPath(__DIR__.'/data/red-nose.gif');
        $res = $uri->save($newFilePath);
        self::assertInstanceOf(SplFileObject::class, $res);
        $res = null;
        self::assertSame((string) $uri, (string) Path::createFromPath($newFilePath));

        // Ensure file handle of \SplFileObject gets closed.
        $res = null;
        unlink($newFilePath);
    }

    /**
     * @covers ::createFromPath
     * @covers ::save
     * @covers ::getData
     */
    public function testRawSave()
    {
        $context = stream_context_create([
            'http'=> [
                'method' => 'GET',
                'header' => "Accept-language: en\r\nCookie: foo=bar\r\n",
            ],
        ]);

        $newFilePath = __DIR__.'/data/temp.txt';
        $uri = Path::createFromPath(__DIR__.'/data/hello-world.txt', $context);
        $res = $uri->save($newFilePath);
        self::assertInstanceOf(SplFileObject::class, $res);
        self::assertSame((string) $uri, (string) Path::createFromPath($newFilePath));
        $data = file_get_contents($newFilePath);
        self::assertSame(base64_encode((string) $data), $uri->getData());

        // Ensure file handle of \SplFileObject gets closed.
        $res = null;
        unlink($newFilePath);
    }

    /**
     * @covers ::validate
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     */
    public function testDataPathConstructor()
    {
        self::assertSame('text/plain;charset=us-ascii,', (string) new Path());
    }

    /**
     * @covers ::parse
     * @covers ::validate
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     */
    public function testInvalidBase64Encoded()
    {
        self::expectException(InvalidUriComponent::class);
        new Path('text/plain;charset=us-ascii;base64,boulook%20at%20me');
    }

    /**
     * @covers ::parse
     * @covers ::validate
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     */
    public function testInvalidComponent()
    {
        self::expectException(InvalidUriComponent::class);
        new Path("data:text/plain;charset=us-ascii,bou\nlook%20at%20me");
    }

    /**
     * @covers ::parse
     * @covers ::validate
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     */
    public function testInvalidString()
    {
        self::expectException(InvalidUriComponent::class);
        new Path('text/plain;boulook€');
    }

    /**
     * @covers ::parse
     * @covers ::validate
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     */
    public function testInvalidMimetype()
    {
        self::expectException(InvalidUriComponent::class);
        new Path('data:toto\\bar;foo=bar,');
    }
}

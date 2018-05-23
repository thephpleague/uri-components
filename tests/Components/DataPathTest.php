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

namespace LeagueTest\Uri\Components;

use League\Uri\Components\DataPath as Path;
use League\Uri\Exception\InvalidUriComponent;
use League\Uri\Exception\PathNotFound;
use PHPUnit\Framework\TestCase;
use SplFileObject;

/**
 * @group path
 * @group datapath
 * @coversDefaultClass \League\Uri\Components\DataPath
 */
class DataPathTest extends TestCase
{
    /**
     * @dataProvider invalidDataUriPath
     * @covers ::createFromPath
     * @param string $path
     */
    public function testCreateFromPathFailed($path)
    {
        $this->expectException(PathNotFound::class);
        Path::createFromPath($path);
    }

    /**
     * @dataProvider invalidDataUriPath
     * @param string $path
     * @covers ::parse
     */
    public function testConstructorFailed($path)
    {
        $this->expectException(InvalidUriComponent::class);
        new Path($path);
    }

    public function invalidDataUriPath()
    {
        return [
            'invalid format' => ['/usr/bin/yeah'],
        ];
    }

    /**
     * @covers ::__set_state
     * @covers ::validate
     * @covers ::parse
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateDocument
     */
    public function testSetState()
    {
        $component = new Path(';,Bonjour%20le%20monde%21');
        $generateComponent = eval('return '.var_export($component, true).';');
        $this->assertEquals($component, $generateComponent);
    }

    /**
     * @covers ::__debugInfo
     */
    public function testDebugInfo()
    {
        $component = new Path(';,Bonjour%20le%20monde%21');
        $debugInfo = $component->__debugInfo();
        $this->assertArrayHasKey('component', $debugInfo);
        $this->assertSame($component->getContent(), $debugInfo['component']);
    }

    /**
     * @covers ::withContent
     * @covers ::__construct
     * @covers ::validate
     * @covers ::parse
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateDocument
     */
    public function testWithPath()
    {
        $path = new Path('text/plain;charset=us-ascii,Bonjour%20le%20monde%21');
        $this->assertSame($path, $path->withContent($path));
        $this->assertNotSame($path, $path->withContent(''));
    }

    /**
     * @dataProvider validPathContent
     * @param string $path
     * @param string $expected
     * @covers ::validate
     * @covers ::__toString
     */
    public function testDefaultConstructor($path, $expected)
    {
        $this->assertSame($expected, (string) (new Path($path)));
    }

    public function validPathContent()
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
     * @covers ::__construct
     * @covers ::validate
     * @covers ::format
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
        $this->assertSame($mimetype, $uri->getMimeType());
        $this->assertSame($mediatype, $uri->getMediaType());
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
     * @covers ::parse
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     */
    public function testWithParameters()
    {
        $uri = new Path('text/plain;charset=us-ascii,Bonjour%20le%20monde%21');
        $newUri = $uri->withParameters('charset=us-ascii');
        $this->assertSame($newUri, $uri);
    }

    /**
     * @covers ::withParameters
     * @covers ::validate
     * @covers ::parse
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
        $this->assertSame($expected, $newUri->getParameters());
    }

    /**
     * @dataProvider invalidParametersString
     *
     * @param string $path
     * @param string $parameters
     * @covers ::withParameters
     * @covers ::validate
     * @covers ::parse
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     */
    public function testWithParametersFailedWithInvalidParameters($path, $parameters)
    {
        $this->expectException(InvalidUriComponent::class);
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
     * @covers ::format
     * @covers ::toBinary
     */
    public function testToBinary($uri)
    {
        $this->assertTrue($uri->toBinary()->isBinaryData());
    }

    /**
     * @dataProvider fileProvider
     * @param Path $uri
     * @covers ::isBinaryData
     * @covers ::format
     * @covers ::toAscii
     */
    public function testToAscii($uri)
    {
        $this->assertFalse($uri->toAscii()->isBinaryData());
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
     * @covers ::format
     * @covers ::withParameters
     */
    public function testUpdateParametersFailed($parameters)
    {
        $this->expectException(InvalidUriComponent::class);
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
        $this->assertInstanceOf(SplFileObject::class, $res);
        $res = null;
        $this->assertSame((string) $uri, (string) Path::createFromPath($newFilePath));

        // Ensure file handle of \SplFileObject gets closed.
        $res = null;
        unlink($newFilePath);
    }

    /**
     * @covers ::save
     * @covers ::getData
     */
    public function testRawSave()
    {
        $newFilePath = __DIR__.'/data/temp.txt';
        $uri = Path::createFromPath(__DIR__.'/data/hello-world.txt');
        $res = $uri->save($newFilePath);
        $this->assertInstanceOf(SplFileObject::class, $res);
        $this->assertSame((string) $uri, (string) Path::createFromPath($newFilePath));
        $data = file_get_contents($newFilePath);
        $this->assertSame(base64_encode($data), $uri->getData());

        // Ensure file handle of \SplFileObject gets closed.
        $res = null;
        unlink($newFilePath);
    }

    /**
     * @covers ::validate
     * @covers ::parse
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     */
    public function testDataPathConstructor()
    {
        $this->assertSame('text/plain;charset=us-ascii,', (string) new Path());
    }

    /**
     * @covers ::validate
     * @covers ::parse
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     */
    public function testInvalidBase64Encoded()
    {
        $this->expectException(InvalidUriComponent::class);
        new Path('text/plain;charset=us-ascii;base64,boulook%20at%20me');
    }

    /**
     * @covers ::validate
     * @covers ::parse
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     */
    public function testInvalidComponent()
    {
        $this->expectException(InvalidUriComponent::class);
        new Path("data:text/plain;charset=us-ascii,bou\nlook%20at%20me");
    }

    /**
     * @covers ::validate
     * @covers ::parse
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     */
    public function testInvalidString()
    {
        $this->expectException(InvalidUriComponent::class);
        new Path('text/plain;boulook€');
    }

    /**
     * @covers ::validate
     * @covers ::parse
     * @covers ::filterMimeType
     * @covers ::filterParameters
     * @covers ::validateParameter
     * @covers ::validateDocument
     */
    public function testInvalidMimetype()
    {
        $this->expectException(InvalidUriComponent::class);
        new Path('data:toto\\bar;foo=bar,');
    }
}

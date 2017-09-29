<?php

namespace LeagueTest\Uri\Components;

use League\Uri\Components\DataPath as Path;
use League\Uri\Components\Exception;
use PHPUnit\Framework\TestCase;
use SplFileObject;

/**
 * @group path
 * @group datapath
 */
class DataPathTest extends TestCase
{
    /**
     * @dataProvider invalidDataUriPath
     * @param $path
     */
    public function testCreateFromPathFailed($path)
    {
        $this->expectException(Exception::class);
        Path::createFromPath($path);
    }

    /**
     * @dataProvider invalidDataUriPath
     * @param string $path
     */
    public function testConstructorFailed($path)
    {
        $this->expectException(Exception::class);
        new Path($path);
    }

    public function testSetState()
    {
        $component = new Path('text/plain;charset=us-ascii,Bonjour%20le%20monde%21');
        $generateComponent = eval('return '.var_export($component, true).';');
        $this->assertEquals($component, $generateComponent);
    }

    public function invalidDataUriPath()
    {
        return [
            'invalid format' => ['/usr/bin/yeah'],
        ];
    }

    public function testWithPath()
    {
        $uri = new Path('text/plain;charset=us-ascii,Bonjour%20le%20monde%21');
        $this->assertSame($uri, $uri->withContent((string) $uri));
    }

    public function testDebugInfo()
    {
        $component = new Path('text/plain;charset=us-ascii,Bonjour%20le%20monde%21');
        $this->assertInternalType('array', $component->__debugInfo());
    }

    /**
     * @dataProvider validPathContent
     * @param string $path
     * @param string $expected
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

    public function testWithParameters()
    {
        $uri = new Path('text/plain;charset=us-ascii,Bonjour%20le%20monde%21');
        $newUri = $uri->withParameters('charset=us-ascii');
        $this->assertSame($newUri, $uri);
    }

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
     */
    public function testWithParametersFailedWithInvalidParameters($path, $parameters)
    {
        $this->expectException(Exception::class);
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

    public function testInvalidSetterThrowException()
    {
        $this->expectException(Exception::class);
        $path = new Path();
        $path->unknownProperty = true;
    }

    public function testInvalidGetterThrowException()
    {
        $this->expectException(Exception::class);
        $path = new Path();
        $path->unknownProperty;
    }

    public function testInvalidIssetThrowException()
    {
        $this->expectException(Exception::class);
        $path = new Path();
        isset($path->unknownProperty);
    }

    public function testInvalidUnssetThrowException()
    {
        $this->expectException(Exception::class);
        $path = new Path();
        unset($path->unknownProperty);
    }

    /**
     * @dataProvider fileProvider
     * @param $uri
     */
    public function testToBinary($uri)
    {
        $this->assertTrue($uri->toBinary()->isBinaryData());
    }

    /**
     * @dataProvider fileProvider
     * @param $uri
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
     */
    public function testUpdateParametersFailed($parameters)
    {
        $this->expectException(Exception::class);
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

    public function testDataPathConstructor()
    {
        $this->assertSame('text/plain;charset=us-ascii,', (string) new Path());
    }

    public function testInvalidBase64Encoded()
    {
        $this->expectException(Exception::class);
        new Path('text/plain;charset=us-ascii;base64,boulook%20at%20me');
    }

    public function testInvalidComponent()
    {
        $this->expectException(Exception::class);
        new Path("data:text/plain;charset=us-ascii,bou\nlook%20at%20me");
    }

    public function testInvalidMimetype()
    {
        $this->expectException(Exception::class);
        new Path('data:toto\\bar;foo=bar,');
    }
}

<?php

namespace LeagueTest\Uri\Components;

use League\Uri\Components\Exception;
use League\Uri\Components\Scheme;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * @group scheme
 * @coversDefaultClass \League\Uri\Components\Scheme
 */
class SchemeTest extends TestCase
{
    /**
     * @covers ::__set_state
     * @covers ::__construct
     */
    public function testSetState()
    {
        $component = new Scheme('ignace');
        $generateComponent = eval('return '.var_export($component, true).';');
        $this->assertEquals($component, $generateComponent);
    }

    /**
     * @covers ::withContent
     * @covers ::getContent
     * @covers ::__toString
     * @covers ::validate
     * @covers ::getUriComponent
     */
    public function testWithValue()
    {
        $scheme = new Scheme('ftp');
        $http_scheme = $scheme->withContent('HTTP');
        $this->assertSame('http', $http_scheme->getContent());
        $this->assertSame('http', (string) $http_scheme);
        $this->assertSame('http:', $http_scheme->getUriComponent());
    }

    /**
     * @covers ::withContent
     * @covers ::validate
     */
    public function testWithContent()
    {
        $scheme = new Scheme('ftp');
        $this->assertSame($scheme, $scheme->withContent('FtP'));
        $this->assertNotSame($scheme, $scheme->withContent('Http'));
    }

    /**
     * @covers ::__debugInfo
     */
    public function testDebugInfo()
    {
        $this->assertInternalType('array', (new Scheme('ftp'))->__debugInfo());
    }

    /**
     * @covers ::getUriComponent
     */
    public function testEmptyScheme()
    {
        $scheme = new Scheme();
        $this->assertSame('', (string) $scheme);
        $this->assertSame('', $scheme->getUriComponent());
    }

    /**
     * @dataProvider validSchemeProvider
     * @param null|string $scheme
     * @param string      $toString
     * @covers ::validate
     * @covers ::__toString
     */
    public function testValidScheme($scheme, $toString)
    {
        $this->assertSame($toString, (string) new Scheme($scheme));
    }

    public function validSchemeProvider()
    {
        return [
            [null, ''],
            [new Scheme('foo'), 'foo'],
            [new class() {
                public function __toString()
                {
                    return 'foo';
                }
            }, 'foo'],
            ['a', 'a'],
            ['ftp', 'ftp'],
            ['HtTps', 'https'],
            ['wSs', 'wss'],
            ['telnEt', 'telnet'],
        ];
    }

    /**
     * @param string $scheme
     * @dataProvider invalidSchemeProvider
     * @covers ::validate
     */
    public function testInvalidScheme($scheme)
    {
        $this->expectException(Exception::class);
        new Scheme($scheme);
    }

    public function invalidSchemeProvider()
    {
        return [
            'empty string' => [''],
            'invalid char' => ['in,valid'],
            'integer like string' => ['123'],
        ];
    }

    /**
     * @covers ::getContent
     */
    public function testInvalidEncodingTypeThrowException()
    {
        $this->expectException(Exception::class);
        (new Scheme('http'))->getContent(-1);
    }

    public function testInvalidSchemeType()
    {
        $this->expectException(TypeError::class);
        new Scheme(date_create());
    }
}

<?php

namespace LeagueTest\Uri\Components;

use League\Uri\Components\Exception;
use League\Uri\Components\Scheme;
use PHPUnit\Framework\TestCase;

/**
 * @group scheme
 */
class SchemeTest extends TestCase
{
    public function testSetState()
    {
        $component = new Scheme('ignace');
        $generateComponent = eval('return '.var_export($component, true).';');
        $this->assertEquals($component, $generateComponent);
    }

    public function testWithValue()
    {
        $scheme = new Scheme('ftp');
        $http_scheme = $scheme->withContent('HTTP');
        $this->assertSame('http', $http_scheme->__toString());
        $this->assertSame('http:', $http_scheme->getUriComponent());
    }

    public function testEmptyScheme()
    {
        $scheme = new Scheme();
        $this->assertSame('', (string) $scheme);
        $this->assertSame('', $scheme->getUriComponent());
    }

    /**
     * @dataProvider validSchemeProvider
     * @param $scheme
     * @param $toString
     */
    public function testValidScheme($scheme, $toString)
    {
        $this->assertSame($toString, (new Scheme($scheme))->__toString());
    }

    public function validSchemeProvider()
    {
        return [
            [null, ''],
            ['a', 'a'],
            ['ftp', 'ftp'],
            ['HtTps', 'https'],
            ['wSs', 'wss'],
            ['telnEt', 'telnet'],
        ];
    }

    /**
     * @param  $scheme
     * @dataProvider invalidSchemeProvider
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

    public function testInvalidEncodingTypeThrowException()
    {
        $this->expectException(Exception::class);
        (new Scheme('http'))->getContent(-1);
    }
}

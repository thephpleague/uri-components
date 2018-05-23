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

use League\Uri\Components\Scheme;
use League\Uri\Exception\InvalidComponentArgument;
use League\Uri\Exception\UnknownEncoding;
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
     * @covers ::__debugInfo
     */
    public function testDebugInfo()
    {
        $component = new Scheme('ftp');
        $debugInfo = $component->__debugInfo();
        $this->assertArrayHasKey('component', $debugInfo);
        $this->assertSame($component->getContent(), $debugInfo['component']);
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
        $this->expectException(InvalidComponentArgument::class);
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
        $this->expectException(UnknownEncoding::class);
        (new Scheme('http'))->getContent(-1);
    }

    public function testInvalidSchemeType()
    {
        $this->expectException(TypeError::class);
        new Scheme(date_create());
    }
}

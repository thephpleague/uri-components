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

use League\Uri\Component\Scheme;
use League\Uri\Exception\InvalidUriComponent;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * @group scheme
 * @coversDefaultClass \League\Uri\Component\Scheme
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
        self::assertEquals($component, $generateComponent);
    }

    /**
     * @covers ::withContent
     * @covers ::getContent
     * @covers ::__toString
     * @covers ::validate
     */
    public function testWithValue()
    {
        $scheme = new Scheme('ftp');
        $http_scheme = $scheme->withContent('HTTP');
        self::assertSame('http', $http_scheme->getContent());
        self::assertSame('http', (string) $http_scheme);
    }

    /**
     * @covers ::withContent
     * @covers ::validate
     */
    public function testWithContent()
    {
        $scheme = new Scheme('ftp');
        self::assertSame($scheme, $scheme->withContent('FtP'));
        self::assertNotSame($scheme, $scheme->withContent('Http'));
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
        self::assertSame($toString, (string) new Scheme($scheme));
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
        self::expectException(InvalidUriComponent::class);
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

    public function testInvalidSchemeType()
    {
        self::expectException(TypeError::class);
        new Scheme(date_create());
    }
}

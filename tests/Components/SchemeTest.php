<?php

/**
 * League.Uri (https://uri.thephpleague.com/components/).
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.8.2
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Uri\Components;

use League\Uri\Components\Exception;
use League\Uri\Components\Scheme;
use PHPUnit\Framework\TestCase;

/**
 * @group scheme
 */
final class SchemeTest extends TestCase
{
    public function testSetState()
    {
        $component = new Scheme('ignace');
        $generateComponent = eval('return '.var_export($component, true).';');
        self::assertEquals($component, $generateComponent);
    }

    public function testWithValue()
    {
        $scheme = new Scheme('ftp');
        $http_scheme = $scheme->withContent('HTTP');
        self::assertSame('http', $http_scheme->__toString());
        self::assertSame('http:', $http_scheme->getUriComponent());
    }

    public function testEmptyScheme()
    {
        $scheme = new Scheme();
        self::assertSame('', (string) $scheme);
        self::assertSame('', $scheme->getUriComponent());
    }

    /**
     * @dataProvider validSchemeProvider
     * @param null|string $scheme
     * @param string      $toString
     */
    public function testValidScheme($scheme, $toString)
    {
        self::assertSame($toString, (new Scheme($scheme))->__toString());
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
     * @param string $scheme
     * @dataProvider invalidSchemeProvider
     */
    public function testInvalidScheme($scheme)
    {
        self::expectException(Exception::class);
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
        self::expectException(Exception::class);
        (new Scheme('http'))->getContent(-1);
    }
}

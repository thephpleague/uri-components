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
use League\Uri\Components\Port;
use PHPUnit\Framework\TestCase;

/**
 * @group port
 */
final class PortTest extends TestCase
{
    public function testPortSetter()
    {
        self::assertSame('443', (new Port(443))->__toString());
    }

    public function testSetState()
    {
        $component = new Port(42);
        $generateComponent = eval('return '.var_export($component, true).';');
        self::assertEquals($component, $generateComponent);
    }

    /**
     * @param null|int $input
     * @param null|int $expected
     * @dataProvider getToIntProvider
     */
    public function testToInt($input, $expected)
    {
        self::assertSame($expected, (new Port($input))->getContent());
    }

    public function getToIntProvider()
    {
        return [
            [null, null],
            [23, 23],
        ];
    }

    public function invalidPortProvider()
    {
        return [
            'invalid port number too low' => [-1],
            //'invalid port number too high' => [10000000],
            //'invalid port number' => [0],
        ];
    }

    /**
     * @param int $port
     *
     * @dataProvider invalidPortProvider
     */
    public function testFailedPort($port)
    {
        self::expectException(Exception::class);
        new Port($port);
    }

    /**
     * @param int|null $input
     * @param string   $expected
     *
     * @dataProvider getUriComponentProvider
     */
    public function testGetUriComponent($input, $expected)
    {
        self::assertSame($expected, (new Port($input))->getUriComponent());
    }

    public function getUriComponentProvider()
    {
        return [
            [443, ':443'],
            [null, ''],
            [23, ':23'],
        ];
    }
}

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

namespace LeagueTest\Uri\Component;

use League\Uri\Component\Port;
use League\Uri\Exception\InvalidUriComponent;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * @group port
 * @coversDefaultClass \League\Uri\Component\Port
 */
class PortTest extends TestCase
{
    /**
     * @covers ::__toString
     */
    public function testPortSetter()
    {
        $this->assertSame('443', (new Port(443))->__toString());
    }

    /**
     * @covers ::__set_state
     * @covers ::__construct
     */
    public function testSetState()
    {
        $component = new Port(42);
        $generateComponent = eval('return '.var_export($component, true).';');
        $this->assertEquals($component, $generateComponent);
    }

    /**
     * @param mixed    $input
     * @param null|int $expected
     * @param mixed    $string_expected
     * @dataProvider getToIntProvider
     * @covers ::toInt
     * @covers ::getContent
     * @covers ::validate
     */
    public function testToInt($input, $expected, $string_expected)
    {
        $this->assertSame($expected, (new Port($input))->toInt());
        $this->assertSame($string_expected, (new Port($input))->getContent());
    }

    public function getToIntProvider()
    {
        return [
            [null, null, null],
            [23, 23, '23'],
            ['23', 23, '23'],
            [new class() {
                public function __toString()
                {
                    return '23';
                }
            }, 23, '23'],
            [new Port(23), 23, '23'],
        ];
    }

    public function testFailedPortTypeError()
    {
        $this->expectException(TypeError::class);
        new Port(date_create());
    }

    public function testFailedPortException()
    {
        $this->expectException(InvalidUriComponent::class);
        new Port(-1);
    }

    /**
     * @covers ::withContent
     */
    public function testWithContent()
    {
        $port = new Port(23);
        $this->assertSame($port, $port->withContent('23'));
        $this->assertNotSame($port, $port->withContent('42'));
    }
}

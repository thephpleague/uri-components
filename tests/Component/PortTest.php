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
use League\Uri\Exception\UnknownEncoding;
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
     */
    public function testSetState()
    {
        $component = new Port(42);
        $generateComponent = eval('return '.var_export($component, true).';');
        $this->assertEquals($component, $generateComponent);
    }

    /**
     * @covers ::__debugInfo
     */
    public function testDebugInfo()
    {
        $component = new Port(42);
        $debugInfo = $component->__debugInfo();
        $this->assertArrayHasKey('component', $debugInfo);
        $this->assertSame($component->getContent(), $debugInfo['component']);
    }

    /**
     * @param mixed    $input
     * @param null|int $expected
     * @dataProvider getToIntProvider
     * @covers ::getContent
     * @covers ::validate
     */
    public function testToInt($input, $expected)
    {
        $this->assertSame($expected, (new Port($input))->getContent());
    }

    public function getToIntProvider()
    {
        return [
            [null, null],
            [23, 23],
            ['23', 23],
            [new class() {
                public function __toString()
                {
                    return '23';
                }
            }, 23],
            [new Port(23), 23],
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

    public function testInvalidEncodingTypeThrowException()
    {
        $this->expectException(UnknownEncoding::class);
        (new Port(23))->getContent(-1);
    }

    /**
     * @param int|null $input
     * @param string   $expected
     *
     * @covers ::getUriComponent
     *
     * @dataProvider getUriComponentProvider
     */
    public function testGetUriComponent($input, $expected)
    {
        $this->assertSame($expected, (new Port($input))->getUriComponent());
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

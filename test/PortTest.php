<?php

namespace LeagueTest\Uri\Components;

use League\Uri\Components\Exception;
use League\Uri\Components\Port;
use PHPUnit\Framework\TestCase;

/**
 * @group port
 */
class PortTest extends TestCase
{
    public function testPortSetter()
    {
        $this->assertSame('443', (new Port(443))->__toString());
    }

    public function testSetState()
    {
        $component = new Port(42);
        $generateComponent = eval('return '.var_export($component, true).';');
        $this->assertEquals($component, $generateComponent);
    }

    /**
     * @param  $input
     * @param  $expected
     * @dataProvider getToIntProvider
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
        ];
    }

    public function invalidPortProvider()
    {
        return [
            'invalid port number too low' => [-23],
            'invalid port number too high' => [10000000],
            'invalid port number' => [0],
        ];
    }

    /**
     * @param $port
     *
     * @dataProvider invalidPortProvider
     */
    public function testFailedPort($port)
    {
        $this->expectException(Exception::class);
        new Port($port);
    }

    /**
     * @param  $input
     * @param  $expected
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

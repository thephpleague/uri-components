<?php

/**
 * League.Uri (http://uri.thephpleague.com/components)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.0
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Uri\Component;

use League\Uri\Component\Port;
use League\Uri\Exception\MalformedUriComponent;
use PHPUnit\Framework\TestCase;
use TypeError;
use function date_create;
use function var_export;

/**
 * @group port
 * @coversDefaultClass \League\Uri\Component\Port
 */
class PortTest extends TestCase
{
    /**
     * @covers ::__toString
     */
    public function testPortSetter(): void
    {
        self::assertSame('443', (new Port(443))->__toString());
    }

    /**
     * @covers ::__set_state
     * @covers ::__construct
     */
    public function testSetState(): void
    {
        $component = new Port(42);
        $generateComponent = eval('return '.var_export($component, true).';');
        self::assertEquals($component, $generateComponent);
    }

    /**
     * @dataProvider getToIntProvider
     *
     * @covers ::toInt
     * @covers ::getContent
     * @covers ::getUriComponent
     * @covers ::validate
     *
     * @param mixed|null $input
     * @param ?int       $expected
     * @param ?string    $string_expected
     */
    public function testToInt(
        $input,
        ?int $expected,
        ?string $string_expected,
        string $uri_expected
    ): void {
        self::assertSame($expected, (new Port($input))->toInt());
        self::assertSame($string_expected, (new Port($input))->getContent());
        self::assertSame($uri_expected, (new Port($input))->getUriComponent());
    }

    public function getToIntProvider(): array
    {
        return [
            [null, null, null, ''],
            [23, 23, '23', ':23'],
            ['23', 23, '23', ':23'],
            [new class() {
                public function __toString()
                {
                    return '23';
                }
            }, 23, '23', ':23'],
            [new Port(23), 23, '23', ':23'],
        ];
    }

    public function testFailedPortTypeError(): void
    {
        self::expectException(TypeError::class);
        new Port(date_create());
    }

    public function testFailedPortException(): void
    {
        self::expectException(MalformedUriComponent::class);
        new Port(-1);
    }

    /**
     * @covers ::withContent
     */
    public function testWithContent(): void
    {
        $port = new Port(23);
        self::assertSame($port, $port->withContent('23'));
        self::assertNotSame($port, $port->withContent('42'));
    }
}

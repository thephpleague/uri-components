<?php

/**
 * League.Uri (https://uri.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Uri\Components;

use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Http;
use League\Uri\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use function var_export;

/**
 * @group port
 * @coversDefaultClass \League\Uri\Components\Port
 */
final class PortTest extends TestCase
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
     * @covers ::value
     * @covers ::getUriComponent
     * @covers ::validate
     */
    public function testToInt(
        UriComponentInterface|Stringable|float|int|string|bool|null $input,
        ?int $expected,
        ?string $string_expected,
        string $uri_expected
    ): void {
        self::assertSame($expected, (new Port($input))->toInt());
        self::assertSame($string_expected, (new Port($input))->value());
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

    public function testFailedPortException(): void
    {
        $this->expectException(SyntaxError::class);

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

    /**
     * @dataProvider getURIProvider
     * @covers ::createFromUri
     */
    public function testCreateFromUri(UriInterface|Psr7UriInterface $uri, ?string $expected): void
    {
        $port = Port::createFromUri($uri);

        self::assertSame($expected, $port->value());
    }

    public function getURIProvider(): iterable
    {
        return [
            'PSR-7 URI object' => [
                'uri' => Http::createFromString('http://example.com:443'),
                'expected' => '443',
            ],
            'PSR-7 URI object with no fragment' => [
                'uri' => Http::createFromString('toto://example.com'),
                'expected' => null,
            ],
            'League URI object' => [
                'uri' => Uri::createFromString('http://example.com:443'),
                'expected' => '443',
            ],
            'League URI object with no fragment' => [
                'uri' => Uri::createFromString('toto://example.com'),
                'expected' => null,
            ],
        ];
    }

    public function testCreateFromAuthority(): void
    {
        $uri = Uri::createFromString('http://example.com:443');
        $auth = Authority::createFromUri($uri);

        self::assertEquals(Port::createFromUri($uri), Port::createFromAuthority($auth));
    }
}

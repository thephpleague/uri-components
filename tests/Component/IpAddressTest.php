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

use League\Uri\Component\Host;
use League\Uri\Exception\InvalidUriComponent;
use PHPUnit\Framework\TestCase;

/**
 * @group host
 * @coversDefaultClass \League\Uri\Component\Host
 */
class IpAddressTest extends TestCase
{
    /**
     * @covers ::__set_state
     */
    public function testSetState()
    {
        $host = new Host('[::1]');
        self::assertEquals($host, eval('return '.var_export($host, true).';'));
    }

    /**
     * @covers ::__construct
     * @covers ::withContent
     * @covers ::parse
     * @covers ::isValidIpv6Hostname
     */
    public function testWithContent()
    {
        $host = new Host('127.0.0.1');
        self::assertSame($host, $host->withContent('127.0.0.1'));
        self::assertSame($host, $host->withContent($host));
        self::assertNotSame($host, $host->withContent('[::1]'));
    }

    /**
     * Test valid IpAddress.
     * @param string|null $host
     * @param bool        $isDomain
     * @param bool        $isIp
     * @param bool        $isIpv4
     * @param bool        $isIpv6
     * @param bool        $isIpFuture
     * @param string|null $ipVersion
     * @param string      $uri
     * @param string      $ip
     * @param string      $iri
     * @dataProvider validIpAddressProvider
     * @covers ::__construct
     * @covers ::parse
     * @covers ::isValidIpv6Hostname
     * @covers ::isIp
     * @covers ::isIpv4
     * @covers ::isIpv6
     * @covers ::isIpFuture
     * @covers ::getIp
     * @covers ::getIpVersion
     */
    public function testValidIpAddress($host, $isDomain, $isIp, $isIpv4, $isIpv6, $isIpFuture, $ipVersion, $uri, $ip, $iri)
    {
        $host = new Host($host);
        self::assertSame($isIp, $host->isIp());
        self::assertSame($isIpv4, $host->isIpv4());
        self::assertSame($isIpv6, $host->isIpv6());
        self::assertSame($isIpFuture, $host->isIpFuture());
        self::assertSame($ip, $host->getIp());
        self::assertSame($ipVersion, $host->getIpVersion());
    }

    public function validIpAddressProvider()
    {
        return [
            'ipv4' => [
                Host::createFromIp('127.0.0.1'),
                false,
                true,
                true,
                false,
                false,
                '4',
                '127.0.0.1',
                '127.0.0.1',
                '127.0.0.1',
                '127.0.0.1',
                false,
                true,
                true,
                false,
                false,
                '4',
                '127.0.0.1',
                '127.0.0.1',
                '127.0.0.1',
            ],
            'ipv6' => [
                '[::1]',
                false,
                true,
                false,
                true,
                false,
                '6',
                '[::1]',
                '::1',
                '[::1]',
            ],
            'scoped ipv6' => [
                '[fe80:1234::%251]',
                false,
                true,
                false,
                true,
                false,
                '6',
                '[fe80:1234::%251]',
                'fe80:1234::%1',
                '[fe80:1234::%251]',
            ],
            'ipfuture' => [
                '[v1.ZZ.ZZ]',
                false,
                true,
                false,
                false,
                true,
                '1',
                '[v1.ZZ.ZZ]',
                'ZZ.ZZ',
                '[v1.ZZ.ZZ]',
            ],
            'registered name' => [
                'uri.thephpleague.com',
                false,
                false,
                false,
                false,
                false,
                null,
                'uri.thephpleague.com',
                null,
                'uri.thephpleague.com',
            ],
        ];
    }

    /**
     * @dataProvider createFromIpValid
     * @param string $input
     * @param string $version
     * @param string $expected
     * @covers ::createFromIp
     */
    public function testCreateFromIp($input, $version, $expected)
    {
        self::assertSame($expected, (string) Host::createFromIp($input, $version));
    }

    public function createFromIpValid()
    {
        return [
            'ipv4' => ['127.0.0.1', '', '127.0.0.1'],
            'ipv4 does care about the version string' => ['127.0.0.1', 'FA', '[vFA.127.0.0.1]'],
            'ipv6' => ['::1', '', '[::1]'],
            'ipv6 does care about the version string' => ['::1', '12', '[v12.::1]'],
            'ipv6 with scope' => ['fe80:1234::%1', '', '[fe80:1234::%251]'],
            'valid IpFuture' => ['csucj.$&+;::', 'AF', '[vAF.csucj.$&+;::]'],
        ];
    }

    /**
     * @dataProvider createFromIpFailed
     * @param string $input
     * @covers ::createFromIp
     */
    public function testCreateFromIpFailed($input)
    {
        self::expectException(InvalidUriComponent::class);
        Host::createFromIp($input);
    }

    public function createFromIpFailed()
    {
        return [
            'false ipv4' => ['127.0.0'],
            'hostname' => ['example.com'],
            'false ipfuture' => ['vAF.csucj.$&+;:/:'],
        ];
    }

    /**
     * @param string $host
     * @param string $expected
     * @dataProvider withoutZoneIdentifierProvider
     * @covers ::withoutZoneIdentifier
     */
    public function testWithoutZoneIdentifier($host, $expected)
    {
        self::assertSame($expected, (string) (new Host($host))->withoutZoneIdentifier());
    }

    public function withoutZoneIdentifierProvider()
    {
        return [
            'ipv4 host' => ['127.0.0.1', '127.0.0.1'],
            'ipv6 host' => ['[::1]', '[::1]'],
            'ipv6 scoped (1)' => ['[fe80::%251]', '[fe80::]'],
            'ipv6 scoped (2)' => ['[fe80::%1]', '[fe80::]'],
        ];
    }

    /**
     * @param string $host
     * @param bool   $expected
     * @dataProvider hasZoneIdentifierProvider
     * @covers ::hasZoneIdentifier
     */
    public function testHasZoneIdentifier($host, $expected)
    {
        self::assertSame($expected, (new Host($host))->hasZoneIdentifier());
    }

    public function hasZoneIdentifierProvider()
    {
        return [
            ['127.0.0.1', false],
            ['[::1]', false],
            ['[fe80::%251]', true],
        ];
    }
}

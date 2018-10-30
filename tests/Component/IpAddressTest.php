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

use League\Uri\Component\IpAddress;
use League\Uri\Exception\InvalidUriComponent;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * @group host
 * @coversDefaultClass \League\Uri\Component\IpAddress
 */
class IpAddressTest extends TestCase
{
    /**
     * @covers ::__set_state
     */
    public function testSetState()
    {
        $host = new IpAddress('[::1]');
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
        $host = new IpAddress('127.0.0.1');
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
        $host = new IpAddress($host);
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
                IpAddress::createFromIp('127.0.0.1'),
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
        ];
    }

    /**
     * @param string $invalid
     * @dataProvider invalidIpAddressProvider
     * @covers ::__construct
     * @covers ::parse
     * @covers ::isValidIpv6Hostname
     */
    public function testInvalidIpAddress($invalid)
    {
        self::expectException(InvalidUriComponent::class);
        new IpAddress($invalid);
    }

    public function invalidIpAddressProvider()
    {
        return [
            'empty string' => [''],
            'null' => [null],
            'domain name' => ['uri.thephpleague.com'],
            'empty label' => ['tot.    .coucou.com'],
            'space in the label' => ['re view'],
            'Invalid IPv4 format' => ['[127.0.0.1]'],
            'Invalid IPv6 format' => ['[[::1]]'],
            'Invalid IPv6 format 2' => ['[::1'],
            'naked ipv6' => ['::1'],
            'scoped naked ipv6' => ['fe80:1234::%251'],
            'invalid character in scope ipv6' => ['[fe80:1234::%25%23]'],
            'space character in starting label' => ['example. com'],
            'invalid character in host label' => ["examp\0le.com"],
            'invalid IP with scope' => ['[127.2.0.1%253]'],
            'invalid scope IPv6' => ['[ab23::1234%251]'],
            'invalid scope ID' => ['[fe80::1234%25?@]'],
            'invalid scope ID with utf8 character' => ['[fe80::1234%25€]'],
            'invalid IPFuture' => ['[v4.1.2.3]'],
            'invalid host with mix content' => ['_b%C3%A9bé.be-'],
        ];
    }

    public function testTypeErrorOnIpAddressConstruction()
    {
        self::expectException(TypeError::class);
        new IpAddress(date_create());
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
        self::assertSame($expected, (string) IpAddress::createFromIp($input, $version));
    }

    public function createFromIpValid()
    {
        return [
            'ipv4' => ['127.0.0.1', '', '127.0.0.1'],
            'ipv4 does not care about the version string' => ['127.0.0.1', 'foo', '127.0.0.1'],
            'ipv6' => ['::1', '', '[::1]'],
            'ipv6 does not care about the version string' => ['::1', 'bar', '[::1]'],
            'ipv6 with scope' => ['fe80:1234::%1', 'foo', '[fe80:1234::%251]'],
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
        IpAddress::createFromIp($input);
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
        self::assertSame($expected, (string) (new IpAddress($host))->withoutZoneIdentifier());
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
        self::assertSame($expected, (new IpAddress($host))->hasZoneIdentifier());
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

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

use League\Uri\Component\IpAddress;
use League\Uri\Exception\InvalidUriComponent;
use League\Uri\Exception\UnknownEncoding;
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
        $this->assertEquals($host, eval('return '.var_export($host, true).';'));
    }

    /**
     * @covers ::__debugInfo
     */
    public function testDebugInfo()
    {
        $component = new IpAddress('127.0.0.1');
        $debugInfo = $component->__debugInfo();
        $this->assertArrayHasKey('component', $debugInfo);
        $this->assertSame($component->getContent(), $debugInfo['component']);
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
        $this->assertSame($host, $host->withContent('127.0.0.1'));
        $this->assertSame($host, $host->withContent($host));
        $this->assertNotSame($host, $host->withContent('[::1]'));
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
     * @covers ::getContent
     * @covers ::getUriComponent
     * @covers ::getIpVersion
     */
    public function testValidIpAddress($host, $isDomain, $isIp, $isIpv4, $isIpv6, $isIpFuture, $ipVersion, $uri, $ip, $iri)
    {
        $host = new IpAddress($host);
        $this->assertSame($isIp, $host->isIp());
        $this->assertSame($isIpv4, $host->isIpv4());
        $this->assertSame($isIpv6, $host->isIpv6());
        $this->assertSame($isIpFuture, $host->isIpFuture());
        $this->assertSame($uri, $host->getUriComponent());
        $this->assertSame($ip, $host->getIp());
        $this->assertSame($iri, $host->getContent(IpAddress::RFC3987_ENCODING));
        $this->assertSame($ipVersion, $host->getIpVersion());
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
        $this->expectException(InvalidUriComponent::class);
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
        $this->expectException(TypeError::class);
        new IpAddress(date_create());
    }

    /**
     * @covers ::getContent
     */
    public function testInvalidEncodingTypeThrowException()
    {
        $this->expectException(UnknownEncoding::class);
        (new IpAddress('[::1]'))->getContent(-1);
    }

    /**
     * Test Punycode support.
     *
     * @param string $unicode Unicode IpAddressname
     * @param string $ascii   Ascii IpAddressname
     * @dataProvider hostnamesProvider
     * @covers ::getContent
     */
    public function testValidUnicodeIpAddress($unicode, $ascii)
    {
        $host = new IpAddress($unicode);
        $this->assertSame($ascii, $host->getContent(IpAddress::RFC3986_ENCODING));
        $this->assertSame($unicode, $host->getContent(IpAddress::RFC3987_ENCODING));
    }

    public function hostnamesProvider()
    {
        // http://en.wikipedia.org/wiki/.test_(international_domain_name)#Test_TLDs
        return [
            ['[::1]', '[::1]'],
            ['127.0.0.1', '127.0.0.1'],
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
        $this->assertSame($expected, (string) IpAddress::createFromIp($input, $version));
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
        $this->expectException(InvalidUriComponent::class);
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
        $this->assertSame($expected, (string) (new IpAddress($host))->withoutZoneIdentifier());
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
        $this->assertSame($expected, (new IpAddress($host))->hasZoneIdentifier());
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

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

namespace LeagueTest\Uri\Components;

use League\Uri\Components\Host;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Http;
use League\Uri\Uri;
use PHPUnit\Framework\TestCase;
use TypeError;
use function array_fill;
use function date_create;
use function implode;
use function var_export;

/**
 * @group host
 * @coversDefaultClass \League\Uri\Components\Host
 */
class HostTest extends TestCase
{
    /**
     * @covers ::__set_state
     */
    public function testSetState(): void
    {
        $host = new Host('uri.thephpleague.com');
        self::assertEquals($host, eval('return '.var_export($host, true).';'));
    }

    /**
     * @covers ::__construct
     * @covers ::withContent
     * @covers ::isValidIpv6Hostname
     * @covers ::isDomain
     */
    public function testWithContent(): void
    {
        $host = new Host('uri.thephpleague.com');
        self::assertSame($host, $host->withContent('uri.thephpleague.com'));
        self::assertSame($host, $host->withContent($host));
        self::assertNotSame($host, $host->withContent('csv.thephpleague.com'));
    }

    /**
     * Test valid Host.
     *
     * @dataProvider validHostProvider
     *
     * @covers ::__construct
     * @covers ::isValidIpv6Hostname
     * @covers ::getContent
     * @covers ::getUriComponent
     * @covers ::toAscii
     * @covers ::toUnicode
     *
     * @param mixed|null $host
     * @param string     $uri
     * @param string     $iri
     */
    public function testValidHost($host, ?string $uri, ?string $iri): void
    {
        $host = new Host($host);
        self::assertSame($uri, $host->toAscii());
        self::assertSame($host->__toString(), $host->getUriComponent());
        self::assertSame($iri, $host->toUnicode());
    }

    public function validHostProvider(): array
    {
        return [
            'ipv4' => [
                new Host('127.0.0.1'),
                '127.0.0.1',
                '127.0.0.1',
            ],
            'ipv6' => [
                '[::1]',
                '[::1]',
                '[::1]',
            ],
            'scoped ipv6' => [
                '[fe80:1234::%251]',
                '[fe80:1234::%251]',
                '[fe80:1234::%251]',
            ],
            'ipfuture' => [
                '[v1.ZZ.ZZ]',
                '[v1.ZZ.ZZ]',
                '[v1.ZZ.ZZ]',
            ],
            'normalized' => [
                'Master.EXAMPLE.cOm',
                'master.example.com',
                'master.example.com',
            ],
            'empty string' => [
                '',
                '',
                '',
            ],
            'null' => [
                null,
                null,
                null,
            ],
            'dot ending' => [
                'example.com.',
                'example.com.',
                'example.com.',
            ],
            'partial numeric' => [
                '23.42c.two',
                '23.42c.two',
                '23.42c.two',
            ],
            'all numeric' => [
                '98.3.2',
                '98.3.2',
                '98.3.2',
            ],
            'mix IP format with host label' => [
                'toto.127.0.0.1',
                'toto.127.0.0.1',
                'toto.127.0.0.1',
            ],
            'idn support' => [
                'مثال.إختبار',
                'xn--mgbh0fb.xn--kgbechtv',
                'مثال.إختبار',
            ],
            'IRI support' => [
                'xn--mgbh0fb.xn--kgbechtv',
                'xn--mgbh0fb.xn--kgbechtv',
                'مثال.إختبار',
            ],
            'Registered Name' => [
                'test..example.com',
                'test..example.com',
                'test..example.com',
            ],
        ];
    }

    /**
     * @dataProvider invalidHostProvider
     *
     * @covers ::__construct
     * @covers ::getIDNAErrors
     * @covers ::isValidIpv6Hostname
     * @covers ::toUnicode
     */
    public function testInvalidHost(string $invalid): void
    {
        self::expectException(SyntaxError::class);
        new Host($invalid);
    }

    public function invalidHostProvider(): array
    {
        return [
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
            'invalid Host with fullwith (1)' =>  ['％００.com'],
            'invalid host with fullwidth escaped' =>   ['%ef%bc%85%ef%bc%94%ef%bc%91.com'],
            'invalid IDNA host' => ['xn--3'],
        ];
    }

    public function testTypeErrorOnHostConstruction(): void
    {
        self::expectException(TypeError::class);
        new Host(date_create());
    }

    /**
     * Test Punycode support.
     *
     * @param string $unicode Unicode Hostname
     * @param string $ascii   Ascii Hostname
     * @dataProvider hostnamesProvider
     * @covers ::toAscii
     * @covers ::toUnicode
     */
    public function testValidUnicodeHost(string $unicode, string $ascii): void
    {
        $host = new Host($unicode);
        self::assertSame($ascii, $host->toAscii());
        self::assertSame($unicode, $host->toUnicode());
    }

    public function hostnamesProvider(): array
    {
        // http://en.wikipedia.org/wiki/.test_(international_domain_name)#Test_TLDs
        return [
            ['مثال.إختبار', 'xn--mgbh0fb.xn--kgbechtv'],
            ['مثال.آزمایشی', 'xn--mgbh0fb.xn--hgbk6aj7f53bba'],
            ['例子.测试', 'xn--fsqu00a.xn--0zwm56d'],
            ['例子.測試', 'xn--fsqu00a.xn--g6w251d'],
            ['пример.испытание', 'xn--e1afmkfd.xn--80akhbyknj4f'],
            ['उदाहरण.परीक्षा', 'xn--p1b6ci4b4b3a.xn--11b5bs3a9aj6g'],
            ['παράδειγμα.δοκιμή', 'xn--hxajbheg2az3al.xn--jxalpdlp'],
            ['실례.테스트', 'xn--9n2bp8q.xn--9t4b11yi5a'],
            ['בײַשפּיל.טעסט', 'xn--fdbk5d8ap9b8a8d.xn--deba0ad'],
            ['例え.テスト', 'xn--r8jz45g.xn--zckzah'],
            ['உதாரணம்.பரிட்சை', 'xn--zkc6cc5bi7f6e.xn--hlcj6aya9esc7a'],
            ['derhausüberwacher.de', 'xn--derhausberwacher-pzb.de'],
            ['renangonçalves.com', 'xn--renangonalves-pgb.com'],
            ['рф.ru', 'xn--p1ai.ru'],
            ['δοκιμή.gr', 'xn--jxalpdlp.gr'],
            ['ফাহাদ্১৯.বাংলা', 'xn--65bj6btb5gwimc.xn--54b7fta0cc'],
            ['𐌀𐌖𐌋𐌄𐌑𐌉·𐌌𐌄𐌕𐌄𐌋𐌉𐌑.gr', 'xn--uba5533kmaba1adkfh6ch2cg.gr'],
            ['guangdong.广东', 'guangdong.xn--xhq521b'],
            ['gwóźdź.pl', 'xn--gwd-hna98db.pl'],
            ['[::1]', '[::1]'],
            ['127.0.0.1', '127.0.0.1'],
        ];
    }


    /**
     * @dataProvider getURIProvider
     * @covers ::createFromUri
     *
     * @param mixed   $uri      an URI object
     * @param ?string $expected
     */
    public function testCreateFromUri($uri, ?string $expected): void
    {
        $host = Host::createFromUri($uri);

        self::assertSame($expected, $host->getContent());
    }

    public function getURIProvider(): iterable
    {
        return [
            'PSR-7 URI object' => [
                'uri' => Http::createFromString('http://example.com?foo=bar'),
                'expected' => 'example.com',
            ],
            'PSR-7 URI object with no host' => [
                'uri' => Http::createFromString('path/to/the/sky?foo'),
                'expected' => null,
            ],
            'PSR-7 URI object with empty string host' => [
                'uri' => Http::createFromString('file:///path/to/you'),
                'expected' => null,
            ],
            'League URI object' => [
                'uri' => Uri::createFromString('http://example.com?foo=bar'),
                'expected' => 'example.com',
            ],
            'League URI object with no host' => [
                'uri' => Uri::createFromString('path/to/the/sky?foo'),
                'expected' => null,
            ],
            'League URI object with empty string query' => [
                'uri' => Uri::createFromString('file:///path/to/you'),
                'expected' => '',
            ],
        ];
    }

    public function testCreateFromUriThrowsTypeError(): void
    {
        self::expectException(TypeError::class);

        Host::createFromUri('http://example.com#foobar');
    }

    /**
     * @dataProvider getIsDomainProvider
     * @covers ::isDomain
     * @param ?string $host
     */
    public function test_host_is_domain(?string $host, bool $expectedIsDomain): void
    {
        $host = new Host($host);

        self::assertSame($host->isDomain(), $expectedIsDomain);
    }

    public function getIsDomainProvider(): iterable
    {
        $tooLongHost = implode('.', array_fill(0, 128, 'b'));
        $maxLongHost = implode('.', array_fill(0, 126, 'a')).'.';
        $tooLongLabel = implode('', array_fill(0, 64, 'c')).'.a';

        return [
            'single label domain' => ['host' => 'localhost', 'expectedIsDomain' => true],
            'single label domain with ending dot' => ['host' => 'localhost.', 'expectedIsDomain' => true],
            'registered named' => ['host' => '-registered-.name', 'expectedIsDomain' => false],
            'too long domain name' => ['host' => $tooLongHost, 'expectedIsDomain' => false],
            'longest domain name' => ['host' => $maxLongHost, 'expectedIsDomain' => true],
            'too long label' => ['host' => $tooLongLabel, 'expectedIsDomain' => false],
            'empty string host' => ['host' => '', 'expectedIsDomain' => true],
            'single dot' => ['host' => '.', 'expectedIsDomain' => false],
            'null string host' => ['host' => null, 'expectedIsDomain' => true],
            'ipv4 host' => ['host' => '127.0.0.1', 'expectedIsDomain' => false],
            'ipv6 host' => ['host' => '[::1]', 'expectedIsDomain' => false],
            'multiple domain with a dot ending' => ['host' => 'ulb.ac.be.', 'expectedIsDomain' => true],
        ];
    }
}

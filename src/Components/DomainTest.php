<?php

/**
 * League.Uri (http://uri.thephpleague.com/components)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.2
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Uri\Components;

use ArrayIterator;
use League\Uri\Components\Authority;
use League\Uri\Components\Domain;
use League\Uri\Components\Host;
use League\Uri\Contracts\UriException;
use League\Uri\Exceptions\OffsetOutOfBounds;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Http;
use League\Uri\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;
use TypeError;
use function date_create;
use function var_export;

/**
 * @group host
 * @coversDefaultClass \League\Uri\Components\Domain
 */
class DomainTest extends TestCase
{
    /**
     * @covers ::__set_state
     */
    public function testSetState(): void
    {
        $host = Domain::createFromString('uri.thephpleague.com');

        self::assertEquals($host, eval('return '.var_export($host, true).';'));
    }

    public function testItCanBeInstantiatedWithAHostInterfaceImplementingObject(): void
    {
        $host = new Host('uri.thephpleague.com');
        $domain = Domain::createFromHost($host);

        self::assertSame('uri.thephpleague.com', $domain->getContent());
    }

    public function testItFailsIfTheHostInterfaceImplementingObjectIsNotADomain(): void
    {
        $this->expectException(UriException::class);

        Domain::createFromHost(Host::createFromIp('127.0.0.1'));
    }

    public function testItFailsIfTheHostIsNotADomain(): void
    {
        $this->expectException(UriException::class);

        Domain::createFromHost(new Domain('127.0.0.1'));
    }

    /**
     * @covers ::getIterator
     * @covers ::isDomain
     * @covers ::isIp
     */
    public function testIterator(): void
    {
        $host = Domain::createFromString('uri.thephpleague.com');

        self::assertEquals(['com', 'thephpleague', 'uri'], iterator_to_array($host));
        self::assertFalse($host->isIp());
        self::assertTrue($host->isDomain());
    }

    /**
     * @covers ::createFromString
     * @covers ::withContent
     */
    public function testWithContent(): void
    {
        $host = Domain::createFromString('uri.thephpleague.com');

        self::assertSame($host, $host->withContent('uri.thephpleague.com'));
        self::assertSame($host, $host->withContent($host));
        self::assertNotSame($host, $host->withContent('csv.thephpleague.com'));
    }

    /**
     * Test valid Domain.
     * @dataProvider validDomainProvider
     *
     * @covers ::createFromString
     * @covers ::setLabels
     * @covers ::getContent
     * @covers ::toUnicode
     */
    public function testValidDomain(string $host, string $uri, string $iri): void
    {
        $host = Domain::createFromString($host);

        self::assertSame($uri, $host->getContent());
        self::assertSame($iri, $host->toUnicode());
    }

    public function validDomainProvider(): array
    {
        return [
            'normalized' => [
                'Master.EXAMPLE.cOm',
                'master.example.com',
                'master.example.com',
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
        ];
    }

    /**
     * @dataProvider invalidDomainProvider
     * @covers ::createFromHost
     * @param ?string $invalid
     */
    public function testInvalidDomain(?string $invalid): void
    {
        $this->expectException(SyntaxError::class);

        Domain::createFromHost(new Host($invalid));
    }

    public function invalidDomainProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'ipv4' => ['127.0.0.1'],
            'ipv6' => ['::1'],
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
            'invalid IDN domain' => ['a⒈com'],
            'invalid Host with fullwith (1)' =>  ['％００.com'],
            'invalid host with fullwidth escaped' =>   ['%ef%bc%85%ef%bc%94%ef%bc%91.com'],
            'registered name not domain name' => ['master..plan.be'],
        ];
    }

    /**
     * @param bool $expected
     *
     * @dataProvider isAbsoluteProvider
     *
     * @covers ::isAbsolute
     */
    public function testIsAbsolute(string $raw, $expected): void
    {
        self::assertSame($expected, Domain::createFromString($raw)->isAbsolute());
    }

    public function isAbsoluteProvider(): array
    {
        return [
            ['example.com.', true],
            ['example.com', false],
        ];
    }

    /**
     * @covers ::getIp
     * @covers ::getIpVersion
     */
    public function testIpProperty(): void
    {
        $host = Domain::createFromString('example.com');

        self::assertNull($host->getIpVersion());
        self::assertNull($host->getIp());
    }

    /**
     * Test Punycode support.
     *
     * @param string $unicode Unicode Domainname
     * @param string $ascii   Ascii Domainname
     * @dataProvider hostnamesProvider
     * @covers ::toUnicode
     * @covers ::toAscii
     */
    public function testValidUnicodeDomain(string $unicode, string $ascii): void
    {
        $host = Domain::createFromString($unicode);

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
        ];
    }

    /**
     * Test Countable.
     *
     * @dataProvider countableProvider
     *
     * @covers ::count
     */
    public function testCountable(string $host, int $nblabels, array $array): void
    {
        self::assertCount($nblabels, Domain::createFromString($host));
    }

    public function countableProvider(): array
    {
        return [
            'string' => ['secure.example.com', 3, ['com', 'example', 'secure']],
            'numeric' => ['92.56.8', 3, ['8', '56', '92']],
        ];
    }

    /**
     * @covers ::createFromLabels
     * @covers ::__toString
     *
     * @dataProvider createFromLabelsValid
     */
    public function testCreateFromLabels(iterable $input, string $expected): void
    {
        self::assertSame($expected, (string) Domain::createFromLabels($input));
    }

    public function createFromLabelsValid(): array
    {
        return [
            'array' => [['com', 'example', 'www'], 'www.example.com'],
            'iterator' => [new ArrayIterator(['com', 'example', 'www']), 'www.example.com'],
            'FQDN' => [['', 'com', 'example', 'www'], 'www.example.com.'],
            'another host object' => [new Domain('example.com.'), 'example.com.'],
        ];
    }

    /**
     * @covers ::createFromLabels
     */
    public function testCreateFromLabelsFailedWithInvalidArrayInput(): void
    {
        $this->expectException(TypeError::class);
        Domain::createFromLabels([date_create()]);
    }

    /**
     * @covers ::createFromLabels
     */
    public function testCreateFromLabelsFailedWithNullLabel(): void
    {
        $this->expectException(TypeError::class);
        Domain::createFromLabels([null]);
    }

    /**
     * @covers ::createFromLabels
     */
    public function testCreateFromLabelsFailedWithEmptyStringLabel(): void
    {
        $this->expectException(SyntaxError::class);
        Domain::createFromLabels(['']);
    }

    /**
     * @covers ::createFromLabels
     */
    public function testCreateFromLabelsFailedWithEmptyLabel(): void
    {
        $this->expectException(SyntaxError::class);
        Domain::createFromLabels([]);
    }

    /**
     * @covers ::get
     */
    public function testGet(): void
    {
        $host = Domain::createFromString('master.example.com');
        self::assertSame('com', $host->get(0));
        self::assertSame('example', $host->get(1));
        self::assertSame('master', $host->get(-1));
        self::assertNull($host->get(23));
    }

    /**
     * @covers ::keys
     */
    public function testOffsets(): void
    {
        $host = Domain::createFromString('master.example.com');
        self::assertSame([2], $host->keys('master'));
        self::assertSame([0, 1, 2], $host->keys());
    }

    /**
     * @covers ::labels
     */
    public function testLabels(): void
    {
        $host = Domain::createFromString('master.example.com');
        self::assertSame(['com', 'example', 'master'], $host->labels());
        self::assertSame(['', 'localhost'], (new Domain('localhost.'))->labels());
    }

    /**
     * @dataProvider withoutProvider
     * @covers ::withoutLabel
     */
    public function testWithout(string $host, int $without, string $res): void
    {
        self::assertSame($res, (string) Domain::createFromString($host)->withoutLabel($without));
    }

    public function withoutProvider(): array
    {
        return [
            'remove one string label' => ['secure.example.com', 0, 'secure.example'],
            'remove one string label negative offset' => ['secure.example.com', -1, 'example.com'],
        ];
    }

    /**
     * @covers ::withoutLabel
     */
    public function testWithoutLabelVariadicArgument(): void
    {
        $host = Domain::createFromString('www.example.com');

        self::assertSame($host, $host->withoutLabel());
    }

    /**
     * @covers ::withoutLabel
     */
    public function testWithoutTriggersException(): void
    {
        $this->expectException(OffsetOutOfBounds::class);

        Domain::createFromString('bébé.be')->withoutLabel(-23);
    }

    /**
     * @dataProvider validPrepend
     *
     * @covers ::prepend
     */
    public function testPrepend(string $raw, string $prepend, string $expected): void
    {
        self::assertSame($expected, (string) Domain::createFromString($raw)->prepend($prepend));
    }

    public function validPrepend(): array
    {
        return [
            ['secure.example.com', 'master', 'master.secure.example.com'],
            ['secure.example.com.', 'master', 'master.secure.example.com.'],
            ['secure.example.com', '127.0.0.1', '127.0.0.1.secure.example.com'],
        ];
    }

    /**
     * @covers ::prepend
     */
    public function testPrependIpFailed(): void
    {
        $this->expectException(SyntaxError::class);

        Domain::createFromString('secure.example.com')->prepend(new Domain('master.'));
    }

    /**
     * @covers ::prepend
     */
    public function testPrependNull(): void
    {
        $domain = Domain::createFromString('secure.example.com');

        self::assertSame($domain->prepend(null), $domain);
    }

    /**
     * @dataProvider validAppend
     *
     * @covers ::append
     */
    public function testAppend(string $raw, string $append, string $expected): void
    {
        self::assertSame($expected, (string) Domain::createFromString($raw)->append($append));
    }

    public function validAppend(): array
    {
        return [
            ['secure.example.com', 'master', 'secure.example.com.master'],
            ['secure.example.com', 'master.', 'secure.example.com.master.'],
            ['toto', '127.0.0.1', 'toto.127.0.0.1'],
            ['example.com', '', 'example.com.'],
        ];
    }

    /**
     * @covers ::append
     */
    public function testAppendIpFailed(): void
    {
        $this->expectException(SyntaxError::class);

        Domain::createFromString('secure.example.com.')->append('master');
    }

    /**
     * @covers ::append
     */
    public function testAppendNull(): void
    {
        $domain = Domain::createFromString('secure.example.com');

        self::assertSame($domain->append(null), $domain);
    }

    /**
     * @dataProvider replaceValid
     * @covers ::withLabel
     * @covers ::append
     * @covers ::prepend
     */
    public function testReplace(string $raw, string $input, int $offset, string $expected): void
    {
        self::assertSame($expected, (string) Domain::createFromString($raw)->withLabel($offset, $input));
    }

    public function replaceValid(): array
    {
        return [
            ['master.example.com', 'shop', 3, 'master.example.com.shop'],
            ['master.example.com', 'shop', -4, 'shop.master.example.com'],
            ['master.example.com', 'shop', 2, 'shop.example.com'],
            ['master.example.com', 'master', 2, 'master.example.com'],
            ['secure.example.com', '127.0.0.1', 0, 'secure.example.127.0.0.1'],
            ['master.example.com.', 'shop', -2, 'master.shop.com.'],
            ['master.example.com', 'shop', -1, 'shop.example.com'],
            ['foo', 'bar', -1, 'bar'],
        ];
    }

    /**
     * @covers ::withLabel
     */
    public function testReplaceIpMustFailed(): void
    {
        $this->expectException(SyntaxError::class);

        Domain::createFromString('secure.example.com')->withLabel(2, '[::1]');
    }

    /**
     * @covers ::withLabel
     */
    public function testReplaceMustFailed(): void
    {
        $this->expectException(OffsetOutOfBounds::class);

        Domain::createFromString('secure.example.com')->withLabel(23, 'foo');
    }

    /**
     * @dataProvider rootProvider
     * @covers ::withRootLabel
     * @covers ::withoutRootLabel
     */
    public function testWithRoot(string $host, string $expected_with_root, string $expected_without_root): void
    {
        $host = Domain::createFromString($host);

        self::assertSame($expected_with_root, (string) $host->withRootLabel());
        self::assertSame($expected_without_root, (string) $host->withoutRootLabel());
    }

    public function rootProvider(): array
    {
        return [
            ['example.com', 'example.com.', 'example.com'],
            ['example.com.', 'example.com.', 'example.com'],
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
        $domain = Domain::createFromUri($uri);

        self::assertSame($expected, $domain->getContent());
    }

    public function getURIProvider(): iterable
    {
        return [
            'PSR-7 URI object' => [
                'uri' => Http::createFromString('http://example.com?foo=bar'),
                'expected' => 'example.com',
            ],
            'League URI object' => [
                'uri' => Uri::createFromString('http://example.com?foo=bar'),
                'expected' => 'example.com',
            ],
        ];
    }

    public function testCreateFromUriThrowsTypeError(): void
    {
        $this->expectException(TypeError::class);

        Domain::createFromUri('http://example.com#foobar');
    }

    /**
     * @dataProvider provideInvalidDomainName
     */
    public function testCreateFromUriThrowsSyntaxtError(UriInterface $uri): void
    {
        $this->expectException(SyntaxError::class);

        Domain::createFromUri($uri);
    }

    public function provideInvalidDomainName(): iterable
    {
        return [
            'PSR-7 URI object with no host' => [Http::createFromString('path/to/the/sky?foo')],
            'PSR-7 URI object with empty string host' => [Http::createFromString('file:///path/to/you')],
        ];
    }

    public function testCreateFromAuthority(): void
    {
        $uri = Uri::createFromString('http://example.com:443');
        $auth = Authority::createFromUri($uri);

        self::assertEquals(Domain::createFromUri($uri), Domain::createFromAuthority($auth));
    }

    public function testCreateFromStringThrowsTypeError(): void
    {
        $this->expectException(TypeError::class);

        Domain::createFromString(new \stdClass());
    }

    public function testSlice(): void
    {
        $domain = Domain::createFromString('ulb.ac.be');

        self::assertSame($domain->getContent(), $domain->slice(-3)->getContent());
        self::assertSame($domain->getContent(), $domain->slice(0)->getContent());

        self::assertSame('ulb.ac', $domain->slice(1)->getContent());
        self::assertSame('ulb', $domain->slice(-1)->getContent());
        self::assertSame('be', $domain->slice(-3, 1)->getContent());
    }

    public function testSliceThrowsOnOverFlow(): void
    {
        $this->expectException(OffsetOutOfBounds::class);

        Domain::createFromString('ulb.ac.be')->slice(5);
    }
}

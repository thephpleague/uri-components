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

use ArrayIterator;
use League\Uri\Contracts\UriException;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\OffsetOutOfBounds;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Http;
use League\Uri\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use TypeError;
use function date_create;

/**
 * @group host
 * @coversDefaultClass \League\Uri\Components\Domain
 */
final class DomainTest extends TestCase
{
    public function testItCanBeInstantiatedWithAHostInterfaceImplementingObject(): void
    {
        $host = Host::createFromString('uri.thephpleague.com');
        $domain = Domain::createFromHost($host);

        self::assertSame('uri.thephpleague.com', $domain->value());
    }

    public function testItFailsIfTheHostInterfaceImplementingObjectIsNotADomain(): void
    {
        $this->expectException(UriException::class);

        Domain::createFromHost(Host::createFromIp('127.0.0.1'));
    }

    public function testItFailsIfTheHostIsNotADomain(): void
    {
        $this->expectException(UriException::class);

        Domain::createFromHost(Domain::createFromString('127.0.0.1'));
    }

    public function testIterator(): void
    {
        $host = Domain::createFromString('uri.thephpleague.com');

        self::assertEquals(['com', 'thephpleague', 'uri'], iterator_to_array($host));
        self::assertFalse($host->isIp());
        self::assertTrue($host->isDomain());
    }

    /**
     * Test valid Domain.
     * @dataProvider validDomainProvider
     */
    public function testValidDomain(string $host, string $uri, string $iri): void
    {
        $host = Domain::createFromString($host);

        self::assertSame($uri, $host->value());
        self::assertSame($iri, $host->toUnicode());
    }

    public static function validDomainProvider(): array
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
     */
    public function testInvalidDomain(?string $invalid): void
    {
        $this->expectException(SyntaxError::class);

        $host = null === $invalid ? Host::new() : Host::createFromString($invalid);

        Domain::createFromHost($host);
    }

    public static function invalidDomainProvider(): array
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
     * @dataProvider isAbsoluteProvider
     */
    public function testIsAbsolute(string $raw, bool $expected): void
    {
        self::assertSame($expected, Domain::createFromString($raw)->isAbsolute());
    }

    public static function isAbsoluteProvider(): array
    {
        return [
            ['example.com.', true],
            ['example.com', false],
        ];
    }

    public function testIpProperty(): void
    {
        $host = Domain::createFromString('example.com');

        self::assertNull($host->getIpVersion());
        self::assertNull($host->getIp());
    }

    /**
     * @dataProvider hostnamesProvider
     */
    public function testValidUnicodeDomain(string $unicode, string $ascii): void
    {
        $host = Domain::createFromString($unicode);

        self::assertSame($ascii, $host->toAscii());
        self::assertSame($unicode, $host->toUnicode());
    }

    public static function hostnamesProvider(): array
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
     * @dataProvider countableProvider
     */
    public function testCountable(string $host, int $nblabels): void
    {
        self::assertCount($nblabels, Domain::createFromString($host));
    }

    public static function countableProvider(): array
    {
        return [
            'string' => ['secure.example.com', 3, ['com', 'example', 'secure']],
            'numeric' => ['92.56.8', 3, ['8', '56', '92']],
        ];
    }

    /**
     * @dataProvider createFromLabelsValid
     */
    public function testCreateFromLabels(iterable $input, string $expected): void
    {
        self::assertSame($expected, (string) Domain::createFromLabels($input));
    }

    public static function createFromLabelsValid(): array
    {
        return [
            'array' => [['com', 'example', 'www'], 'www.example.com'],
            'iterator' => [new ArrayIterator(['com', 'example', 'www']), 'www.example.com'],
            'FQDN' => [['', 'com', 'example', 'www'], 'www.example.com.'],
            'another host object' => [Domain::createFromString('example.com.'), 'example.com.'],
        ];
    }

    public function testCreateFromLabelsFailedWithInvalidArrayInput(): void
    {
        $this->expectException(TypeError::class);
        Domain::createFromLabels([date_create()]);
    }

    public function testCreateFromLabelsFailedWithNullLabel(): void
    {
        $this->expectException(TypeError::class);
        Domain::createFromLabels([null]);
    }

    public function testCreateFromLabelsFailedWithEmptyStringLabel(): void
    {
        $this->expectException(SyntaxError::class);
        Domain::createFromLabels(['']);
    }

    public function testCreateFromLabelsFailedWithEmptyLabel(): void
    {
        $this->expectException(SyntaxError::class);
        Domain::createFromLabels([]);
    }

    public function testGet(): void
    {
        $host = Domain::createFromString('master.example.com');
        self::assertSame('com', $host->get(0));
        self::assertSame('example', $host->get(1));
        self::assertSame('master', $host->get(-1));
        self::assertNull($host->get(23));
    }

    public function testOffsets(): void
    {
        $host = Domain::createFromString('master.example.com');
        self::assertSame([2], $host->keys('master'));
        self::assertSame([0, 1, 2], $host->keys());
    }

    public function testLabels(): void
    {
        $host = Domain::createFromString('master.example.com');
        self::assertSame(['com', 'example', 'master'], $host->labels());
        self::assertSame(['', 'localhost'], Domain::createFromString('localhost.')->labels());
    }

    /**
     * @dataProvider withoutProvider
     */
    public function testWithout(string $host, int $without, string $res): void
    {
        self::assertSame($res, (string) Domain::createFromString($host)->withoutLabel($without));
    }

    public static function withoutProvider(): array
    {
        return [
            'remove one string label' => ['secure.example.com', 0, 'secure.example'],
            'remove one string label negative offset' => ['secure.example.com', -1, 'example.com'],
        ];
    }

    public function testWithoutLabelVariadicArgument(): void
    {
        $host = Domain::createFromString('www.example.com');

        self::assertSame($host, $host->withoutLabel());
    }

    public function testWithoutTriggersException(): void
    {
        $this->expectException(OffsetOutOfBounds::class);

        Domain::createFromString('bébé.be')->withoutLabel(-23);
    }

    /**
     * @dataProvider validPrepend
     */
    public function testPrepend(string $raw, string $prepend, string $expected): void
    {
        self::assertSame($expected, (string) Domain::createFromString($raw)->prepend($prepend));
    }

    public static function validPrepend(): array
    {
        return [
            ['secure.example.com', 'master', 'master.secure.example.com'],
            ['secure.example.com.', 'master', 'master.secure.example.com.'],
            ['secure.example.com', '127.0.0.1', '127.0.0.1.secure.example.com'],
        ];
    }

    public function testPrependIpFailed(): void
    {
        $this->expectException(SyntaxError::class);

        Domain::createFromString('secure.example.com')->prepend(Domain::createFromString('master.'));
    }

    public function testPrependNull(): void
    {
        $domain = Domain::createFromString('secure.example.com');

        self::assertSame($domain->prepend(null), $domain);
    }

    /**
     * @dataProvider validAppend
     */
    public function testAppend(string $raw, string $append, string $expected): void
    {
        self::assertSame($expected, (string) Domain::createFromString($raw)->append($append));
    }

    public static function validAppend(): array
    {
        return [
            ['secure.example.com', 'master', 'secure.example.com.master'],
            ['secure.example.com', 'master.', 'secure.example.com.master.'],
            ['toto', '127.0.0.1', 'toto.127.0.0.1'],
            ['example.com', '', 'example.com.'],
        ];
    }

    public function testAppendIpFailed(): void
    {
        $this->expectException(SyntaxError::class);

        Domain::createFromString('secure.example.com.')->append('master');
    }

    public function testAppendNull(): void
    {
        $domain = Domain::createFromString('secure.example.com');

        self::assertSame($domain->append(null), $domain);
    }

    /**
     * @dataProvider replaceValid
     */
    public function testReplace(string $raw, string $input, int $offset, string $expected): void
    {
        self::assertSame($expected, (string) Domain::createFromString($raw)->withLabel($offset, $input));
    }

    public static function replaceValid(): array
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

    public function testReplaceIpMustFailed(): void
    {
        $this->expectException(SyntaxError::class);

        Domain::createFromString('secure.example.com')->withLabel(2, '[::1]');
    }

    public function testReplaceMustFailed(): void
    {
        $this->expectException(OffsetOutOfBounds::class);

        Domain::createFromString('secure.example.com')->withLabel(23, 'foo');
    }

    /**
     * @dataProvider rootProvider
     */
    public function testWithRoot(string $host, string $expected_with_root, string $expected_without_root): void
    {
        $host = Domain::createFromString($host);

        self::assertSame($expected_with_root, (string) $host->withRootLabel());
        self::assertSame($expected_without_root, (string) $host->withoutRootLabel());
    }

    public static function rootProvider(): array
    {
        return [
            ['example.com', 'example.com.', 'example.com'],
            ['example.com.', 'example.com.', 'example.com'],
        ];
    }

    /**
     * @dataProvider getURIProvider
     */
    public function testCreateFromUri(Psr7UriInterface|UriInterface $uri, ?string $expected): void
    {
        $domain = Domain::createFromUri($uri);

        self::assertSame($expected, $domain->value());
    }

    public static function getURIProvider(): iterable
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

    /**
     * @dataProvider provideInvalidDomainName
     */
    public function testCreateFromUriThrowsSyntaxtError(Psr7UriInterface $uri): void
    {
        $this->expectException(SyntaxError::class);

        Domain::createFromUri($uri);
    }

    public static function provideInvalidDomainName(): iterable
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

    public function testSlice(): void
    {
        $domain = Domain::createFromString('ulb.ac.be');

        self::assertSame($domain->value(), $domain->slice(-3)->value());
        self::assertSame($domain->value(), $domain->slice(0)->value());

        self::assertSame('ulb.ac', $domain->slice(1)->value());
        self::assertSame('ulb', $domain->slice(-1)->value());
        self::assertSame('be', $domain->slice(-3, 1)->value());
    }

    public function testSliceThrowsOnOverFlow(): void
    {
        $this->expectException(OffsetOutOfBounds::class);

        Domain::createFromString('ulb.ac.be')->slice(5);
    }
}

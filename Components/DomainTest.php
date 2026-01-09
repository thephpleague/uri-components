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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface as Psr7UriInterface;

use function str_repeat;

#[CoversClass(Domain::class)]
#[Group('host')]
final class DomainTest extends TestCase
{
    public function testItCanBeInstantiatedWithAHostInterfaceImplementingObject(): void
    {
        $host = Host::new('uri.thephpleague.com');
        $domain = Domain::new($host);

        self::assertTrue($domain->contains('uri'));
        self::assertFalse($domain->contains('period'));
        self::assertSame(2, $domain->indexOf('uri'));
        self::assertNull($domain->indexOf('period'));
        self::assertSame($domain->lastIndexOf('uri'), $domain->indexOf('uri'));
        self::assertFalse($domain->isEmpty());
        self::assertSame('com', $domain->first());
        self::assertSame('uri', $domain->last());

        self::assertSame('uri.thephpleague.com', $domain->value());
    }

    public function testItFailsIfTheHostInterfaceImplementingObjectIsNotADomain(): void
    {
        $this->expectException(UriException::class);

        Domain::new(Host::fromIp('127.0.0.1'));
    }

    public function testItFailsIfTheHostIsNotADomain(): void
    {
        $this->expectException(UriException::class);
        Domain::new('127.0.0.1');
    }

    public function testIterator(): void
    {
        $host = Domain::new('uri.thephpleague.com');

        self::assertEquals(['com', 'thephpleague', 'uri'], iterator_to_array($host));
        self::assertFalse($host->isIp());
        self::assertTrue($host->isDomain());
        self::assertTrue($host->isRegisteredName());
    }

    /**
     * Test valid Domain.
     */
    #[DataProvider('validDomainProvider')]
    public function testValidDomain(string $host, string $uri, string $iri): void
    {
        $host = Domain::new($host);

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

    #[DataProvider('invalidDomainProvider')]
    public function testInvalidDomain(?string $invalid): void
    {
        $this->expectException(SyntaxError::class);

        Domain::new($invalid);
    }

    public static function invalidDomainProvider(): array
    {
        return [
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

    #[DataProvider('isAbsoluteProvider')]
    public function testIsAbsolute(string $raw, bool $expected): void
    {
        self::assertSame($expected, Domain::new($raw)->isAbsolute());
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
        $host = Domain::new('example.com');

        self::assertNull($host->getIpVersion());
        self::assertNull($host->getIp());
    }

    #[DataProvider('hostnamesProvider')]
    public function testValidUnicodeDomain(string $unicode, string $ascii): void
    {
        $host = Domain::new($unicode);

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

    #[DataProvider('countableProvider')]
    public function testCountable(string $host, int $nblabels, array $labels): void
    {
        self::assertCount($nblabels, Domain::new($host));
    }

    public static function countableProvider(): array
    {
        return [
            'string' => ['secure.example.com', 3, ['com', 'example', 'secure']],
            'numeric' => ['92.56.8', 3, ['8', '56', '92']],
        ];
    }

    #[DataProvider('createFromLabelsValid')]
    public function testCreateFromLabels(iterable $input, string $expected): void
    {
        self::assertSame($expected, (string) Domain::fromLabels(...$input));
    }

    public static function createFromLabelsValid(): array
    {
        return [
            'array' => [['com', 'example', 'www'], 'www.example.com'],
            'iterator' => [new ArrayIterator(['com', 'example', 'www']), 'www.example.com'],
            'FQDN' => [['', 'com', 'example', 'www'], 'www.example.com.'],
            'another host object' => [Domain::new('example.com.'), 'example.com.'],
        ];
    }

    public function testCreateFromLabelsFailedWithEmptyStringLabel(): void
    {
        $this->expectException(SyntaxError::class);
        Domain::fromLabels('');
    }

    public function testCreateFromLabelsSucceedsWithEmptyLabel(): void
    {
        self::assertNull(Domain::fromLabels()->value());
    }

    public function testGet(): void
    {
        $host = Domain::new('master.example.com');
        self::assertSame('com', $host->get(0));
        self::assertSame('example', $host->get(1));
        self::assertSame('master', $host->get(-1));
        self::assertNull($host->get(23));
    }

    public function testOffsets(): void
    {
        $host = Domain::new('master.example.com');
        self::assertSame([2], $host->keys('master'));
        self::assertSame([0, 1, 2], $host->keys());
    }

    public function testLabels(): void
    {
        $host = Domain::new('master.example.com');
        self::assertSame(['com', 'example', 'master'], [...$host]);
        self::assertSame(['', 'localhost'], [...Domain::new('localhost.')]);
    }

    #[DataProvider('withoutProvider')]
    public function testWithout(string $host, int $without, string $res): void
    {
        self::assertSame($res, (string) Domain::new($host)->withoutLabel($without));
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
        $host = Domain::new('www.example.com');

        self::assertSame($host, $host->withoutLabel());
    }

    public function testWithoutTriggersException(): void
    {
        $this->expectException(OffsetOutOfBounds::class);

        Domain::new('bébé.be')->withoutLabel(-23);
    }

    #[DataProvider('validPrepend')]
    public function testPrepend(string $raw, string $prepend, string $expected): void
    {
        self::assertSame($expected, (string) Domain::new($raw)->prepend($prepend));
    }

    public static function validPrepend(): array
    {
        return [
            ['secure.example.com', 'master', 'master.secure.example.com'],
            ['secure.example.com.', 'master', 'master.secure.example.com.'],
            ['secure.example.com', '127.0.0.1', '127.0.0.1.secure.example.com'],
            ['secure.example.com', '127.', '127.secure.example.com'],
            ['secure.example.com.', '127.', '127.secure.example.com.'],
            ['secure.example.com', '127', '127.secure.example.com'],
        ];
    }

    public function testPrependFailsWithInvalidAbsoluteHost(): void
    {
        $this->expectException(SyntaxError::class);

        Domain::new('secure.example.com')->prepend('master..');
    }

    public function testPrependNull(): void
    {
        $domain = Domain::new('secure.example.com');

        self::assertSame($domain->prepend(null), $domain);
    }

    #[DataProvider('validAppend')]
    public function testAppend(string $raw, string $append, string $expected): void
    {
        self::assertSame($expected, (string) Domain::new($raw)->append($append));
    }

    public static function validAppend(): array
    {
        return [
            ['secure.example.com', 'master', 'secure.example.com.master'],
            ['secure.example.com', 'master.', 'secure.example.com.master.'],
            ['toto', '127.0.0.1', 'toto.127.0.0.1'],
            ['example.com', '', 'example.com.'],
            ['secure.example.com.', 'master', 'secure.example.com.master.'],
            ['secure.example.com.', 'master.', 'secure.example.com.master.'],
            ['secure.example.com', 'master.', 'secure.example.com.master.'],
        ];
    }

    public function testAppendFailsWithInvalidAbsoluteHost(): void
    {
        $this->expectException(SyntaxError::class);

        Domain::new('secure.example.com')->append('master..');
    }

    public function testAppendNull(): void
    {
        $domain = Domain::new('secure.example.com');

        self::assertSame($domain->append(null), $domain);
    }

    #[DataProvider('replaceValid')]
    public function testReplace(string $raw, string $input, int $offset, string $expected): void
    {
        self::assertSame($expected, (string) Domain::new($raw)->withLabel($offset, $input));
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

        Domain::new('secure.example.com')->withLabel(2, '[::1]');
    }

    public function testReplaceMustFailed(): void
    {
        $this->expectException(OffsetOutOfBounds::class);

        Domain::new('secure.example.com')->withLabel(23, 'foo');
    }

    #[DataProvider('rootProvider')]
    public function testWithRoot(string $host, string $expected_with_root, string $expected_without_root): void
    {
        $host = Domain::new($host);

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

    #[DataProvider('getURIProvider')]
    public function testCreateFromUri(Psr7UriInterface|UriInterface $uri, ?string $expected): void
    {
        $domain = Domain::fromUri($uri);

        self::assertSame($expected, $domain->value());
    }

    public static function getURIProvider(): iterable
    {
        return [
            'PSR-7 URI object' => [
                'uri' => Http::new('http://example.com?foo=bar'),
                'expected' => 'example.com',
            ],
            'League URI object' => [
                'uri' => Uri::new('http://example.com?foo=bar'),
                'expected' => 'example.com',
            ],
        ];
    }

    public function testCreateFromAuthority(): void
    {
        $uri = Uri::new('http://example.com:443');
        $auth = Authority::fromUri($uri);

        self::assertEquals(Domain::fromUri($uri), Domain::fromAuthority($auth));
    }

    public function testSlice(): void
    {
        $domain = Domain::new('ulb.ac.be');

        self::assertSame($domain->value(), $domain->slice(-3)->value());
        self::assertSame($domain->value(), $domain->slice(0)->value());

        self::assertSame('ulb.ac', $domain->slice(1)->value());
        self::assertSame('ulb', $domain->slice(-1)->value());
        self::assertSame('be', $domain->slice(-3, 1)->value());
    }

    public function testSliceThrowsOnOverFlow(): void
    {
        $this->expectException(OffsetOutOfBounds::class);

        Domain::new('ulb.ac.be')->slice(5);
    }

    public function testCreateFromComponentsThrowsException7(): void
    {
        self::expectException(SyntaxError::class);

        Domain::new(str_repeat('A', 255));
    }

    #[DataProvider('provideSubdomainCases')]
    public function test_is_subdomain_of(
        string $child,
        string $parent,
        bool $expected
    ): void {
        $domain = Domain::tryNew($child);

        self::assertInstanceOf(Domain::class, $domain);
        self::assertSame($expected, $domain->isSubdomainOf($parent), sprintf('"%s" isSubDomainOf "%s"', $child, $parent));
    }

    public static function provideSubdomainCases(): iterable
    {
        yield 'direct subdomain' => [
            'foo.example.com',
            'example.com',
            true,
        ];

        yield 'nested subdomain' => [
            'bar.foo.example.com',
            'example.com',
            true,
        ];

        yield 'identical domain' => [
            'example.com',
            'example.com',
            false,
        ];

        yield 'parent is child' => [
            'example.com',
            'foo.example.com',
            false,
        ];

        yield 'sibling domain' => [
            'foo.example.com',
            'bar.example.com',
            false,
        ];

        yield 'similar suffix but not subdomain' => [
            'evil-example.com',
            'example.com',
            false,
        ];

        yield 'tld only parent' => [
            'example.com',
            'com',
            true,
        ];

        yield 'invalid parent domain' => [
            'foo.example.com',
            'not a domain',
            false,
        ];
    }

    #[DataProvider('provideHasSubdomainCases')]
    public function test_has_subdomain(
        string $parent,
        string $child,
        bool $expected
    ): void {
        $domain = Domain::tryNew($parent);

        self::assertInstanceOf(Domain::class, $domain);
        self::assertSame($expected, $domain->hasSubdomain($child), sprintf('"%s" hasSubdomain "%s"', $parent, $child));
    }

    public static function provideHasSubdomainCases(): iterable
    {
        yield 'direct subdomain' => [
            'example.com',
            'foo.example.com',
            true,
        ];

        yield 'nested subdomain' => [
            'example.com',
            'bar.foo.example.com',
            true,
        ];

        yield 'identical domain' => [
            'example.com',
            'example.com',
            false,
        ];

        yield 'parent passed as child' => [
            'foo.example.com',
            'example.com',
            false,
        ];

        yield 'sibling domain' => [
            'foo.example.com',
            'bar.example.com',
            false,
        ];

        yield 'unrelated domain' => [
            'example.com',
            'evil.com',
            false,
        ];

        yield 'suffix collision' => [
            'example.com',
            'evil-example.com',
            false,
        ];
    }

    public function test_symmetry_with_is_subdomain_of_with_idn_and_rooted_hosts(): void
    {
        $parent = Domain::new('bébê.com');
        $child = Domain::new('foo.xn--bb-bjaf.com.');

        self::assertTrue($child->isSubdomainOf($parent));
        self::assertTrue($parent->hasSubdomain($child));
        self::assertFalse($parent->isSubdomainOf($child));
        self::assertFalse($child->hasSubdomain($parent));
    }

    public function test_sibling_is_symmetric(): void
    {
        $a = Domain::new('foo.example.com');
        $b = Domain::new('bar.example.com');

        self::assertTrue($a->isSiblingOf($b));
        self::assertTrue($b->isSiblingOf($a));
    }

    public function test_parent_and_child_are_not_siblings(): void
    {
        $parent = Domain::new('example.com');
        $child  = Domain::new('foo.example.com');

        self::assertFalse($parent->isSiblingOf($child));
        self::assertFalse($child->isSiblingOf($parent));
    }

    public function test_identical_domains_are_not_siblings(): void
    {
        $domain = Domain::new('foo.example.com');

        self::assertFalse($domain->isSiblingOf($domain));
    }

    #[DataProvider('provideSiblingCases')]
    public function test_is_sibling_of(string $hostA, string $hostB, bool $expected): void
    {
        self::assertSame(
            $expected,
            Domain::new($hostA)->isSiblingOf($hostB),
            sprintf('"%s" isSiblingOf "%s"', $hostA, $hostB)
        );
    }

    public static function provideSiblingCases(): iterable
    {
        // ✅ True siblings
        yield 'simple siblings' => [
            'foo.example.com',
            'bar.example.com',
            true,
        ];

        yield 'multi-level siblings' => [
            'a.b.example.com',
            'c.b.example.com',
            true,
        ];

        // ❌ Parent vs child
        yield 'parent vs child' => [
            'example.com',
            'foo.example.com',
            false,
        ];

        yield 'child vs parent' => [
            'foo.example.com',
            'example.com',
            false,
        ];

        // ❌ Identical hosts
        yield 'identical hosts' => [
            'foo.example.com',
            'foo.example.com',
            false,
        ];

        // ❌ Different parents
        yield 'different parents' => [
            'foo.example.com',
            'bar.example.org',
            false,
        ];

        // ✅ Only TLDs
        yield 'only tld hosts' => [
            'com',
            'org',
            true,
        ];

        // ✅ IDN siblings
        yield 'idn siblings' => [
            'bébê.com',
            'fôo.com',
            true,
        ];

        // ✅ Trailing dots
        yield 'siblings with trailing dots' => [
            'foo.example.com.',
            'bar.example.com.',
            true,
        ];

        // ❌ Multi-level vs shallow
        yield 'grandchild vs child' => [
            'baz.foo.example.com',
            'bar.example.com',
            false,
        ];
    }

    #[Test]
    public function it_returns_the_lowest_common_ancestor_of_two_domains(): void
    {
        $a = Domain::new('foo.example.com');
        $b = Domain::new('bar.example.com');

        self::assertSame('example.com', $a->commonAncestorWith($b)->toAscii());
    }

    #[Test]
    public function it_returns_the_deepest_shared_parent(): void
    {
        $a = Domain::new('a.b.example.com');
        $b = Domain::new('c.b.example.com');

        self::assertSame('b.example.com', $a->commonAncestorWith($b)->toAscii());
    }

    #[Test]
    public function it_returns_the_parent_when_one_domain_is_a_subdomain_of_the_other(): void
    {
        $parent = Domain::new('example.com');
        $child  = Domain::new('foo.example.com');

        self::assertSame('example.com', $parent->commonAncestorWith($child)->toAscii());
        self::assertSame('example.com', $child->commonAncestorWith($parent)->toAscii());
    }

    #[Test]
    public function it_returns_itself_when_domains_are_identical(): void
    {
        $domain = Domain::new('foo.example.com');

        self::assertSame('foo.example.com', $domain->commonAncestorWith($domain)->toAscii());
    }

    #[Test]
    public function it_returns_the_root_when_there_is_no_common_ancestor(): void
    {
        $a = Domain::new('example.com');
        $b = Domain::new('example.org');

        self::assertTrue($a->commonAncestorWith($b)->isEmpty());
    }

    #[Test]
    public function it_supports_idn_and_trailing_dot_normalization(): void
    {
        $a = Domain::new('foo.bébê.com');
        $b = Domain::new('bar.xn--bb-bjaf.com.');

        self::assertSame('xn--bb-bjaf.com', $a->commonAncestorWith($b)->toAscii());
    }

    #[Test]
    public function it_returns_root_when_other_host_is_null_or_invalid(): void
    {
        $domain = Domain::new('example.com');

        self::assertTrue($domain->commonAncestorWith(null)->isEmpty());
        self::assertTrue($domain->commonAncestorWith('not a host')->isEmpty());
    }

    #[Test]
    public function it_is_symmetric(): void
    {
        $a = Domain::new('a.b.example.com');
        $b = Domain::new('c.b.example.com');

        self::assertTrue($a->commonAncestorWith($b)->equals($b->commonAncestorWith($a)));
    }
}

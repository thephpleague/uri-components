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

use Generator;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Http;
use League\Uri\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;

#[CoversClass(Scheme::class)]
#[Group('scheme')]
final class SchemeTest extends TestCase
{
    public function testWithContent(): void
    {
        self::assertEquals(Scheme::new('ftp'), Scheme::new('FtP'));
    }

    #[DataProvider('validSchemeProvider')]
    public function testValidScheme(
        Stringable|string|null $scheme,
        string $toString,
        string $uriComponent
    ): void {
        $scheme = null !== $scheme ? Scheme::new($scheme) : Scheme::new();

        self::assertSame($toString, (string) $scheme);
        self::assertSame($uriComponent, $scheme->getUriComponent());
    }

    public static function validSchemeProvider(): array
    {
        return [
            [null, '', ''],
            [Scheme::new('foo'), 'foo', 'foo:'],
            [new class () {
                public function __toString(): string
                {
                    return 'foo';
                }
            }, 'foo', 'foo:'],
            ['a', 'a', 'a:'],
            ['ftp', 'ftp', 'ftp:'],
            ['HtTps', 'https', 'https:'],
            ['wSs', 'wss', 'wss:'],
            ['telnEt', 'telnet', 'telnet:'],
        ];
    }

    #[DataProvider('invalidSchemeProvider')]
    public function testInvalidScheme(string $scheme): void
    {
        $this->expectException(SyntaxError::class);

        Scheme::new($scheme);
    }

    public static function invalidSchemeProvider(): array
    {
        return [
            'empty string' => [''],
            'invalid char' => ['in,valid'],
            'integer like string' => ['123'],
        ];
    }

    #[DataProvider('getURIProvider')]
    public function testCreateFromUri(UriInterface|Psr7UriInterface $uri, ?string $expected): void
    {
        self::assertSame($expected, Scheme::fromUri($uri)->value());
    }

    public static function getURIProvider(): iterable
    {
        return [
            'PSR-7 URI object' => [
                'uri' => Http::new('http://example.com?foo=bar'),
                'expected' => 'http',
            ],
            'PSR-7 URI object with no scheme' => [
                'uri' => Http::new('//example.com/path'),
                'expected' => null,
            ],
            'League URI object' => [
                'uri' => Uri::new('http://example.com?foo=bar'),
                'expected' => 'http',
            ],
            'League URI object with no scheme' => [
                'uri' => Uri::new('//example.com/path'),
                'expected' => null,
            ],
        ];
    }

    #[DataProvider('getSchemeInfoProvider')]
    public function it_can_detect_information_about_special_schemes(
        ?string $scheme,
        bool $isHttp,
        bool $isWebsocket,
        bool $isSsl,
        bool $isSpecial,
        Port $defaultPort,
    ): void {
        $schemeObject = Scheme::new($scheme);

        self::assertSame($isHttp, $schemeObject->isHttp());
        self::assertSame($isWebsocket, $schemeObject->isWebsocket());
        self::assertSame($isSsl, $schemeObject->isSsl());
        self::assertSame($isSpecial, $schemeObject->isWhatWgSpecial());
        self::assertSame($defaultPort, $schemeObject->defaultPort());
    }

    public static function getSchemeInfoProvider(): Generator
    {
        yield 'detect an HTTP URL' => [
            'scheme' => 'http',
            'isHttp' => true,
            'isWebsocket' => false,
            'isSsl' => false,
            'isSpecial' => true,
            Port::new(80),
        ];

        yield 'detect an WSS URL' => [
            'scheme' => 'wss',
            'isHttp' => false,
            'isWebsocket' => true,
            'isSsl' => true,
            'isSpecial' => true,
            Port::new(443),
        ];

        yield 'detect an email URL' => [
            'scheme' => 'email',
            'isHttp' => false,
            'isWebsocket' => false,
            'isSsl' => false,
            'isSpecial' => false,
            Port::new(null),
        ];
    }
}

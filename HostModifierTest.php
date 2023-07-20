<?php

/**
 * League.Uri (https://uri.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Uri;

use GuzzleHttp\Psr7\Utils;
use League\Uri\Exceptions\SyntaxError;
use PHPUnit\Framework\TestCase;

/**
 * @group host
 * @group resolution
 * @coversDefaultClass \League\Uri\UriModifier
 */
final class HostModifierTest extends TestCase
{
    private readonly string $uri;
    private readonly Modifier $modifier;

    protected function setUp(): void
    {
        $this->uri = 'http://www.example.com/path/to/the/sky.php?kingkong=toto&foo=bar+baz#doc3';
        $this->modifier = Modifier::from($this->uri);
    }

    /**
     * @dataProvider validHostProvider
     */
    public function testPrependLabelProcess(string $label, int $key, string $prepend, string $append, string $replace): void
    {
        self::assertSame($prepend, $this->modifier->prependLabel($label)->get()->getHost());
    }

    /**
     * @dataProvider validHostProvider
     */
    public function testAppendLabelProcess(string $label, int $key, string $prepend, string $append, string $replace): void
    {
        self::assertSame($append, $this->modifier->appendLabel($label)->get()->getHost());
    }

    /**
     * @dataProvider validHostProvider
     */
    public function testReplaceLabelProcess(string $label, int $key, string $prepend, string $append, string $replace): void
    {
        self::assertSame($replace, $this->modifier->replaceLabel($key, $label)->get()->getHost());
    }

    public static function validHostProvider(): array
    {
        return [
            ['toto', 2, 'toto.www.example.com', 'www.example.com.toto', 'toto.example.com'],
            ['123', 1, '123.www.example.com', 'www.example.com.123', 'www.123.com'],
        ];
    }

    public function testAppendLabelWithIpv4Host(): void
    {
        $uri = Http::new('http://127.0.0.1/foo/bar');

        self::assertSame(
            '127.0.0.1.localhost',
            Modifier::from($uri)->appendLabel('.localhost')->get()->getHost()
        );
    }

    public function testAppendLabelThrowsWithOtherIpHost(): void
    {
        $this->expectException(SyntaxError::class);

        Modifier::from(Http::new('http://[::1]/foo/bar'))->appendLabel('.localhost');
    }

    public function testPrependLabelWithIpv4Host(): void
    {
        $uri = Http::new('http://127.0.0.1/foo/bar');

        self::assertSame(
            'localhost.127.0.0.1',
            Modifier::from($uri)->prependLabel('localhost.')->get()->getHost()
        );
    }

    public function testAppendNulLabel(): void
    {
        $uri = Uri::new('http://127.0.0.1');

        self::assertSame($uri, Modifier::from($uri)->appendLabel(null)->get());
    }

    public function testPrependLabelThrowsWithOtherIpHost(): void
    {
        $this->expectException(SyntaxError::class);

        Modifier::from(Http::new('http://[::1]/foo/bar'))->prependLabel('.localhost');
    }

    public function testPrependNullLabel(): void
    {
        $uri = Uri::new('http://127.0.0.1');

        self::assertSame($uri, Modifier::from($uri)->prependLabel(null)->get());
    }

    public function testHostToAsciiProcess(): void
    {
        $uri = Uri::new('http://مثال.إختبار/where/to/go');

        self::assertSame(
            'http://xn--mgbh0fb.xn--kgbechtv/where/to/go',
            (string)  Modifier::from($uri)->hostToAscii()
        );
    }

    public function testWithoutZoneIdentifierProcess(): void
    {
        $uri = Http::new('http://[fe80::1234%25eth0-1]/path/to/the/sky.php');

        self::assertSame(
            'http://[fe80::1234]/path/to/the/sky.php',
            (string) Modifier::from($uri)->removeZoneId()
        );
    }

    /**
     * @dataProvider validwithoutLabelProvider
     */
    public function testwithoutLabelProcess(array $keys, string $expected): void
    {
        self::assertSame($expected, $this->modifier->removeLabels(...$keys)->get()->getHost());
    }

    public static function validwithoutLabelProvider(): array
    {
        return [
            [[1], 'www.com'],
        ];
    }

    public function testRemoveLabels(): void
    {
        self::assertSame('example.com', $this->modifier->removeLabels(2)->get()->getHost());
    }

    public function testAddRootLabel(): void
    {
        self::assertSame('www.example.com.', $this->modifier->addRootLabel()->addRootLabel()->get()->getHost());
    }

    public function testRemoveRootLabel(): void
    {
        self::assertSame('www.example.com', $this->modifier->addRootLabel()->removeRootLabel()->get()->getHost());
        self::assertSame('www.example.com', $this->modifier->removeRootLabel()->get()->getHost());
    }

    public function testItCanBeJsonSerialize(): void
    {
        $uri = Http::new($this->uri);

        self::assertSame(json_encode($uri), json_encode($this->modifier));
    }

    public function testItCanConvertHostToUnicode(): void
    {
        $uriString = 'http://bébé.be';
        $uri = (string) Http::new($uriString);
        $modifier = Modifier::from(Utils::uriFor($uri));

        self::assertSame('http://xn--bb-bjab.be', $uri);
        self::assertSame('http://xn--bb-bjab.be', (string) $modifier);
        self::assertSame($uriString, (string) $modifier->hostToUnicode());
    }
}

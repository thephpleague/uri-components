<?php

/**
 * League.Uri (https://uri.thephpleague.com/components/2.0/)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Uri;

use GuzzleHttp\Psr7\Uri as GuzzleUri;
use League\Uri\Exceptions\OffsetOutOfBounds;
use League\Uri\Exceptions\SyntaxError;
use PHPUnit\Framework\TestCase;

/**
 * @group host
 * @group resolution
 * @coversDefaultClass \League\Uri\UriModifier
 */
final class HostModifierTest extends TestCase
{
    /**
     * @var Http
     */
    private $uri;

    protected function setUp(): void
    {
        $this->uri = Http::createFromString(
            'http://www.example.com/path/to/the/sky.php?kingkong=toto&foo=bar+baz#doc3'
        );
    }

    /**
     * @dataProvider validHostProvider
     *
     * @covers ::prependLabel
     * @covers ::normalizeComponent
     */
    public function testPrependLabelProcess(string $label, int $key, string $prepend, string $append, string $replace): void
    {
        self::assertSame($prepend, UriModifier::prependLabel($this->uri, $label)->getHost());
    }

    /**
     * @dataProvider validHostProvider
     *
     * @covers ::appendLabel
     * @covers ::normalizeComponent
     */
    public function testAppendLabelProcess(string $label, int $key, string $prepend, string $append, string $replace): void
    {
        self::assertSame($append, UriModifier::appendLabel($this->uri, $label)->getHost());
    }

    /**
     * @dataProvider validHostProvider
     *
     * @covers ::replaceLabel
     * @covers ::normalizeComponent
     */
    public function testReplaceLabelProcess(string $label, int $key, string $prepend, string $append, string $replace): void
    {
        self::assertSame($replace, UriModifier::replaceLabel($this->uri, $key, $label)->getHost());
    }

    public function validHostProvider(): array
    {
        return [
            ['toto', 2, 'toto.www.example.com', 'www.example.com.toto', 'toto.example.com'],
            ['123', 1, '123.www.example.com', 'www.example.com.123', 'www.123.com'],
        ];
    }

    public function testAppendLabelWithIpv4Host(): void
    {
        $uri = Http::createFromString('http://127.0.0.1/foo/bar');

        self::assertSame('127.0.0.1.localhost', UriModifier::appendLabel($uri, '.localhost')->getHost());
    }

    public function testAppendLabelThrowsWithOtherIpHost(): void
    {
        $this->expectException(SyntaxError::class);

        $uri = Http::createFromString('http://[::1]/foo/bar');
        UriModifier::appendLabel($uri, '.localhost');
    }

    public function testPrependLabelWithIpv4Host(): void
    {
        $uri = Http::createFromString('http://127.0.0.1/foo/bar');
        self::assertSame('localhost.127.0.0.1', UriModifier::prependLabel($uri, 'localhost.')->getHost());
    }

    public function testAppendNulLabel(): void
    {
        $uri = Uri::createFromString('http://127.0.0.1');
        self::assertSame($uri, UriModifier::appendLabel($uri, null));
    }

    public function testPrependLabelThrowsWithOtherIpHost(): void
    {
        $this->expectException(SyntaxError::class);
        $uri = Http::createFromString('http://[::1]/foo/bar');
        UriModifier::prependLabel($uri, '.localhost');
    }

    public function testPrependNullLabel(): void
    {
        $uri = Uri::createFromString('http://127.0.0.1');
        self::assertSame($uri, UriModifier::prependLabel($uri, null));
    }

    /**
     * @covers ::hostToAscii
     * @covers ::normalizeComponent
     */
    public function testHostToAsciiProcess(): void
    {
        $uri = new GuzzleUri('http://مثال.إختبار/where/to/go');
        self::assertSame(
            'http://xn--mgbh0fb.xn--kgbechtv/where/to/go',
            (string)  UriModifier::hostToAscii($uri)
        );
    }

    /**
     * @covers ::hostToUnicode
     * @covers ::normalizeComponent
     */
    public function testHostToUnicodeProcess(): void
    {
        $uri = new GuzzleUri('http://xn--mgbh0fb.xn--kgbechtv/where/to/go');
        $expected = 'http://مثال.إختبار/where/to/go';

        self::assertSame($expected, (string) UriModifier::hostToUnicode($uri));
    }

    /**
     * @covers ::removeZoneId
     * @covers ::normalizeComponent
     */
    public function testWithoutZoneIdentifierProcess(): void
    {
        $uri = Http::createFromString('http://[fe80::1234%25eth0-1]/path/to/the/sky.php');
        self::assertSame(
            'http://[fe80::1234]/path/to/the/sky.php',
            (string) UriModifier::removeZoneId($uri)
        );
    }

    /**
     * @dataProvider validwithoutLabelProvider
     *
     * @covers ::removeLabels
     * @covers ::normalizeComponent
     */
    public function testwithoutLabelProcess(array $keys, string $expected): void
    {
        self::assertSame($expected, UriModifier::removeLabels($this->uri, ...$keys)->getHost());
    }

    public function validwithoutLabelProvider(): array
    {
        return [
            [[1], 'www.com'],
        ];
    }

    /**
     * @covers ::removeLabels
     * @covers ::normalizeComponent
     */
    public function testRemoveLabels(): void
    {
        self::assertSame('example.com', UriModifier::removeLabels($this->uri, 2)->getHost());
    }

    /**
     * @dataProvider invalidRemoveLabelsParameters
     *
     * @covers ::removeLabels
     * @covers ::normalizeComponent
     */
    public function testRemoveLabelsFailedConstructor(array $params): void
    {
        $this->expectException(OffsetOutOfBounds::class);
        UriModifier::removeLabels($this->uri, ...$params);
    }

    public function invalidRemoveLabelsParameters(): array
    {
        return [
            'array contains float' => [[1, 2, 3.1]],
        ];
    }

    /**
     * @covers ::addRootLabel
     */
    public function testAddRootLabel(): void
    {
        self::assertSame('www.example.com.', UriModifier::addRootLabel($this->uri)->getHost());
    }

    /**
     * @covers ::removeRootLabel
     */
    public function testRemoveRootLabel(): void
    {
        self::assertSame('www.example.com', UriModifier::removeRootLabel($this->uri)->getHost());
    }
}

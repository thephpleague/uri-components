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

namespace LeagueTest\Uri;

use League\Uri\Component\Host;
use League\Uri\Exception\OffsetOutOfBounds;
use League\Uri\Exception\SyntaxError;
use League\Uri\Http;
use League\Uri\UriModifier;
use PHPUnit\Framework\TestCase;
use Zend\Diactoros\Uri as ZendUri;

/**
 * @group host
 * @group resolution
 * @coversDefaultClass \League\Uri\UriModifier
 */
class HostModifierTest extends TestCase
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
     */
    public function testPrependLabelProcess(string $label, int $key, string $prepend, string $append, string $replace): void
    {
        self::assertSame($prepend, UriModifier::prependLabel($this->uri, $label)->getHost());
    }

    /**
     * @dataProvider validHostProvider
     *
     * @covers ::appendLabel
     */
    public function testAppendLabelProcess(string $label, int $key, string $prepend, string $append, string $replace): void
    {
        self::assertSame($append, UriModifier::appendLabel($this->uri, $label)->getHost());
    }

    /**
     * @dataProvider validHostProvider
     *
     * @covers ::replaceLabel
     *
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
        self::expectException(SyntaxError::class);
        $uri = Http::createFromString('http://[::1]/foo/bar');
        UriModifier::appendLabel($uri, '.localhost');
    }

    public function testPrependLabelWithIpv4Host(): void
    {
        $uri = Http::createFromString('http://127.0.0.1/foo/bar');
        self::assertSame('localhost.127.0.0.1', UriModifier::prependLabel($uri, 'localhost.')->getHost());
    }

    public function testPrependLabelThrowsWithOtherIpHost(): void
    {
        self::expectException(SyntaxError::class);
        $uri = Http::createFromString('http://[::1]/foo/bar');
        UriModifier::prependLabel($uri, '.localhost');
    }

    /**
     * @covers ::hostToAscii
     */
    public function testHostToAsciiProcess(): void
    {
        $uri = Http::createFromString('http://مثال.إختبار/where/to/go');
        self::assertSame(
            'http://xn--mgbh0fb.xn--kgbechtv/where/to/go',
            (string)  UriModifier::hostToAscii($uri)
        );
    }

    /**
     * @covers ::hostToUnicode
     */
    public function testHostToUnicodeProcess(): void
    {
        $uri = new ZendUri('http://xn--mgbh0fb.xn--kgbechtv/where/to/go');
        $expected = 'http://مثال.إختبار/where/to/go';
        self::assertSame($expected, (string) UriModifier::hostToUnicode($uri));
    }

    /**
     * @covers ::removeZoneId
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
     */
    public function testRemoveLabels(): void
    {
        self::assertSame('example.com', UriModifier::removeLabels($this->uri, 2)->getHost());
    }

    /**
     * @dataProvider invalidRemoveLabelsParameters
     *
     * @covers ::removeLabels
     */
    public function testRemoveLabelsFailedConstructor(array $params): void
    {
        self::expectException(OffsetOutOfBounds::class);
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

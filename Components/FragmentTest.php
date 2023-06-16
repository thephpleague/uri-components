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

use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Http;
use League\Uri\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;

/**
 * @group fragment
 * @coversDefaultClass \League\Uri\Components\Fragment
 */
final class FragmentTest extends TestCase
{
    /**
     * @dataProvider getUriComponentProvider
     */
    public function testStringRepresentation(?string $str, string $encoded): void
    {
        $fragment = null !== $str ? Fragment::fromString($str) : Fragment::new();

        self::assertSame($encoded, (string) $fragment);
    }

    public static function getUriComponentProvider(): array
    {
        $unreserved = 'a-zA-Z0-9.-_~!$&\'()*+,;=:@';

        return [
            'null' => [null, ''],
            'empty' => ['', ''],
            'evaluate empty' => ['0', '0'],
            'hash' => ['#', '%23'],
            'toofan' => ['toofan', 'toofan'],
            'notencoded' => ["azAZ0-9/?-._~!$&'()*+,;=:@", 'azAZ0-9/?-._~!$&\'()*+,;=:@'],
            'encoded' => ['%^[]{}"<>\\', '%25%5E%5B%5D%7B%7D%22%3C%3E%5C'],
            'Percent encode spaces' => ['frag ment', 'frag%20ment'],
            'Percent encode multibyte' => ['€', '%E2%82%AC'],
            "Don't encode something that's already encoded" => ['frag%20ment', 'frag%20ment'],
            'Percent encode invalid percent encodings' => ['frag%2-ment', 'frag%252-ment'],
            "Don't encode path segments" => ['frag/ment', 'frag/ment'],
            "Don't encode unreserved chars or sub-delimiters" => [$unreserved, $unreserved],
            'Encoded unreserved chars are not decoded' => ['fr%61gment', 'fr%61gment'],
        ];
    }

    /**
     * @dataProvider geValueProvider
     */
    public function testGetValue(UriComponentInterface|Stringable|string|null $str, ?string $expected): void
    {
        if ($str instanceof UriComponentInterface) {
            $str = $str->value();
        }

        $fragment = null !== $str ? Fragment::fromString($str) : Fragment::new();

        self::assertSame($expected, $fragment->decoded());
    }

    public static function geValueProvider(): array
    {
        return [
            [Fragment::new(), null],
            [null, null],
            ['', ''],
            ['0', '0'],
            ['azAZ0-9/?-._~!$&\'()*+,;=:@%^/[]{}\"<>\\', 'azAZ0-9/?-._~!$&\'()*+,;=:@%^/[]{}\"<>\\'],
            ['€', '€'],
            ['%E2%82%AC', '€'],
            ['frag ment', 'frag ment'],
            ['frag%20ment', 'frag ment'],
            ['frag%2-ment', 'frag%2-ment'],
            ['fr%61gment', 'fr%61gment'],
            ['frag%2Bment', 'frag%2Bment'],
            ['frag+ment', 'frag+ment'],
        ];
    }

    /**
     * @dataProvider getContentProvider
     */
    public function testGetContent(string $input, string $expected): void
    {
        self::assertSame($expected, Fragment::fromString($input)->value());
    }

    public static function getContentProvider(): array
    {
        return [
            ['€', '%E2%82%AC'],
            ['%E2%82%AC', '%E2%82%AC'],
            ['action=v%61lue', 'action=v%61lue'],
        ];
    }

    public function testFailedFragmentException(): void
    {
        $this->expectException(SyntaxError::class);

        Fragment::fromString("\0");
    }

    public function testGetUriComponent(): void
    {
        self::assertSame('#yolo', Fragment::fromString('yolo')->getUriComponent());
        self::assertEquals('', Fragment::new()->getUriComponent());
    }

    public function testJsonSerialize(): void
    {
        self::assertEquals('"yolo"', json_encode(Fragment::fromString('yolo')));
    }

    public function testPreserverDelimiter(): void
    {
        $fragment = Fragment::new();
        self::assertNull($fragment->value());
        self::assertSame('', $fragment->toString());
    }

    /**
     * @dataProvider getURIProvider
     */
    public function testCreateFromUri(Psr7UriInterface|UriInterface $uri, ?string $expected): void
    {
        $fragment = Fragment::fromUri($uri);

        self::assertSame($expected, $fragment->value());
    }

    public static function getURIProvider(): iterable
    {
        return [
            'PSR-7 URI object' => [
                'uri' => Http::fromString('http://example.com#foobar'),
                'expected' => 'foobar',
            ],
            'PSR-7 URI object with no fragment' => [
                'uri' => Http::fromString('http://example.com'),
                'expected' => null,
            ],
            'PSR-7 URI object with empty string fragment' => [
                'uri' => Http::fromString('http://example.com#'),
                'expected' => null,
            ],
            'League URI object' => [
                'uri' => Uri::fromString('http://example.com#foobar'),
                'expected' => 'foobar',
            ],
            'League URI object with no fragment' => [
                'uri' => Uri::fromString('http://example.com'),
                'expected' => null,
            ],
            'League URI object with empty string fragment' => [
                'uri' => Uri::fromString('http://example.com#'),
                'expected' => '',
            ],
        ];
    }
}

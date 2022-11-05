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

namespace League\Uri\Components;

use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Http;
use League\Uri\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use function var_export;

/**
 * @group fragment
 * @coversDefaultClass \League\Uri\Components\Fragment
 */
final class FragmentTest extends TestCase
{
    /**
     * @dataProvider getUriComponentProvider
     *
     * @covers ::__construct
     * @covers ::validateComponent
     * @covers ::filterComponent
     * @covers ::__toString
     *
     * @param ?string $str
     */
    public function testStringRepresentation(?string $str, string $encoded): void
    {
        self::assertSame($encoded, (string) new Fragment($str));
    }

    public function getUriComponentProvider(): array
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
     *
     * @covers ::__construct
     * @covers ::validateComponent
     * @covers ::filterComponent
     * @covers ::decoded
     * @covers ::encodeComponent
     * @covers ::encodeMatches
     * @covers ::decodeMatches
     * @param ?string $expected
     */
    public function testGetValue(Stringable|float|int|string|bool|null $str, ?string $expected): void
    {
        self::assertSame($expected, (new Fragment($str))->decoded());
    }

    public function geValueProvider(): array
    {
        return [
            [new Fragment(), null],
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
     *
     * @covers ::__construct
     * @covers ::validateComponent
     * @covers ::filterComponent
     * @covers ::value
     * @covers ::encodeMatches
     * @covers ::decodeMatches
     */
    public function testGetContent(string $input, string $expected): void
    {
        self::assertSame($expected, (new Fragment($input))->value());
    }

    public function getContentProvider(): array
    {
        return [
            ['€', '%E2%82%AC'],
            ['%E2%82%AC', '%E2%82%AC'],
            ['action=v%61lue', 'action=v%61lue'],
        ];
    }

    /**
     * @covers ::filterComponent
     */
    public function testFailedFragmentException(): void
    {
        $this->expectException(SyntaxError::class);
        new Fragment("\0");
    }

    /**
     * @covers ::__set_state
     */
    public function testSetState(): void
    {
        $component = new Fragment('yolo');
        $generateComponent = eval('return '.var_export($component, true).';');
        self::assertEquals($component, $generateComponent);
    }

    /**
     * @covers ::getUriComponent
     */
    public function testGetUriComponent(): void
    {
        self::assertSame('#yolo', (new Fragment('yolo'))->getUriComponent());
        self::assertEquals('', (new Fragment())->getUriComponent());
    }

    /**
     * @covers ::jsonSerialize
     */
    public function testJsonSerialize(): void
    {
        $component = new Fragment('yolo');
        self::assertEquals('"yolo"', json_encode($component));
    }

    /**
     * @covers ::__toString
     * @covers ::validateComponent
     * @covers ::withContent
     * @covers ::decodeMatches
     */
    public function testPreserverDelimiter(): void
    {
        $fragment = new Fragment();
        $altFragment = $fragment->withContent(null);
        self::assertSame($fragment, $altFragment);
        self::assertNull($altFragment->value());
        self::assertSame('', $altFragment->__toString());
    }

    /**
     * @covers ::withContent
     * @covers ::encodeMatches
     * @covers ::decodeMatches
     */
    public function testWithContent(): void
    {
        $fragment = new Fragment('coucou');
        self::assertSame($fragment, $fragment->withContent('coucou'));
        self::assertNotSame($fragment, $fragment->withContent('Coucou'));
    }

    /**
     * @dataProvider getURIProvider
     * @covers ::createFromUri
     * @param ?string $expected
     */
    public function testCreateFromUri(Psr7UriInterface|UriInterface $uri, ?string $expected): void
    {
        $fragment = Fragment::createFromUri($uri);

        self::assertSame($expected, $fragment->value());
    }

    public function getURIProvider(): iterable
    {
        return [
            'PSR-7 URI object' => [
                'uri' => Http::createFromString('http://example.com#foobar'),
                'expected' => 'foobar',
            ],
            'PSR-7 URI object with no fragment' => [
                'uri' => Http::createFromString('http://example.com'),
                'expected' => null,
            ],
            'PSR-7 URI object with empty string fragment' => [
                'uri' => Http::createFromString('http://example.com#'),
                'expected' => null,
            ],
            'League URI object' => [
                'uri' => Uri::createFromString('http://example.com#foobar'),
                'expected' => 'foobar',
            ],
            'League URI object with no fragment' => [
                'uri' => Uri::createFromString('http://example.com'),
                'expected' => null,
            ],
            'League URI object with empty string fragment' => [
                'uri' => Uri::createFromString('http://example.com#'),
                'expected' => '',
            ],
        ];
    }
}

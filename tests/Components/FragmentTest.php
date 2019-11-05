<?php

/**
 * League.Uri (http://uri.thephpleague.com/components)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.1
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Uri\Components;

use League\Uri\Components\Fragment;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Http;
use League\Uri\Uri;
use PHPUnit\Framework\TestCase;
use TypeError;
use function date_create;
use function var_export;

/**
 * @group fragment
 * @coversDefaultClass \League\Uri\Components\Fragment
 */
class FragmentTest extends TestCase
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
     *
     * @param null|mixed $str
     * @param ?string    $expected
     */
    public function testGetValue($str, ?string $expected): void
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
     * @covers ::getContent
     * @covers ::encodeMatches
     * @covers ::decodeMatches
     */
    public function testGetContent(string $input, string $expected): void
    {
        self::assertSame($expected, (new Fragment($input))->getContent());
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
        self::expectException(SyntaxError::class);
        new Fragment("\0");
    }

    public function testFailedFragmentTypeError(): void
    {
        self::expectException(TypeError::class);
        new Fragment(date_create());
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
        self::assertNull($altFragment->getContent());
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
     *
     * @param mixed   $uri      an URI object
     * @param ?string $expected
     */
    public function testCreateFromUri($uri, ?string $expected): void
    {
        $fragment = Fragment::createFromUri($uri);

        self::assertSame($expected, $fragment->getContent());
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

    public function testCreateFromUriThrowsTypeError(): void
    {
        self::expectException(TypeError::class);

        Fragment::createFromUri('http://example.com#foobar');
    }
}

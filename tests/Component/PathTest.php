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

namespace LeagueTest\Uri\Component;

use League\Uri\Component\Path;
use League\Uri\Exception\MalformedUriComponent;
use PHPUnit\Framework\TestCase;
use TypeError;
use function date_create;
use function var_export;

/**
 * @group path
 * @group defaultpath
 * @coversDefaultClass \League\Uri\Component\Path
 */
class PathTest extends TestCase
{
    /**
     * @dataProvider validPathEncoding
     *
     * @covers ::__construct
     * @covers ::validate
     * @covers ::decodeMatches
     * @covers ::decoded
     * @covers ::getContent
     * @covers ::encodeComponent
     */
    public function testGetUriComponent(string $decoded, string $encoded): void
    {
        $path = new Path($decoded);
        self::assertSame($decoded, $path->decoded());
        self::assertSame($encoded, $path->getContent());
    }

    public function validPathEncoding(): array
    {
        return [
            [
                'toto',
                'toto',
            ],
            [
                'bar---',
                'bar---',
            ],
            [
                '',
                '',
                '',
            ],
            [
                '"bad"',
                '%22bad%22',
            ],
            [
                '<not good>',
                '%3Cnot%20good%3E',
            ],
            [
                '{broken}',
                '%7Bbroken%7D',
            ],
            [
                '`oops`',
                '%60oops%60',
            ],
            [
                '\\slashy',
                '%5Cslashy',
            ],
            [
                'foo^bar',
                'foo%5Ebar',
            ],
            [
                'foo^bar/baz',
                'foo%5Ebar/baz',
            ],
            [
                'foo%2Fbar',
                'foo%2Fbar',
            ],
            [
                '/v1/people/%7E:(first-name,last-name,email-address,picture-url)',
                '/v1/people/%7E:(first-name,last-name,email-address,picture-url)',
            ],
            [
                '/v1/people/~:(first-name,last-name,email-address,picture-url)',
                '/v1/people/~:(first-name,last-name,email-address,picture-url)',
            ],
            [
                'foo%2520bar',
                'foo%2520bar',
            ],
        ];
    }

    public function testWithContent(): void
    {
        $component = new Path('this is a normal path');
        self::assertSame($component, $component->withContent($component));
        self::assertNotSame($component, $component->withContent('new/path'));
    }

    /**
     * @dataProvider invalidPath
     *
     * @param mixed|null $path
     */
    public function testConstructorThrowsWithInvalidData($path): void
    {
        self::expectException(TypeError::class);
        new Path($path);
    }

    public function invalidPath(): array
    {
        return [
            [date_create()],
            [[]],
            [null],
        ];
    }

    public function testConstructorThrowsExceptionWithInvalidData(): void
    {
        self::expectException(MalformedUriComponent::class);
        new Path("\0");
    }

    public function testSetState(): void
    {
        $component = new Path(42);
        $generateComponent = eval('return '.var_export($component, true).';');
        self::assertEquals($component, $generateComponent);
    }

    /**
     * Test Removing Dot Segment.
     *
     * @dataProvider normalizeProvider
     */
    public function testWithoutDotSegments(string $path, string $expected): void
    {
        self::assertSame($expected, (new Path($path))->withoutDotSegments()->__toString());
    }

    /**
     * Provides different segment to be normalized.
     */
    public function normalizeProvider(): array
    {
        return [
            ['/a/b/c/./../../g', '/a/g'],
            ['mid/content=5/../6', 'mid/6'],
            ['a/b/c', 'a/b/c'],
            ['a/b/c/.', 'a/b/c/'],
            ['/a/b/c', '/a/b/c'],
        ];
    }

    /**
     * @dataProvider trailingSlashProvider
     */
    public function testHasTrailingSlash(string $path, bool $expected): void
    {
        self::assertSame($expected, (new Path($path))->hasTrailingSlash());
    }

    public function trailingSlashProvider(): array
    {
        return [
            ['/path/to/my/', true],
            ['/path/to/my', false],
            ['path/to/my', false],
            ['path/to/my/', true],
            ['/', true],
            ['', false],
        ];
    }

    /**
     * @dataProvider withTrailingSlashProvider
     */
    public function testWithTrailingSlash(string $path, string $expected): void
    {
        self::assertSame($expected, (string) (new Path($path))->withTrailingSlash());
    }

    public function withTrailingSlashProvider(): array
    {
        return [
            'relative path without ending slash' => ['toto', 'toto/'],
            'absolute path without ending slash' => ['/toto', '/toto/'],
            'root path' => ['/', '/'],
            'empty path' => ['', '/'],
            'relative path with ending slash' => ['toto/', 'toto/'],
            'absolute path with ending slash' => ['/toto/', '/toto/'],
        ];
    }

    /**
     * @dataProvider withoutTrailingSlashProvider
     */
    public function testWithoutTrailingSlash(string $path, string $expected): void
    {
        self::assertSame($expected, (string) (new Path($path))->withoutTrailingSlash());
    }

    public function withoutTrailingSlashProvider(): array
    {
        return [
            'relative path without ending slash' => ['toto', 'toto'],
            'absolute path without ending slash' => ['/toto', '/toto'],
            'root path' => ['/', ''],
            'empty path' => ['', ''],
            'relative path with ending slash' => ['toto/', 'toto'],
            'absolute path with ending slash' => ['/toto/', '/toto'],
        ];
    }

    /**
     * @dataProvider withLeadingSlashProvider
     */
    public function testWithLeadingSlash(string $path, string $expected): void
    {
        self::assertSame($expected, (string) (new Path($path))->withLeadingSlash());
    }

    public function withLeadingSlashProvider(): array
    {
        return [
            'relative path without leading slash' => ['toto', '/toto'],
            'absolute path' => ['/toto', '/toto'],
            'root path' => ['/', '/'],
            'empty path' => ['', '/'],
            'relative path with ending slash' => ['toto/', '/toto/'],
            'absolute path with ending slash' => ['/toto/', '/toto/'],
        ];
    }

    /**
     * @dataProvider withoutLeadingSlashProvider
     */
    public function testWithoutLeadingSlash(string $path, string $expected): void
    {
        self::assertSame($expected, (string) (new Path($path))->withoutLeadingSlash());
    }

    public function withoutLeadingSlashProvider(): array
    {
        return [
            'relative path without ending slash' => ['toto', 'toto'],
            'absolute path without ending slash' => ['/toto', 'toto'],
            'root path' => ['/', ''],
            'empty path' => ['', ''],
            'absolute path with ending slash' => ['/toto/', 'toto/'],
        ];
    }
}

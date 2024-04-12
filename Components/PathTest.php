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

use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Http;
use League\Uri\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface as Psr7UriInterface;

#[CoversClass(Path::class)]
#[Group('path')]
#[Group('defaultpath')]
final class PathTest extends TestCase
{
    #[DataProvider('validPathEncoding')]
    public function testGetUriComponent(string $decoded, string $encoded): void
    {
        $path = Path::new($decoded);

        self::assertSame($decoded, $path->decoded());
        self::assertSame($encoded, $path->value());
    }

    public static function validPathEncoding(): array
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

    public function testConstructorThrowsExceptionWithInvalidData(): void
    {
        $this->expectException(SyntaxError::class);

        Path::new("\0");
    }

    /**
     * Test Removing Dot Segment.
     */
    #[DataProvider('normalizeProvider')]
    public function testWithoutDotSegments(string $path, string $expected): void
    {
        self::assertSame($expected, Path::new($path)->withoutDotSegments()->toString());
    }

    /**
     * Provides different segment to be normalized.
     */
    public static function normalizeProvider(): array
    {
        return [
            ['/a/b/c/./../../g', '/a/g'],
            ['mid/content=5/../6', 'mid/6'],
            ['a/b/c', 'a/b/c'],
            ['a/b/c/.', 'a/b/c/'],
            ['/a/b/c', '/a/b/c'],
        ];
    }

    #[DataProvider('trailingSlashProvider')]
    public function testHasTrailingSlash(string $path, bool $expected): void
    {
        self::assertSame($expected, Path::new($path)->hasTrailingSlash());
    }

    public static function trailingSlashProvider(): array
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

    #[DataProvider('withTrailingSlashProvider')]
    public function testWithTrailingSlash(string $path, string $expected): void
    {
        self::assertSame($expected, (string) Path::new($path)->withTrailingSlash());
    }

    public static function withTrailingSlashProvider(): array
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

    #[DataProvider('withoutTrailingSlashProvider')]
    public function testWithoutTrailingSlash(string $path, string $expected): void
    {
        self::assertSame($expected, (string) Path::new($path)->withoutTrailingSlash());
    }

    public static function withoutTrailingSlashProvider(): array
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

    #[DataProvider('withLeadingSlashProvider')]
    public function testWithLeadingSlash(string $path, string $expected): void
    {
        self::assertSame($expected, (string) Path::new($path)->withLeadingSlash());
    }

    public static function withLeadingSlashProvider(): array
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

    #[DataProvider('withoutLeadingSlashProvider')]
    public function testWithoutLeadingSlash(string $path, string $expected): void
    {
        self::assertSame($expected, (string) Path::new($path)->withoutLeadingSlash());
    }

    public static function withoutLeadingSlashProvider(): array
    {
        return [
            'relative path without ending slash' => ['toto', 'toto'],
            'absolute path without ending slash' => ['/toto', 'toto'],
            'root path' => ['/', ''],
            'empty path' => ['', ''],
            'absolute path with ending slash' => ['/toto/', 'toto/'],
        ];
    }

    #[DataProvider('getURIProvider')]
    public function testCreateFromUri(Psr7UriInterface|UriInterface $uri, ?string $expected): void
    {
        $path = Path::fromUri($uri);

        self::assertSame($expected, $path->value());
    }

    public static function getURIProvider(): iterable
    {
        return [
            'PSR-7 URI object' => [
                'uri' => Http::new('http://example.com/path'),
                'expected' => '/path',
            ],
            'PSR-7 URI object with no path' => [
                'uri' => Http::new('toto://example.com'),
                'expected' => '',
            ],
            'League URI object' => [
                'uri' => Uri::new('http://example.com/path'),
                'expected' => '/path',
            ],
            'League URI object with no path' => [
                'uri' => Uri::new('toto://example.com'),
                'expected' => '',
            ],
        ];
    }
}

<?php

namespace LeagueTest\Uri;

use League\Uri;
use League\Uri\Schemes\Http;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * @group functions
 */
class FunctionsTest extends TestCase
{
    /**
     * @dataProvider uriProvider
     *
     * @covers \League\Uri\is_uri
     * @covers \League\Uri\filter_uri
     * @covers \League\Uri\is_absolute
     * @covers \League\Uri\is_absolute_path
     * @covers \League\Uri\is_network_path
     * @covers \League\Uri\is_relative_path
     * @covers \League\Uri\is_same_document
     *
     * @param mixed  $uri
     * @param mixed  $base_uri
     * @param bool[] $infos
     */
    public function testStat($uri, $base_uri, array $infos)
    {
        if (null !== $base_uri) {
            $this->assertSame($infos['same_document'], Uri\is_same_document($uri, $base_uri));
        }
        $this->assertSame($infos['relative_path'], Uri\is_relative_path($uri));
        $this->assertSame($infos['absolute_path'], Uri\is_absolute_path($uri));
        $this->assertSame($infos['absolute_uri'], Uri\is_absolute($uri));
        $this->assertSame($infos['network_path'], Uri\is_network_path($uri));
    }

    public function uriProvider()
    {
        return [
            'absolute uri' => [
                'uri' => Http::createFromString('http://a/p?q#f'),
                'base_uri' => null,
                'infos' => [
                    'absolute_uri' => true,
                    'network_path' => false,
                    'absolute_path' => false,
                    'relative_path' => false,
                    'same_document' => false,
                ],
            ],
            'network relative uri' => [
                'uri' => Http::createFromString('//스타벅스코리아.com/p?q#f'),
                'base_uri' => Http::createFromString('//xn--oy2b35ckwhba574atvuzkc.com/p?q#z'),
                'infos' => [
                    'absolute_uri' => false,
                    'network_path' => true,
                    'absolute_path' => false,
                    'relative_path' => false,
                    'same_document' => true,
                ],
            ],
            'path absolute uri' => [
                'uri' => Http::createFromString('/p?q#f'),
                'base_uri' => Http::createFromString('/p?a#f'),
                'infos' => [
                    'absolute_uri' => false,
                    'network_path' => false,
                    'absolute_path' => true,
                    'relative_path' => false,
                    'same_document' => false,
                ],
            ],
            'path relative uri with non empty path' => [
                'uri' => Http::createFromString('p?q#f'),
                'base_uri' => null,
                'infos' => [
                    'absolute_uri' => false,
                    'network_path' => false,
                    'absolute_path' => false,
                    'relative_path' => true,
                    'same_document' => false,
                ],
            ],
            'path relative uri with empty' => [
                'uri' => Http::createFromString('?q#f'),
                'base_uri' => null,
                'infos' => [
                    'absolute_uri' => false,
                    'network_path' => false,
                    'absolute_path' => false,
                    'relative_path' => true,
                    'same_document' => false,
                ],
            ],
        ];
    }

    /**
     * @dataProvider failedUriProvider
     *
     * @covers \League\Uri\filter_uri
     * @covers \League\Uri\is_uri
     * @covers \League\Uri\is_same_document
     *
     * @param mixed $uri
     * @param mixed $base_uri
     */
    public function testStatThrowsInvalidArgumentException($uri, $base_uri)
    {
        $this->expectException(TypeError::class);
        Uri\is_same_document($uri, $base_uri);
    }

    public function failedUriProvider()
    {
        return [
            'invalid uri' => [
                'uri' => Http::createFromString('http://a/p?q#f'),
                'base_uri' => 'http://example.com',
            ],
            'invalid base uri' => [
                'uri' => 'http://example.com',
                'base_uri' => Http::createFromString('//a/p?q#f'),
            ],
        ];
    }

    /**
     * @dataProvider functionProvider
     *
     * @covers \League\Uri\is_uri
     * @covers \League\Uri\is_absolute
     * @covers \League\Uri\is_absolute_path
     * @covers \League\Uri\is_network_path
     * @covers \League\Uri\is_relative_path
     *
     * @param string $function
     */
    public function testIsFunctionsThrowsTypeError(string $function)
    {
        $this->expectException(TypeError::class);
        ($function)('http://example.com');
    }

    public function functionProvider()
    {
        return [
            ['\League\Uri\is_absolute'],
            ['\League\Uri\is_network_path'],
            ['\League\Uri\is_absolute_path'],
            ['\League\Uri\is_relative_path'],
        ];
    }
}

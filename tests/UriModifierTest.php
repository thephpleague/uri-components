<?php

namespace LeagueTest\Uri;

use InvalidArgumentException;
use League\Uri;
use League\Uri\Interfaces\Uri as LeagueUriInterface;
use League\Uri\Schemes\Data;
use League\Uri\Schemes\Ftp;
use League\Uri\Schemes\Http;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * @group uri
 * @group modifier
 * @group uri-modifier
 */
class UriModifierTest extends TestCase
{
    const BASE_URI = 'http://a/b/c/d;p?q';

    /**
     * @covers \League\Uri\resolve
     * @covers \League\Uri\Resolver
     *
     * @dataProvider resolveProvider
     *
     * @param string $uri
     * @param string $relative
     * @param string $expected
     */
    public function testResolve(string $uri, string $relative, string $expected)
    {
        $uri      = Http::createFromString($uri);
        $relative = Http::createFromString($relative);
        $this->assertSame($expected, (string) Uri\resolve($relative, $uri));
    }

    public function resolveProvider()
    {
        return [
            'base uri'                => [self::BASE_URI, '',              self::BASE_URI],
            'scheme'                  => [self::BASE_URI, 'http://d/e/f',  'http://d/e/f'],
            'path 1'                  => [self::BASE_URI, 'g',             'http://a/b/c/g'],
            'path 2'                  => [self::BASE_URI, './g',           'http://a/b/c/g'],
            'path 3'                  => [self::BASE_URI, 'g/',            'http://a/b/c/g/'],
            'path 4'                  => [self::BASE_URI, '/g',            'http://a/g'],
            'authority'               => [self::BASE_URI, '//g',           'http://g'],
            'query'                   => [self::BASE_URI, '?y',            'http://a/b/c/d;p?y'],
            'path + query'            => [self::BASE_URI, 'g?y',           'http://a/b/c/g?y'],
            'fragment'                => [self::BASE_URI, '#s',            'http://a/b/c/d;p?q#s'],
            'path + fragment'         => [self::BASE_URI, 'g#s',           'http://a/b/c/g#s'],
            'path + query + fragment' => [self::BASE_URI, 'g?y#s',         'http://a/b/c/g?y#s'],
            'single dot 1'            => [self::BASE_URI, '.',             'http://a/b/c/'],
            'single dot 2'            => [self::BASE_URI, './',            'http://a/b/c/'],
            'single dot 3'            => [self::BASE_URI, './g/.',         'http://a/b/c/g/'],
            'single dot 4'            => [self::BASE_URI, 'g/./h',         'http://a/b/c/g/h'],
            'double dot 1'            => [self::BASE_URI, '..',            'http://a/b/'],
            'double dot 2'            => [self::BASE_URI, '../',           'http://a/b/'],
            'double dot 3'            => [self::BASE_URI, '../g',          'http://a/b/g'],
            'double dot 4'            => [self::BASE_URI, '../..',         'http://a/'],
            'double dot 5'            => [self::BASE_URI, '../../',        'http://a/'],
            'double dot 6'            => [self::BASE_URI, '../../g',       'http://a/g'],
            'double dot 7'            => [self::BASE_URI, '../../../g',    'http://a/g'],
            'double dot 8'            => [self::BASE_URI, '../../../../g', 'http://a/g'],
            'double dot 9'            => [self::BASE_URI, 'g/../h' ,       'http://a/b/c/h'],
            'mulitple slashes'        => [self::BASE_URI, 'foo////g',      'http://a/b/c/foo////g'],
            'complex path 1'          => [self::BASE_URI, ';x',            'http://a/b/c/;x'],
            'complex path 2'          => [self::BASE_URI, 'g;x',           'http://a/b/c/g;x'],
            'complex path 3'          => [self::BASE_URI, 'g;x?y#s',       'http://a/b/c/g;x?y#s'],
            'complex path 4'          => [self::BASE_URI, 'g;x=1/./y',     'http://a/b/c/g;x=1/y'],
            'complex path 5'          => [self::BASE_URI, 'g;x=1/../y',    'http://a/b/c/y'],
            'dot segments presence 1' => [self::BASE_URI, '/./g',          'http://a/g'],
            'dot segments presence 2' => [self::BASE_URI, '/../g',         'http://a/g'],
            'dot segments presence 3' => [self::BASE_URI, 'g.',            'http://a/b/c/g.'],
            'dot segments presence 4' => [self::BASE_URI, '.g',            'http://a/b/c/.g'],
            'dot segments presence 5' => [self::BASE_URI, 'g..',           'http://a/b/c/g..'],
            'dot segments presence 6' => [self::BASE_URI, '..g',           'http://a/b/c/..g'],
            'origin uri without path' => ['http://h:b@a', 'b/../y',        'http://h:b@a/y'],
            '2 relative paths 1'      => ['a/b',          '../..',         '/'],
            '2 relative paths 2'      => ['a/b',          './.',           'a/'],
            '2 relative paths 3'      => ['a/b',          '../c',          'c'],
            '2 relative paths 4'      => ['a/b',          'c/..',          'a/'],
            '2 relative paths 5'      => ['a/b',          'c/.',           'a/c/'],
        ];
    }

    /**
     * @covers \League\Uri\resolve
     * @covers \League\Uri\Resolver
     */
    public function testResolveUri()
    {
        $http = Http::createFromString('http://example.com/path/to/file');
        $dataUri = Data::createFromString('data:text/plain;charset=us-ascii,Bonjour%20le%20monde!');
        $this->assertSame($dataUri, Uri\resolve($dataUri, $http));
    }

    /**
     * @covers \League\Uri\resolve
     * @covers \League\Uri\Resolver
     */
    public function testResolveLetThrowResolvedUriException()
    {
        $this->expectException(InvalidArgumentException::class);
        $http = Http::createFromString('http://example.com/path/to/file');
        $ftp = Ftp::createFromString('ftp//a/b/c/d;p');
        Uri\resolve($ftp, $http);
    }

    /**
     * @covers \League\Uri\resolve
     * @covers \League\Uri\Resolver
     */
    public function testResolveThrowExceptionOnConstructor()
    {
        $this->expectException(TypeError::class);
        Uri\resolve('ftp//a/b/c/d;p', 'toto');
    }

    /**
     * @covers \League\Uri\relativize
     * @covers \League\Uri\Relativizer
     *
     * @dataProvider relativizeProvider
     *
     * @param string $uri
     * @param string $resolved
     * @param string $expected
     */
    public function testRelativize(string $uri, string $resolved, string $expected)
    {
        $uri      = Http::createFromString($uri);
        $resolved = Http::createFromString($resolved);
        $this->assertSame($expected, (string) Uri\relativize($resolved, $uri));
    }

    public function relativizeProvider()
    {
        return [
            'different scheme'        => [self::BASE_URI,       'https://a/b/c/d;p?q',   'https://a/b/c/d;p?q'],
            'different authority'     => [self::BASE_URI,       'https://g/b/c/d;p?q',   'https://g/b/c/d;p?q'],
            'empty uri'               => [self::BASE_URI,       '',                      ''],
            'same uri'                => [self::BASE_URI,       self::BASE_URI,          ''],
            'same path'               => [self::BASE_URI,       'http://a/b/c/d;p',      'd;p'],
            'parent path 1'           => [self::BASE_URI,       'http://a/b/c/',         './'],
            'parent path 2'           => [self::BASE_URI,       'http://a/b/',           '../'],
            'parent path 3'           => [self::BASE_URI,       'http://a/',             '../../'],
            'parent path 4'           => [self::BASE_URI,       'http://a',              '../../'],
            'sibling path 1'          => [self::BASE_URI,       'http://a/b/c/g',        'g'],
            'sibling path 2'          => [self::BASE_URI,       'http://a/b/c/g/h',      'g/h'],
            'sibling path 3'          => [self::BASE_URI,       'http://a/b/g',          '../g'],
            'sibling path 4'          => [self::BASE_URI,       'http://a/g',            '../../g'],
            'query'                   => [self::BASE_URI,       'http://a/b/c/d;p?y',    '?y'],
            'fragment'                => [self::BASE_URI,       'http://a/b/c/d;p?q#s',  '#s'],
            'path + query'            => [self::BASE_URI,       'http://a/b/c/g?y',      'g?y'],
            'path + fragment'         => [self::BASE_URI,       'http://a/b/c/g#s',      'g#s'],
            'path + query + fragment' => [self::BASE_URI,       'http://a/b/c/g?y#s',    'g?y#s'],
            'empty segments'          => [self::BASE_URI,       'http://a/b/c/foo////g', 'foo////g'],
            'empty segments 1'        => [self::BASE_URI,       'http://a/b////c/foo/g', '..////c/foo/g'],
            'relative single dot 1'   => [self::BASE_URI,       '.',                     '.'],
            'relative single dot 2'   => [self::BASE_URI,       './',                    './'],
            'relative double dot 1'   => [self::BASE_URI,       '..',                    '..'],
            'relative double dot 2'   => [self::BASE_URI,       '../',                   '../'],
            'path with colon 1'       => ['http://a/',          'http://a/d:p',          './d:p'],
            'path with colon 2'       => [self::BASE_URI,       'http://a/b/c/g/d:p',    'g/d:p'],
            'scheme + auth 1'         => ['http://a',           'http://a?q#s',          '?q#s'],
            'scheme + auth 2'         => ['http://a/',          'http://a?q#s',          '/?q#s'],
            '2 relative paths 1'      => ['a/b',                '../..',                 '../..'],
            '2 relative paths 2'      => ['a/b',                './.',                   './.'],
            '2 relative paths 3'      => ['a/b',                '../c',                  '../c'],
            '2 relative paths 4'      => ['a/b',                'c/..',                  'c/..'],
            '2 relative paths 5'      => ['a/b',                'c/.',                   'c/.'],
            'baseUri with query'      => ['/a/b/?q',            '/a/b/#h',               './#h'],
            'targetUri with fragment' => ['/',                  '/#h',                   '#h'],
            'same document'           => ['/',                  '/',                     ''],
            'same URI normalized'     => ['http://a',           'http://a/',             ''],
        ];
    }

    /**
     * @covers \League\Uri\relativize
     * @covers \League\Uri\Relativizer
     */
    public function testRelativizerThrowExceptionOnConstructor()
    {
        $this->expectException(TypeError::class);
        Uri\relativize('ftp//a/b/c/d;p', 'toto');
    }

    /**
     * @covers \League\Uri\relativize
     * @covers \League\Uri\Relativizer
     * @covers \League\Uri\resolve
     * @covers \League\Uri\Resolver
     *
     * @dataProvider relativizeAndResolveProvider
     *
     * @param string $baseUri
     * @param string $uri
     * @param string $expectedRelativize
     * @param string $expectedResolved
     */
    public function testRelativizeAndResolve(
        string $baseUri,
        string $uri,
        string $expectedRelativize,
        string $expectedResolved
    ) {
        $baseUri = Http::createFromString($baseUri);
        $uri = Http::createFromString($uri);

        $relativeUri = Uri\relativize($uri, $baseUri);
        $resolvedUri = Uri\resolve($relativeUri, $baseUri);

        $this->assertSame($expectedRelativize, (string) $relativeUri);
        $this->assertSame($expectedResolved, (string) $resolvedUri);
    }

    public function relativizeAndResolveProvider()
    {
        return [
            'empty path'            => [self::BASE_URI, 'http://a/', '../../',   'http://a/'],
            'absolute empty path'   => [self::BASE_URI, 'http://a',  '../../',   'http://a/'],
            'relative single dot 1' => [self::BASE_URI, '.',         '.',        'http://a/b/c/'],
            'relative single dot 2' => [self::BASE_URI, './',        './',       'http://a/b/c/'],
            'relative double dot 1' => [self::BASE_URI, '..',        '..',       'http://a/b/'],
            'relative double dot 2' => [self::BASE_URI, '../',       '../',      'http://a/b/'],
            '2 relative paths 1'    => ['a/b',          '../..',     '../..',    '/'],
            '2 relative paths 2'    => ['a/b',          './.',       './.',      'a/'],
            '2 relative paths 3'    => ['a/b',          '../c',      '../c',     'c'],
            '2 relative paths 4'    => ['a/b',          'c/..',      'c/..',     'a/'],
            '2 relative paths 5'    => ['a/b',          'c/.',       'c/.',      'a/c/'],
            'path with colon'       => ['http://a/',    'http://a/d:p', './d:p', 'http://a/d:p'],
        ];
    }

    /**
     * @covers \League\Uri\normalize
     *
     * @dataProvider sameValueAsProvider
     *
     * @param LeagueUriInterface $uri1
     * @param LeagueUriInterface $uri2
     * @param bool               $expected
     */
    public function testSameValueAs($uri1, $uri2, bool $expected)
    {
        $this->assertSame($expected, (string) Uri\normalize($uri1) == (string) Uri\normalize($uri2));
    }

    public function sameValueAsProvider()
    {
        return [
            '2 disctincts URIs' => [
                Http::createFromString('http://example.com'),
                Ftp::createFromString('ftp://example.com'),
                false,
            ],
            '2 identical URIs' => [
                Http::createFromString('http://example.com'),
                Http::createFromString('http://example.com'),
                true,
            ],
            '2 identical URIs after normalization' => [
                Http::createFromString('HtTp://مثال.إختبار:80/%7efoo/%7efoo/'),
                Http::createFromString('http://xn--mgbh0fb.xn--kgbechtv/%7Efoo/~foo/'),
                true,
            ],
            '2 identical URIs after removing dot segment' => [
                Http::createFromString('http://example.org/~foo/'),
                Http::createFromString('http://example.ORG/bar/./../~foo/'),
                true,
            ],
            '2 distincts relative URIs' => [
                Http::createFromString('~foo/'),
                Http::createFromString('../~foo/'),
                false,
            ],
            '2 identical relative URIs' => [
                Http::createFromString('../%7efoo/'),
                Http::createFromString('../~foo/'),
                true,
            ],
        ];
    }

    /**
     * @covers \League\Uri\normalize
     */
    public function testNormalizeDoesNotAlterPathEncoding()
    {
        $rawUrl = 'HtTp://vonNN.com/ipsam-nulla-adipisci-laboriosam-dignissimos-accusamus-eum-voluptatem';
        $this->assertSame(
            'http://vonnn.com/ipsam-nulla-adipisci-laboriosam-dignissimos-accusamus-eum-voluptatem',
            (string) Uri\normalize(Http::createFromString($rawUrl))
        );
    }
}

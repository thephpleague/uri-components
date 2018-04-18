<?php

namespace LeagueTest\Uri;

use InvalidArgumentException;
use League\Uri;
use League\Uri\Components\Host;
use League\Uri\Components\Query;
use League\Uri\Components\Scheme;
use League\Uri\Formatter;
use League\Uri\Schemes\Http;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;
use TypeError;
use Zend\Diactoros\Uri as ZendUri;

/**
 * @group formatter
 * @coversDefaultClass League\Uri\Formatter
 */
class FormatterTest extends TestCase
{
    /**
     * @var UriInterface
     */
    private $uri;

    protected function setUp()
    {
        $this->uri = new ZendUri(
            'http://login:pass@gwóźdź.pl:443/test/query.php?kingkong=toto&foo=bar+baz#doc3'
        );
    }

    /**
     * @covers ::setEncoding
     * @covers \League\Uri\uri_to_ascii
     */
    public function testFormatHostAscii()
    {
        $this->assertSame('xn--gwd-hna98db.pl', Uri\uri_to_ascii(new Host('gwóźdź.pl')));
    }

    /**
     * @covers ::setEncoding
     */
    public function testInvalidEncoding()
    {
        $this->expectException(InvalidArgumentException::class);
        (new Formatter())->setEncoding(24);
    }

    /**
     * @covers ::setQuerySeparator
     * @covers \League\Uri\uri_to_ascii
     */
    public function testFormatWithSimpleString()
    {
        $uri = 'https://login:pass@gwóźdź.pl:443/test/query.php?kingkong=toto&foo=bar+baz#doc3';
        $expected = 'https://login:pass@xn--gwd-hna98db.pl/test/query.php?kingkong=toto&amp;foo=bar+baz#doc3';

        $this->assertSame($expected, Uri\uri_to_ascii(new ZendUri($uri), '&amp;'));
    }

    /**
     * @covers ::format
     * @covers ::formatUri
     * @covers \League\Uri\uri_to_ascii
     */
    public function testFormatWithZeroes()
    {
        $expected = 'https://example.com/image.jpeg?0#0';
        $uri = new ZendUri('https://example.com/image.jpeg?0#0');
        $this->assertSame($expected, Uri\uri_to_ascii($uri));
    }

    /**
     * @covers ::format
     * @covers ::formatUri
     * @covers \League\Uri\uri_to_ascii
     */
    public function testFormatComponent()
    {
        $scheme = new Scheme('ftp');
        $this->assertSame((string) $scheme, Uri\uri_to_ascii($scheme));
    }

    /**
     * @covers ::format
     * @covers ::formatUri
     * @covers \League\Uri\uri_to_unicode
     */
    public function testFormatHostUnicode()
    {
        $this->assertSame('gwóźdź.pl', Uri\uri_to_unicode(new Host('gwóźdź.pl')));
    }

    /**
     * @covers ::format
     * @covers ::formatUri
     * @covers \League\Uri\uri_to_ascii
     */
    public function testFormatQueryRFC3986()
    {
        $this->assertSame('kingkong=toto&foo=bar+baz', Uri\uri_to_ascii(new Query('kingkong=toto&foo=bar+baz')));
    }

    /**
     * @covers ::format
     * @covers ::formatUri
     * @covers \League\Uri\uri_to_ascii
     */
    public function testFormatQueryWithSeparator()
    {
        $this->assertSame(
            'kingkong=toto&amp;foo=bar+baz',
            Uri\uri_to_ascii(new Query('kingkong=toto&foo=bar+baz'), '&amp;')
        );
    }

    /**
     * @covers ::format
     * @covers ::formatUri
     * @covers \League\Uri\uri_to_ascii
     */
    public function testFormat()
    {
        $expected = 'http://login:pass@xn--gwd-hna98db.pl:443/test/query.php?kingkong=toto&amp;foo=bar+baz#doc3';
        $this->assertSame($expected, Uri\uri_to_ascii($this->uri, '&amp;'));
    }

    /**
     * @covers ::format
     * @covers ::formatUri
     * @covers \League\Uri\uri_to_ascii
     */
    public function testFormatWithoutAuthority()
    {
        $expected = '/test/query.php?kingkong=toto&amp;foo=bar+baz#doc3';
        $uri = new ZendUri('/test/query.php?kingkong=toto&foo=bar+baz#doc3');

        $this->assertSame($expected, Uri\uri_to_ascii($uri, '&amp;'));
    }

    /**
     * @covers ::format
     * @covers ::formatUri
     */
    public function testFormatterFailed()
    {
        $this->expectException(TypeError::class);
        (new Formatter())->format('http://www.example.com');
    }

    /**
     * @covers ::preserveQuery
     */
    public function testFormatterPreservedQuery()
    {
        $formatter = new Formatter();
        $formatter->preserveQuery(true);

        $expected = 'http://example.com';
        $uri = new ZendUri($expected);

        $this->assertSame($expected, (string) $uri);
        $this->assertSame('http://example.com?', $formatter->format($uri));
    }

    /**
     * @covers ::preserveFragment
     */
    public function testFormatterPreservedFragment()
    {
        $formatter = new Formatter();
        $formatter->preserveFragment(true);

        $expected = 'http://example.com';
        $uri = new ZendUri($expected);

        $this->assertSame($expected, (string) $uri);
        $this->assertSame('http://example.com#', $formatter->format($uri));
    }

    /**
     * @covers ::format
     * @covers ::formatUri
     * @covers \League\Uri\uri_to_unicode
     */
    public function testUriStaysRFC3986Compliant()
    {
        $expected = 'http://bébé.com/foo/bar';
        $uri = (new ZendUri('http://bébé.com'))->withPath('foo/bar');

        $this->assertSame($expected, Uri\uri_to_unicode($uri));
    }

    /**
     * @dataProvider dataUriStringProvider
     *
     * @covers \League\Uri\uri_to_ascii
     * @covers \League\Uri\uri_to_unicode
     *
     * @param string $str
     * @param string $rfc3986
     * @param string $rfc3987
     * @param string $host3986
     * @param string $host3987
     */
    public function testUriConversion($str, $rfc3986, $rfc3987, $host3986, $host3987)
    {
        $host = new Host($host3986);
        $uri = Uri\Schemes\Http::createFromString($str);
        $this->assertSame($rfc3986, Uri\uri_to_ascii($uri));
        $this->assertSame($rfc3987, Uri\uri_to_unicode($uri));
        $this->assertSame($host3986, Uri\uri_to_ascii($host));
        $this->assertSame($host3987, Uri\uri_to_unicode($host));
    }

    public function dataUriStringProvider()
    {
        return [
            'mixed content' => [
                'http://xn--bb-bjab.be/toto/тестовый_путь/',
                'http://xn--bb-bjab.be/toto/%D1%82%D0%B5%D1%81%D1%82%D0%BE%D0%B2%D1%8B%D0%B9_%D0%BF%D1%83%D1%82%D1%8C/',
                'http://bébé.be/toto/тестовый_путь/',
                'xn--bb-bjab.be',
                'bébé.be',
            ],
            'host punycoded' => [
                'https://ουτοπία.δπθ.gr',
                'https://xn--kxae4bafwg.xn--pxaix.gr',
                'https://ουτοπία.δπθ.gr',
                'xn--kxae4bafwg.xn--pxaix.gr',
                'ουτοπία.δπθ.gr',
            ],
            'preserve both delimiters' => [
                'https://example.com/?#',
                'https://example.com/?#',
                'https://example.com/?#',
                'example.com',
                'example.com',
            ],
            'preserve fragment delimiters' => [
                'https://example.com/#',
                'https://example.com/#',
                'https://example.com/#',
                'example.com',
                'example.com',
            ],
            'preserve query delimiters' => [
                'https://example.com/?',
                'https://example.com/?',
                'https://example.com/?',
                'example.com',
                'example.com',
            ],
        ];
    }

    /**
     * @dataProvider functionProvider
     *
     * @covers \League\Uri\is_uri
     * @covers \League\Uri\uri_to_ascii
     * @covers \League\Uri\uri_to_unicode
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
            ['\League\Uri\uri_to_ascii'],
            ['\League\Uri\uri_to_unicode'],
        ];
    }
}

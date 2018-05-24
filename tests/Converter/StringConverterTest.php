<?php

/**
 * League.Uri (http://uri.thephpleague.com).
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.0
 * @link       https://github.com/thephpleague/uri-schemes
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Uri\Converter;

use League\Uri;
use League\Uri\Component\Host;
use League\Uri\Component\Query;
use League\Uri\Component\Scheme;
use League\Uri\Converter\StringConverter;
use League\Uri\Exception\UnknownEncoding;
use League\Uri\Http;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;
use TypeError;
use Zend\Diactoros\Uri as ZendUri;

/**
 * @group converter
 * @coversDefaultClass League\Uri\Converter\StringConverter
 */
class StringConverterTest extends TestCase
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
     * @covers \League\Uri\to_ascii
     * @covers \League\Uri\to_unicode
     */
    public function testFormatHostAscii()
    {
        $host3987 = 'gwóźdź.pl';
        $host3986 = 'xn--gwd-hna98db.pl';

        $this->assertSame($host3986, Uri\to_ascii(new Host($host3986)));
        $this->assertSame($host3987, Uri\to_unicode(new Host($host3986)));
    }

    /**
     * @covers ::convert
     */
    public function testInvalidEncoding()
    {
        $this->expectException(UnknownEncoding::class);
        (new StringConverter())->convert(new Host(), 24);
    }

    /**
     * @covers ::convert
     * @covers ::convertURI
     * @covers \League\Uri\to_ascii
     */
    public function testFormatWithSimpleString()
    {
        $uri = 'https://login:pass@gwóźdź.pl:443/test/query.php?kingkong=toto&foo=bar+baz#doc3';
        $expected = 'https://login:pass@xn--gwd-hna98db.pl/test/query.php?kingkong=toto&amp;foo=bar+baz#doc3';

        $this->assertSame($expected, Uri\to_ascii(new ZendUri($uri), '&amp;'));
    }

    /**
     * @covers ::convert
     * @covers ::convertURI
     * @covers \League\Uri\to_ascii
     */
    public function testFormatWithZeroes()
    {
        $expected = 'https://example.com/image.jpeg?0#0';
        $uri = Http::createFromString('https://example.com/image.jpeg?0#0');
        $this->assertSame($expected, Uri\to_ascii($uri));
    }

    /**
     * @covers ::convert
     * @covers ::convertURI
     * @covers \League\Uri\to_ascii
     */
    public function testFormatComponent()
    {
        $scheme = new Scheme('ftp');
        $this->assertSame((string) $scheme, Uri\to_ascii($scheme));
    }

    /**
     * @covers ::convert
     * @covers ::convertURI
     * @covers \League\Uri\to_unicode
     */
    public function testFormatHostUnicode()
    {
        $this->assertSame('gwóźdź.pl', Uri\to_unicode(new Host('gwóźdź.pl')));
    }

    /**
     * @covers ::convert
     * @covers ::convertURI
     * @covers \League\Uri\to_ascii
     */
    public function testFormatQueryRFC3986()
    {
        $this->assertSame('kingkong=toto&foo=bar+baz', Uri\to_ascii(new Query('kingkong=toto&foo=bar+baz')));
    }

    /**
     * @covers ::convert
     * @covers ::convertURI
     * @covers \League\Uri\to_ascii
     */
    public function testFormatQueryWithSeparator()
    {
        $this->assertSame(
            'kingkong=toto&amp;foo=bar+baz',
            Uri\to_ascii(new Query('kingkong=toto&foo=bar+baz'), '&amp;')
        );
    }

    /**
     * @covers ::convert
     * @covers ::convertURI
     * @covers \League\Uri\to_ascii
     */
    public function testFormat()
    {
        $expected = 'http://login:pass@xn--gwd-hna98db.pl:443/test/query.php?kingkong=toto&amp;foo=bar+baz#doc3';
        $this->assertSame($expected, Uri\to_ascii($this->uri, '&amp;'));
    }

    /**
     * @covers ::convert
     * @covers ::convertURI
     * @covers \League\Uri\to_ascii
     */
    public function testFormatWithoutAuthority()
    {
        $expected = '/test/query.php?kingkong=toto&amp;foo=bar+baz#doc3';
        $uri = new ZendUri('/test/query.php?kingkong=toto&foo=bar+baz#doc3');

        $this->assertSame($expected, Uri\to_ascii($uri, '&amp;'));
    }

    /**
     * @covers ::convert
     * @covers ::convertURI
     */
    public function testStringConverterFailed()
    {
        $this->expectException(TypeError::class);
        (new StringConverter())->convert('http://www.example.com');
    }

    /**
     * @covers ::convert
     * @covers ::convertURI
     * @covers \League\Uri\to_unicode
     */
    public function testUriStaysRFC3986Compliant()
    {
        $expected = 'http://bébé.com/foo/bar';
        $uri = (new ZendUri('http://bébé.com'))->withPath('foo/bar');

        $this->assertSame($expected, Uri\to_unicode($uri));
    }

    /**
     * @dataProvider dataUriStringProvider
     *
     * @covers ::convert
     * @covers ::convertURI
     * @covers \League\Uri\to_ascii
     * @covers \League\Uri\to_unicode
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
        $uri = Uri\Http::createFromString($str);
        $this->assertSame($rfc3986, Uri\to_ascii($uri));
        $this->assertSame($rfc3987, Uri\to_unicode($uri));
        $this->assertSame($host3986, Uri\to_ascii($host));
        $this->assertSame($host3987, Uri\to_unicode($host));
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
     * @covers ::convert
     * @covers \League\Uri\to_ascii
     * @covers \League\Uri\to_unicode
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
            ['\League\Uri\to_ascii'],
            ['\League\Uri\to_unicode'],
        ];
    }
}

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

namespace LeagueTest\Uri;

use League\Uri\Component\Host;
use League\Uri\Http;
use PHPUnit\Framework\TestCase;
use TypeError;
use Zend\Diactoros\Uri as ZendUri;
use function League\Uri\add_root_label;
use function League\Uri\append_host;
use function League\Uri\host_to_ascii;
use function League\Uri\host_to_unicode;
use function League\Uri\prepend_host;
use function League\Uri\remove_labels;
use function League\Uri\remove_root_label;
use function League\Uri\remove_zone_id;
use function League\Uri\replace_label;

/**
 * @group host
 */
class HostModifierTest extends TestCase
{
    /**
     * @var Http
     */
    private $uri;

    protected function setUp()
    {
        $this->uri = Http::createFromString(
            'http://www.example.com/path/to/the/sky.php?kingkong=toto&foo=bar+baz#doc3'
        );
    }

    /**
     * @dataProvider validHostProvider
     *
     * @covers \League\Uri\prepend_host
     *
     * @param string $label
     * @param int    $key
     * @param string $prepend
     * @param string $append
     * @param string $replace
     */
    public function testPrependLabelProcess(string $label, int $key, string $prepend, string $append, string $replace)
    {
        self::assertSame($prepend, prepend_host($this->uri, $label)->getHost());
    }

    /**
     * @dataProvider validHostProvider
     *
     * @covers \League\Uri\append_host
     *
     * @param string $label
     * @param int    $key
     * @param string $prepend
     * @param string $append
     * @param string $replace
     */
    public function testAppendLabelProcess(string $label, int $key, string $prepend, string $append, string $replace)
    {
        self::assertSame($append, append_host($this->uri, $label)->getHost());
    }

    /**
     * @dataProvider validHostProvider
     *
     * @covers \League\Uri\replace_label
     *
     * @param string $label
     * @param int    $key
     * @param string $prepend
     * @param string $append
     * @param string $replace
     */
    public function testReplaceLabelProcess(string $label, int $key, string $prepend, string $append, string $replace)
    {
        self::assertSame($replace, replace_label($this->uri, $key, $label)->getHost());
    }

    public function validHostProvider()
    {
        return [
            ['toto', 2, 'toto.www.example.com', 'www.example.com.toto', 'toto.example.com'],
            ['123', 1, '123.www.example.com', 'www.example.com.123', 'www.123.com'],
        ];
    }

    /**
     * @covers \League\Uri\host_to_ascii
     */
    public function testHostToAsciiProcess()
    {
        $uri = Http::createFromString('http://مثال.إختبار/where/to/go');
        self::assertSame(
            'http://xn--mgbh0fb.xn--kgbechtv/where/to/go',
            (string) host_to_ascii($uri)
        );
    }

    /**
     * @covers \League\Uri\host_to_unicode
     */
    public function testHostToUnicodeProcess()
    {
        $uri = new ZendUri('http://xn--mgbh0fb.xn--kgbechtv/where/to/go');
        $expected = 'http://مثال.إختبار/where/to/go';
        self::assertSame($expected, (string) host_to_unicode($uri));
    }

    /**
     * @covers \League\Uri\remove_zone_id
     */
    public function testWithoutZoneIdentifierProcess()
    {
        $uri = Http::createFromString('http://[fe80::1234%25eth0-1]/path/to/the/sky.php');
        self::assertSame(
            'http://[fe80::1234]/path/to/the/sky.php',
            (string) remove_zone_id($uri)
        );
    }

    /**
     * @covers \League\Uri\remove_labels
     *
     * @dataProvider validwithoutLabelProvider
     *
     * @param array  $keys
     * @param string $expected
     */
    public function testwithoutLabelProcess(array $keys, string $expected)
    {
        self::assertSame($expected, remove_labels($this->uri, $keys)->getHost());
    }

    public function validwithoutLabelProvider()
    {
        return [
            [[1], 'www.com'],
        ];
    }

    /**
     * @covers \League\Uri\remove_labels
     */
    public function testRemoveLabels()
    {
        self::assertSame('example.com', remove_labels($this->uri, [2])->getHost());
    }

    /**
     * @covers \League\Uri\remove_labels
     *
     * @dataProvider invalidRemoveLabelsParameters
     *
     * @param array $params
     */
    public function testRemoveLabelsFailedConstructor(array $params)
    {
        self::expectException(TypeError::class);
        remove_labels($this->uri, $params);
    }

    public function invalidRemoveLabelsParameters()
    {
        return [
            'array contains float' => [[1, 2, '3.1']],
        ];
    }

    /**
     * @covers \League\Uri\add_root_label
     */
    public function testAddRootLabel()
    {
        self::assertSame('www.example.com.', add_root_label($this->uri)->getHost());
    }

    /**
     * @covers \League\Uri\remove_root_label
     */
    public function testRemoveRootLabel()
    {
        self::assertSame('www.example.com', remove_root_label($this->uri)->getHost());
    }
}

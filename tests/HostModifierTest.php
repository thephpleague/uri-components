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

use League\Uri;
use League\Uri\Components\Host;
use League\Uri\Schemes\Http;
use PHPUnit\Framework\TestCase;
use TypeError;
use Zend\Diactoros\Uri as ZendUri;

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
        $this->assertSame($prepend, Uri\prepend_host($this->uri, $label)->getHost());
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
        $this->assertSame($append, Uri\append_host($this->uri, $label)->getHost());
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
        $this->assertSame($replace, Uri\replace_label($this->uri, $key, $label)->getHost());
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
        $this->assertSame(
            'http://xn--mgbh0fb.xn--kgbechtv/where/to/go',
            (string) Uri\host_to_ascii($uri)
        );
    }

    /**
     * @covers \League\Uri\host_to_unicode
     */
    public function testHostToUnicodeProcess()
    {
        $uri = new ZendUri('http://xn--mgbh0fb.xn--kgbechtv/where/to/go');
        $expected = 'http://مثال.إختبار/where/to/go';
        $this->assertSame($expected, (string) Uri\host_to_unicode($uri));
    }

    /**
     * @covers \League\Uri\remove_zone_id
     */
    public function testWithoutZoneIdentifierProcess()
    {
        $uri = Http::createFromString('http://[fe80::1234%25eth0-1]/path/to/the/sky.php');
        $this->assertSame(
            'http://[fe80::1234]/path/to/the/sky.php',
            (string) Uri\remove_zone_id($uri)
        );
    }

    /**
     * @covers \League\Uri\remove_labels
     *
     * @dataProvider validWithoutLabelsProvider
     *
     * @param array  $keys
     * @param string $expected
     */
    public function testWithoutLabelsProcess(array $keys, string $expected)
    {
        $this->assertSame($expected, Uri\remove_labels($this->uri, $keys)->getHost());
    }

    public function validWithoutLabelsProvider()
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
        $this->assertSame('example.com', Uri\remove_labels($this->uri, [2])->getHost());
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
        $this->expectException(TypeError::class);
        Uri\remove_labels($this->uri, $params);
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
        $this->assertSame('www.example.com.', Uri\add_root_label($this->uri)->getHost());
    }

    /**
     * @covers \League\Uri\remove_root_label
     */
    public function testRemoveRootLabel()
    {
        $this->assertSame('www.example.com', Uri\remove_root_label($this->uri)->getHost());
    }
}

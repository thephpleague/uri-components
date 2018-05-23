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

namespace LeagueTest\Uri\Components;

use League\Uri\Components\Fragment;
use League\Uri\Exception\InvalidComponentArgument;
use League\Uri\Exception\UnknownEncoding;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * @group fragment
 * @coversDefaultClass \League\Uri\Components\Fragment
 */
class FragmentTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::validateComponent
     * @covers ::filterComponent
     * @covers ::getUriComponent
     * @dataProvider getUriComponentProvider
     * @param string $str
     * @param string $encoded
     */
    public function testGetUriComponent($str, $encoded)
    {
        $this->assertSame($encoded, (new Fragment($str))->getUriComponent());
    }

    public function getUriComponentProvider()
    {
        $unreserved = 'a-zA-Z0-9.-_~!$&\'()*+,;=:@';

        return [
            'null' => [null, ''],
            'empty' => ['', '#'],
            'evaluate empty' => ['0', '#0'],
            'hash' => ['#', '#%23'],
            'toofan' => ['toofan', '#toofan'],
            'notencoded' => ["azAZ0-9/?-._~!$&'()*+,;=:@", '#azAZ0-9/?-._~!$&\'()*+,;=:@'],
            'encoded' => ['%^[]{}"<>\\', '#%25%5E%5B%5D%7B%7D%22%3C%3E%5C'],
            'Percent encode spaces' => ['frag ment', '#frag%20ment'],
            'Percent encode multibyte' => ['€', '#%E2%82%AC'],
            "Don't encode something that's already encoded" => ['frag%20ment', '#frag%20ment'],
            'Percent encode invalid percent encodings' => ['frag%2-ment', '#frag%252-ment'],
            "Don't encode path segments" => ['frag/ment', '#frag/ment'],
            "Don't encode unreserved chars or sub-delimiters" => [$unreserved, '#'.$unreserved],
            'Encoded unreserved chars are not decoded' => ['fr%61gment', '#fr%61gment'],
        ];
    }

    /**
     * @dataProvider geValueProvider
     * @covers ::__construct
     * @covers ::validateComponent
     * @covers ::filterComponent
     * @covers ::getContent
     * @covers ::encodeComponent
     * @covers ::encodeMatches
     * @covers ::decodeMatches
     * @param mixed       $str
     * @param string|null $expected
     * @param int         $enc_type
     */
    public function testGetValue($str, $expected, $enc_type)
    {
        $this->assertSame($expected, (new Fragment($str))->getContent($enc_type));
    }

    public function geValueProvider()
    {
        return [
            [new Fragment(), null, Fragment::RFC3987_ENCODING],
            [null, null, Fragment::RFC3987_ENCODING],
            ['', '', Fragment::RFC3987_ENCODING],
            ['0', '0', Fragment::RFC3987_ENCODING],
            ['azAZ0-9/?-._~!$&\'()*+,;=:@%^/[]{}\"<>\\', 'azAZ0-9/?-._~!$&\'()*+,;=:@%^/[]{}\"<>\\', Fragment::RFC3987_ENCODING],
            ['€', '€', Fragment::RFC3987_ENCODING],
            ['%E2%82%AC', '€', Fragment::RFC3987_ENCODING],
            ['frag ment', 'frag ment', Fragment::RFC3987_ENCODING],
            ['frag%20ment', 'frag ment', Fragment::RFC3987_ENCODING],
            ['frag%2-ment', 'frag%2-ment', Fragment::RFC3987_ENCODING],
            ['fr%61gment', 'fr%61gment', Fragment::RFC3987_ENCODING],
            ['frag+ment', 'frag%2Bment', Fragment::RFC1738_ENCODING],
        ];
    }

    /**
     * @dataProvider getContentProvider
     * @param string $input
     * @param int    $enc_type
     * @param string $expected
     * @covers ::__construct
     * @covers ::validateComponent
     * @covers ::filterComponent
     * @covers ::getContent
     * @covers ::encodeMatches
     * @covers ::decodeMatches
     */
    public function testGetContent($input, $enc_type, $expected)
    {
        $this->assertSame($expected, (new Fragment($input))->getContent($enc_type));
    }

    public function getContentProvider()
    {
        return [
            ['€', Fragment::RFC3987_ENCODING, '€'],
            ['€', Fragment::RFC3986_ENCODING, '%E2%82%AC'],
            ['%E2%82%AC', Fragment::RFC3987_ENCODING, '€'],
            ['%E2%82%AC', Fragment::RFC3986_ENCODING, '%E2%82%AC'],
            ['action=v%61lue',  Fragment::RFC3986_ENCODING, 'action=v%61lue'],
        ];
    }

    public function testInvalidEncodingTypeThrowException()
    {
        $this->expectException(UnknownEncoding::class);
        (new Fragment('host'))->getContent(-1);
    }

    /**
     * @covers ::filterComponent
     */
    public function testFailedFragmentException()
    {
        $this->expectException(InvalidComponentArgument::class);
        new Fragment("\0");
    }

    public function testFailedFragmentTypeError()
    {
        $this->expectException(TypeError::class);
        new Fragment(date_create());
    }

    /**
     * @covers ::__set_state
     */
    public function testSetState()
    {
        $component = new Fragment('yolo');
        $generateComponent = eval('return '.var_export($component, true).';');
        $this->assertEquals($component, $generateComponent);
    }

    /**
     * @covers ::jsonSerialize
     */
    public function testJsonSerialize()
    {
        $component = new Fragment('yolo');
        $this->assertEquals('"yolo"', json_encode($component));
    }

    /**
     * @covers ::__debugInfo
     */
    public function testDebugInfo()
    {
        $component = new Fragment('yolo');
        $debugInfo = $component->__debugInfo();
        $this->assertArrayHasKey('component', $debugInfo);
        $this->assertSame($component->getContent(), $debugInfo['component']);
    }

    /**
     * @covers ::__toString
     * @covers ::validateComponent
     * @covers ::withContent
     * @covers ::decodeMatches
     */
    public function testPreserverDelimiter()
    {
        $fragment = new Fragment();
        $altFragment = $fragment->withContent(null);
        $this->assertSame($fragment, $altFragment);
        $this->assertNull($altFragment->getContent());
        $this->assertSame('', $altFragment->__toString());
    }

    /**
     * @covers ::withContent
     * @covers ::encodeMatches
     * @covers ::decodeMatches
     */
    public function testWithContent()
    {
        $fragment = new Fragment('coucou');
        $this->assertSame($fragment, $fragment->withContent('coucou'));
        $this->assertNotSame($fragment, $fragment->withContent('Coucou'));
    }
}

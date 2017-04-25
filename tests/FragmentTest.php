<?php

namespace LeagueTest\Uri\Components;

use League\Uri\Components\Exception;
use League\Uri\Components\Fragment;
use PHPUnit\Framework\TestCase;

/**
 * @group fragment
 */
class FragmentTest extends TestCase
{
    /**
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

    public function testIsNull()
    {
        $this->assertTrue((new Fragment(null))->isNull());
        $this->assertFalse((new Fragment(''))->isNull());
    }

    /**
     * @dataProvider geValueProvider
     * @param string|null $str
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
        ];
    }

    public function testInvalidEncodingTypeThrowException()
    {
        $this->expectException(Exception::class);
        (new Fragment('host'))->getContent(-1);
    }

    public function testDebugInfo()
    {
        $this->assertInternalType('array', (new Fragment('yolo'))->__debugInfo());
    }

    public function testPreserverDelimiter()
    {
        $fragment = new Fragment();
        $altFragment = $fragment->withContent(null);
        $this->assertSame($fragment, $altFragment);
        $this->assertNull($altFragment->getContent());
        $this->assertSame('', $altFragment->__toString());
    }
}

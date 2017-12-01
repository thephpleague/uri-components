<?php

namespace LeagueTest\Uri\Components;

use League\Uri\Components\Exception;
use League\Uri\Components\UserInfo;
use PHPUnit\Framework\TestCase;

/**
 * @group userinfo
 */
class UserInfoTest extends TestCase
{
    public function testDebugInfo()
    {
        $component = new UserInfo('yolo', 'oloy');
        $this->assertInternalType('array', $component->__debugInfo());
    }

    /**
     * @dataProvider userInfoProvider
     * @param string      $user
     * @param string      $pass
     * @param string|null $expected_user
     * @param string|null $expected_pass
     * @param string      $expected_str
     * @param string      $uri_component
     * @param string      $iri_str
     * @param string      $rfc1738_str
     */
    public function testConstructor(
        $user,
        $pass,
        $expected_user,
        $expected_pass,
        $expected_str,
        $uri_component,
        $iri_str,
        $rfc1738_str
    ) {
        $userinfo = new UserInfo($user, $pass);
        $this->assertSame($expected_user, $userinfo->getUser());
        $this->assertSame($expected_pass, $userinfo->getPass());
        $this->assertSame($expected_str, (string) $userinfo);
        $this->assertSame($uri_component, $userinfo->getUriComponent());
        $this->assertSame($iri_str, $userinfo->getContent(UserInfo::RFC3987_ENCODING));
        $this->assertSame($rfc1738_str, $userinfo->getContent(UserInfo::RFC1738_ENCODING));
    }

    public function userInfoProvider()
    {
        return [
            [
                'user' => 'login',
                'pass' => 'pass',
                'expected_user' => 'login',
                'expected_pass' => 'pass',
                'expected_str' => 'login:pass',
                'uri_component' => 'login:pass@',
                'iri_str' => 'login:pass',
                'rfc1738_str' => 'login:pass',
            ],
            [
                'user' => 'login',
                'pass' => null,
                'expected_user' => 'login',
                'expected_pass' => null,
                'expected_str' => 'login',
                'uri_component' => 'login@',
                'iri_str' => 'login',
                'rfc1738_str' => 'login',
            ],
            [
                'user' => null,
                'pass' => null,
                'expected_user' => null,
                'expected_pass' => null,
                'expected_str' => '',
                'uri_component' => '',
                'iri_str' => null,
                'rfc1738_str' => null,
            ],
            [
                'user' => '',
                'pass' => null,
                'expected_user' => '',
                'expected_pass' => null,
                'expected_str' => '',
                'uri_component' => '',
                'iri_str' => '',
                'rfc1738_str' => '',
            ],
            [
                'user' => '',
                'pass' => '',
                'expected_user' => '',
                'expected_pass' => null,
                'expected_str' => '',
                'uri_component' => '',
                'iri_str' => '',
                'rfc1738_str' => '',
            ],
            [
                'user' => null,
                'pass' => 'pass',
                'expected_user' => null,
                'expected_pass' => null,
                'expected_str' => '',
                'uri_component' => '',
                'iri_str' => null,
                'rfc1738_str' => null,
            ],
            [
                'user' => 'foò',
                'pass' => 'bar',
                'expected_user' => 'fo%C3%B2',
                'expected_pass' => 'bar',
                'expected_str' => 'fo%C3%B2:bar',
                'uri_component' => 'fo%C3%B2:bar@',
                'iri_str' => 'foò:bar',
                'rfc1738_str' => 'fo%C3%B2:bar',
            ],
            [
                'user' => 'fo+o',
                'pass' => 'ba+r',
                'expected_user' => 'fo+o',
                'expected_pass' => 'ba+r',
                'expected_str' => 'fo+o:ba+r',
                'uri_component' => 'fo+o:ba+r@',
                'iri_str' => 'fo+o:ba+r',
                'rfc1738_str' => 'fo%2Bo:ba%2Br',
            ],

        ];
    }

    public function testIsNull()
    {
        $this->assertTrue((new UserInfo(null))->isNull());
        $this->assertFalse((new UserInfo(''))->isNull());
    }

    public function testIsEmpty()
    {
        $this->assertTrue((new UserInfo(null))->isEmpty());
        $this->assertTrue((new UserInfo(''))->isEmpty());
    }

    /**
     * @dataProvider createFromStringProvider
     * @param string|null $str
     * @param string|null $expected_user
     * @param string|null $expected_pass
     * @param string      $expected_str
     */
    public function testWithContent($str, $expected_user, $expected_pass, $expected_str)
    {
        $conn = (new UserInfo())->withContent($str);
        $this->assertSame($expected_user, $conn->getUser());
        $this->assertSame($expected_pass, $conn->getPass());
        $this->assertSame($expected_str, (string) $conn);
    }

    public function createFromStringProvider()
    {
        return [
            'simple' => ['user:pass', 'user', 'pass', 'user:pass'],
            'empty password' => ['user:', 'user', '', 'user:'],
            'no password' => ['user', 'user', null, 'user'],
            'no login but has password' => [':pass', '', null, ''],
            'empty all' => ['', '', null, ''],
            'null content' => [null, null, null, ''],
            'encoded chars' => ['foo%40bar:bar%40foo', 'foo%40bar', 'bar%40foo', 'foo%40bar:bar%40foo'],
        ];
    }

    public function testWithContentReturnSameInstance()
    {
        $conn = new UserInfo();
        $this->assertEquals($conn, $conn->withContent(':pass'));

        $conn = new UserInfo('user', 'pass');
        $this->assertSame($conn, $conn->withContent('user:pass'));
    }

    public function testSetState()
    {
        $conn = new UserInfo('user', 'pass');
        $generateConn = eval('return '.var_export($conn, true).';');
        $this->assertEquals($conn, $generateConn);
    }

    /**
     * @dataProvider withUserInfoProvider
     * @param string      $user
     * @param string|null $pass
     * @param string      $expected
     */
    public function testWithUserInfo($user, $pass, $expected)
    {
        $this->assertSame($expected, (string) (new UserInfo('user', 'pass'))->withUserInfo($user, $pass));
    }

    public function withUserInfoProvider()
    {
        return [
            'simple' => ['user', 'pass', 'user:pass'],
            'empty password' => ['user', '', 'user:'],
            'no password' => ['user', null, 'user'],
            'no login but has password' => ['', 'pass', ''],
            'empty all' => ['', '', ''],
        ];
    }

    public function testGetUserThrowsInvalidArgumentException()
    {
        $this->expectException(Exception::class);
        (new UserInfo())->getUser(-1);
    }

    public function testGetPassThrowsInvalidArgumentException()
    {
        $this->expectException(Exception::class);
        (new UserInfo())->getPass(-1);
    }

    public function testInvalidEncodingTypeThrowException()
    {
        $this->expectException(Exception::class);
        (new UserInfo('user', 'pass'))->getContent(-1);
    }
}

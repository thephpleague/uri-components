<?php

namespace LeagueTest\Uri\Components;

use League\Uri\Components\Exception;
use League\Uri\Components\UserInfo;

/**
 * @group userinfo
 */
class UserInfoTest extends AbstractTestCase
{
    /**
     * @supportsDebugInfo
     */
    public function testDebugInfo()
    {
        $component = new UserInfo('yolo', 'oloy');
        $this->assertInternalType('array', $component->__debugInfo());
        ob_start();
        var_dump($component);
        $res = ob_get_clean();
        $this->assertContains($component->__toString(), $res);
        $this->assertContains('userInfo', $res);
    }

    /**
     * @dataProvider userInfoProvider
     */
    public function testConstructor(
        $user,
        $pass,
        $expected_user,
        $expected_pass,
        $expected_str,
        $uri_component,
        $iri_str
    ) {
        $userinfo = new UserInfo($user, $pass);
        $this->assertSame($expected_user, $userinfo->getUser());
        $this->assertSame($expected_pass, $userinfo->getPass());
        $this->assertSame($expected_str, (string) $userinfo);
        $this->assertSame($uri_component, $userinfo->getUriComponent());
        $this->assertSame($iri_str, $userinfo->getContent(UserInfo::RFC3987));
    }

    public function userInfoProvider()
    {
        return [
            [
                'login',
                'pass',
                'login',
                'pass',
                'login:pass',
                'login:pass@',
                'login:pass',
            ],
            [
                'login',
                null,
                'login',
                '',
                'login',
                'login@',
                'login',
            ],
            [
                null,
                null,
                '',
                '',
                '',
                '',
                null,
            ],
            [
                '',
                null,
                '',
                '',
                '',
                '',
                '',
            ],
            [
                '',
                '',
                '',
                '',
                '',
                '',
                '',
            ],
            [
                null,
                'pass',
                '',
                '',
                '',
                '',
                null,
            ],
            [
                'foò',
                'bar',
                'fo%C3%B2',
                'bar',
                'fo%C3%B2:bar',
                'fo%C3%B2:bar@',
                'foò:bar',
            ],

        ];
    }


    public function testIsNull()
    {
        $this->assertFalse((new UserInfo(null))->isDefined());
        $this->assertTrue((new UserInfo('toto'))->isDefined());
    }

    /**
     * @dataProvider createFromStringProvider
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
            'no password' => ['user', 'user', '', 'user'],
            'no login but has password' => [':pass', '', '', ''],
            'empty all' => ['', '', '', ''],
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
     */
    public function testWithUserInfo($user, $pass, $expected)
    {
        $conn = (new UserInfo('user', 'pass'))->withUserInfo($user, $pass);
        $this->assertSame($expected, $conn->__toString());
    }

    public function withUserInfoProvider()
    {
        return [
            'simple' => ['user', 'pass', 'user:pass'],
            'empty password' => ['user', '', 'user:'],
            'no password' => ['user', null, 'user'],
            'no login but has password' => ['', 'pass', ''],
            'empty all' => ['', '', '', ''],
        ];
    }

    public function testWithContentThrowsInvalidArgumentException()
    {
        $this->expectException(Exception::class);
        (new UserInfo())->withContent([]);
    }

    public function testGetUserThrowsInvalidArgumentException()
    {
        $this->expectException(Exception::class);
        (new UserInfo())->getUser('toto');
    }

    public function testGetPassThrowsInvalidArgumentException()
    {
        $this->expectException(Exception::class);
        (new UserInfo())->getPass('toto');
    }

    public function testConstructorThrowsInvalidArgumentException1()
    {
        $this->expectException(Exception::class);
        new UserInfo('tot:o');
    }

    public function testConstructorThrowsInvalidArgumentException2()
    {
        $this->expectException(Exception::class);
        new UserInfo('toto', 'p@ass');
    }

    public function testWithUserInfoThrowException()
    {
        $this->expectException(Exception::class);
        (new UserInfo('user', 'pass'))->withUserInfo(null);
    }

    public function testInvalidEncodingTypeThrowException()
    {
        $this->expectException(Exception::class);
        (new UserInfo('user', 'pass'))->getContent('RFC1738');
    }
}

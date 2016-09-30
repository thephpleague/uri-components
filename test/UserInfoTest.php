<?php

namespace LeagueTest\Uri\Components;

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
    public function testConstructor($user, $pass, $expected_user, $expected_pass, $expected_str, $uri_component)
    {
        $userinfo = new UserInfo($user, $pass);
        $this->assertSame($expected_user, $userinfo->getUser());
        $this->assertSame($expected_pass, $userinfo->getPass());
        $this->assertSame($expected_str, (string) $userinfo);
        $this->assertSame($uri_component, $userinfo->getUriComponent());
    }

    public function userInfoProvider()
    {
        return [
            ['login', 'pass', 'login', 'pass', 'login:pass', 'login:pass@'],
            ['login', null, 'login', '', 'login', 'login@'],
            [null, null, '', '', '', ''],
            ['', null, '', '', '', ''],
            ['', '', '', '', '', ''],
            [null, 'pass', '', '', '', ''],
        ];
    }


    public function testIsNull()
    {
        $this->assertTrue((new UserInfo(null))->isDefined());
        $this->assertFalse((new UserInfo('toto'))->isDefined());
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
            'empty password' => ['user:', 'user', '', 'user'],
            'no password' => ['user', 'user', '', 'user'],
            'no login but has password' => [':pass', '', '', ''],
            'empty all' => ['', '', '', ''],
        ];
    }

    public function testWithContentReturnSameInstance()
    {
        $conn = new UserInfo();
        $this->assertEquals($conn, $conn->withContent(':pass'));

        $conn = new UserInfo('user', 'pass');
        $this->assertSame($conn, $conn->withContent('user:pass'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testWithContentThrowsInvalidArgumentException()
    {
        (new UserInfo())->withContent([]);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorThrowsInvalidArgumentException1()
    {
        new UserInfo('tot:o');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorThrowsInvalidArgumentException2()
    {
        new UserInfo('toto', 'p@ass');
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
            'empty password' => ['user', '', 'user'],
            'no password' => ['user', null, 'user'],
            'no login but has password' => ['', 'pass', ''],
            'empty all' => ['', '', '', ''],
        ];
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testWithUserInfoThrowException()
    {
        (new UserInfo('user', 'pass'))->withUserInfo(null);
    }
}

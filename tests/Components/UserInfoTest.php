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

use League\Uri\Components\UserInfo;
use League\Uri\Exception\InvalidArgument;
use League\Uri\Exception\UnknownEncoding;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * @group userinfo
 * @coversDefaultClass \League\Uri\Components\UserInfo
 */
class UserInfoTest extends TestCase
{
    /**
     * @dataProvider userInfoProvider
     * @param mixed       $user
     * @param mixed       $pass
     * @param string|null $expected_user
     * @param string|null $expected_pass
     * @param string      $expected_str
     * @param string      $uri_component
     * @param string      $iri_str
     * @param string      $rfc1738_str
     * @covers ::__construct
     * @covers ::validateComponent
     * @covers ::getContent
     * @covers ::__toString
     * @covers ::getUriComponent
     * @covers ::decodeMatches
     * @covers ::encodeMatches
     * @covers ::getPass
     * @covers ::getUser
     * @covers ::encodeComponent
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
                'user' => new UserInfo('login'),
                'pass' => new UserInfo('pass'),
                'expected_user' => 'login',
                'expected_pass' => 'pass',
                'expected_str' => 'login:pass',
                'uri_component' => 'login:pass@',
                'iri_str' => 'login:pass',
                'rfc1738_str' => 'login:pass',
            ],
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
                'user' => 'login%61',
                'pass' => 'pass',
                'expected_user' => 'login%61',
                'expected_pass' => 'pass',
                'expected_str' => 'login%61:pass',
                'uri_component' => 'login%61:pass@',
                'iri_str' => 'login%61:pass',
                'rfc1738_str' => 'login%61:pass',
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

    /**
     * @dataProvider createFromStringProvider
     * @param mixed  $user
     * @param mixed  $str
     * @param mixed  $expected_user
     * @param mixed  $expected_pass
     * @param string $expected_str
     * @covers ::withContent
     * @covers ::getUser
     * @covers ::getPass
     * @covers ::decodeMatches
     */
    public function testWithContent($user, $str, $expected_user, $expected_pass, $expected_str)
    {
        $conn = (new UserInfo($user))->withContent($str);
        $this->assertSame($expected_user, $conn->getUser());
        $this->assertSame($expected_pass, $conn->getPass());
        $this->assertSame($expected_str, (string) $conn);
    }

    public function createFromStringProvider()
    {
        return [
            'simple' => [null, 'user:pass', 'user', 'pass', 'user:pass'],
            'empty password' => [null, 'user:', 'user', '', 'user:'],
            'no password' => [null, 'user', 'user', null, 'user'],
            'no login but has password' => [null, ':pass', '', null, ''],
            'empty all' => [null, '', '', null, ''],
            'null content' => [null, null, null, null, ''],
            'encoded chars' => [null, 'foo%40bar:bar%40foo', 'foo%40bar', 'bar%40foo', 'foo%40bar:bar%40foo'],
            'component interface' => [null, new UserInfo('user', 'pass'), 'user', 'pass', 'user:pass'],
            'reset object' => ['login', new UserInfo(null), null, null, ''],
        ];
    }

    /**
     * @covers ::withContent
     * @covers ::decodeMatches
     */
    public function testWithContentReturnSameInstance()
    {
        $conn = new UserInfo();
        $this->assertEquals($conn, $conn->withContent(':pass'));

        $conn = new UserInfo('user', 'pass');
        $this->assertSame($conn, $conn->withContent('user:pass'));
    }

    /**
     * @covers ::__set_state
     */
    public function testSetState()
    {
        $conn = new UserInfo('user', 'pass');
        $generateConn = eval('return '.var_export($conn, true).';');
        $this->assertEquals($conn, $generateConn);
    }

    /**
     * @covers ::__debugInfo
     */
    public function testDebugInfo()
    {
        $component = new UserInfo('user', 'pass');
        $debugInfo = $component->__debugInfo();
        $this->assertArrayHasKey('component', $debugInfo);
        $this->assertSame($component->getContent(), $debugInfo['component']);
    }

    /**
     * @dataProvider withUserInfoProvider
     * @param mixed  $user
     * @param mixed  $pass
     * @param string $expected
     * @covers ::withUserInfo
     * @covers ::decodeMatches
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

    /**
     * @covers ::getUser
     */
    public function testGetUserThrowsInvalidArgumentException()
    {
        $this->expectException(UnknownEncoding::class);
        (new UserInfo())->getUser(-1);
    }

    /**
     * @covers ::getPass
     */
    public function testGetPassThrowsInvalidArgumentException()
    {
        $this->expectException(UnknownEncoding::class);
        (new UserInfo())->getPass(-1);
    }

    /**
     * @covers ::getContent
     */
    public function testInvalidEncodingTypeThrowException()
    {
        $this->expectException(UnknownEncoding::class);
        (new UserInfo('user', 'pass'))->getContent(-1);
    }

    /**
     * @covers ::withContent
     */
    public function testWithContentThrowsInvalidArgumentException()
    {
        $this->expectException(TypeError::class);
        (new UserInfo())->withContent(date_create());
    }

    public function testConstructorThrowsTypeError()
    {
        $this->expectException(TypeError::class);
        new UserInfo(date_create());
    }

    public function testConstructorThrowsException()
    {
        $this->expectException(InvalidArgument::class);
        new UserInfo("\0");
    }
}

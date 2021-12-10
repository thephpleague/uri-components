<?php

/**
 * League.Uri (https://uri.thephpleague.com/components/2.0/)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Uri\Components;

use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Http;
use League\Uri\Uri;
use PHPUnit\Framework\TestCase;
use TypeError;
use function date_create;
use function var_export;

/**
 * @group userinfo
 * @coversDefaultClass \League\Uri\Components\UserInfo
 */
final class UserInfoTest extends TestCase
{
    /**
     * @dataProvider userInfoProvider
     *
     * @covers ::__construct
     * @covers ::validateComponent
     * @covers ::getContent
     * @covers ::__toString
     * @covers ::decodeMatches
     * @covers ::encodeMatches
     * @covers ::getPass
     * @covers ::getUser
     * @covers ::encodeComponent
     * @covers ::getUriComponent
     *
     * @param object|float|int|string|bool|null $user
     * @param object|float|int|string|bool|null $pass
     * @param ?string                           $expected_user
     * @param ?string                           $expected_pass
     */
    public function testConstructor(
        $user,
        $pass,
        ?string $expected_user,
        ?string $expected_pass,
        string $expected_str,
        string $uriComponent
    ): void {
        $userinfo = new UserInfo($user, $pass);
        self::assertSame($expected_user, $userinfo->getUser());
        self::assertSame($expected_pass, $userinfo->getPass());
        self::assertSame($expected_str, (string) $userinfo);
        self::assertSame($uriComponent, $userinfo->getUriComponent());
    }

    public function userInfoProvider(): array
    {
        return [
            [
                'user' => new UserInfo('login'),
                'pass' => new UserInfo('pass'),
                'expected_user' => 'login',
                'expected_pass' => 'pass',
                'expected_str' => 'login:pass',
                'uriComponent' => 'login:pass@',
            ],
            [
                'user' => 'login',
                'pass' => 'pass',
                'expected_user' => 'login',
                'expected_pass' => 'pass',
                'expected_str' => 'login:pass',
                'uriComponent' => 'login:pass@',
            ],
            [
                'user' => 'login%61',
                'pass' => 'pass',
                'expected_user' => 'login%61',
                'expected_pass' => 'pass',
                'expected_str' => 'login%61:pass',
                'uriComponent' => 'login%61:pass@',
            ],
            [
                'user' => 'login',
                'pass' => null,
                'expected_user' => 'login',
                'expected_pass' => null,
                'expected_str' => 'login',
                'uriComponent' => 'login@',
            ],
            [
                'user' => null,
                'pass' => null,
                'expected_user' => null,
                'expected_pass' => null,
                'expected_str' => '',
                'uriComponent' => '',
            ],
            [
                'user' => '',
                'pass' => null,
                'expected_user' => '',
                'expected_pass' => null,
                'expected_str' => '',
                'uriComponent' => '@',
            ],
            [
                'user' => '',
                'pass' => '',
                'expected_user' => '',
                'expected_pass' => null,
                'expected_str' => '',
                'uriComponent' => '@',
            ],
            [
                'user' => null,
                'pass' => 'pass',
                'expected_user' => null,
                'expected_pass' => null,
                'expected_str' => '',
                'uriComponent' => '',
            ],
            [
                'user' => 'foò',
                'pass' => 'bar',
                'expected_user' => 'foò',
                'expected_pass' => 'bar',
                'expected_str' => 'fo%C3%B2:bar',
                'uriComponent' => 'fo%C3%B2:bar@',
            ],
            [
                'user' => 'fo+o',
                'pass' => 'ba+r',
                'expected_user' => 'fo+o',
                'expected_pass' => 'ba+r',
                'expected_str' => 'fo+o:ba+r',
                'uriComponent' => 'fo+o:ba+r@',
            ],

        ];
    }

    /**
     * @dataProvider createFromStringProvider
     *
     * @covers ::withContent
     * @covers ::getUser
     * @covers ::getPass
     * @covers ::decode
     * @covers ::decodeMatches
     *
     * @param UriComponentInterface|string|null $str
     * @param ?string                           $user
     * @param ?string                           $expected_user
     * @param ?string                           $expected_pass
     */
    public function testWithContent(
        ?string $user,
        $str,
        ?string $expected_user,
        ?string $expected_pass,
        string $expected_str
    ): void {
        $conn = (new UserInfo($user))->withContent($str);
        self::assertSame($expected_str, (string) $conn);
        self::assertSame((new UserInfo($expected_user, $expected_pass))->getContent(), $conn->getContent());
    }

    public function createFromStringProvider(): array
    {
        return [
            'simple' => [null, 'user:pass', 'user', 'pass', 'user:pass'],
            'empty password' => [null, 'user:', 'user', '', 'user:'],
            'no password' => [null, 'user', 'user', null, 'user'],
            'no login but has password' => [null, ':pass', '', null, ''],
            'empty all' => [null, '', '', null, ''],
            'null content' => [null, null, null, null, ''],
            'component interface' => [null, new UserInfo('user', 'pass'), 'user', 'pass', 'user:pass'],
            'reset object' => ['login', new UserInfo(null), null, null, ''],
            'encoded chars 1' => [null, 'foo%40bar:bar%40foo', 'foo@bar', 'bar@foo', 'foo%40bar:bar%40foo'],
            'encoded chars 3' => [null, 'foo%a1bar:bar%40foo', 'foo%A1bar', 'bar@foo', 'foo%A1bar:bar%40foo'],
            'encoded chars 2' => [null, "user:'O=+9zLZ%7d%25%7bz+:tC", 'user', "'O=+9zLZ}%{z+:tC", "user:'O=+9zLZ%7D%25%7Bz+:tC"],
        ];
    }

    /**
     * @covers ::withContent
     * @covers ::decode
     * @covers ::decodeMatches
     */
    public function testWithContentReturnSameInstance(): void
    {
        $conn = new UserInfo();
        self::assertEquals($conn, $conn->withContent(':pass'));

        $conn = new UserInfo('user', 'pass');
        self::assertSame($conn, $conn->withContent('user:pass'));
    }

    /**
     * @covers ::__set_state
     */
    public function testSetState(): void
    {
        $conn = new UserInfo('user', 'pass');
        $generateConn = eval('return '.var_export($conn, true).';');
        self::assertEquals($conn, $generateConn);
    }

    /**
     * @dataProvider withUserInfoProvider
     *
     * @covers ::withUserInfo
     * @covers ::decodeMatches
     * @covers ::decode
     *
     * @param ?string $pass
     */
    public function testWithUserInfo(string $user, ?string $pass, string $expected): void
    {
        self::assertSame($expected, (string) (new UserInfo('user', 'pass'))->withUserInfo($user, $pass));
    }

    public function withUserInfoProvider(): array
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
     * @covers ::withContent
     */
    public function testWithContentThrowsInvalidUriComponentException(): void
    {
        $this->expectException(TypeError::class);
        (new UserInfo())->withContent(date_create());
    }

    public function testConstructorThrowsTypeError(): void
    {
        $this->expectException(TypeError::class);
        new UserInfo(date_create());
    }

    public function testConstructorThrowsException(): void
    {
        $this->expectException(SyntaxError::class);
        new UserInfo("\0");
    }

    /**
     * @dataProvider getURIProvider
     * @covers ::createFromUri
     * @covers ::decode
     *
     * @param mixed   $uri      an URI object
     * @param ?string $expected
     */
    public function testCreateFromUri($uri, ?string $expected): void
    {
        $userInfo = UserInfo::createFromUri($uri);

        self::assertSame($expected, $userInfo->getContent());
    }

    public function getURIProvider(): iterable
    {
        return [
            'PSR-7 URI object' => [
                'uri' => Http::createFromString('http://foo:bar@example.com?foo=bar'),
                'expected' => 'foo:bar',
            ],
            'PSR-7 URI object with no user info' => [
                'uri' => Http::createFromString('path/to/the/sky?foo'),
                'expected' => null,
            ],
            'PSR-7 URI object with empty string user info' => [
                'uri' => Http::createFromString('http://@example.com?foo=bar'),
                'expected' => null,
            ],
            'League URI object' => [
                'uri' => Uri::createFromString('http://foo:bar@example.com?foo=bar'),
                'expected' => 'foo:bar',
            ],
            'League URI object with no user info' => [
                'uri' => Uri::createFromString('path/to/the/sky?foo'),
                'expected' => null,
            ],
            'League URI object with empty string user info' => [
                'uri' => Uri::createFromString('http://@example.com?foo=bar'),
                'expected' => '',
            ],
            'URI object with encoded user info string' => [
                'uri' => Uri::createFromString('http://login%af:bar@example.com:81'),
                'expected' => 'login%AF:bar',
            ],
        ];
    }

    public function testCreateFromUriThrowsTypeError(): void
    {
        $this->expectException(TypeError::class);

        UserInfo::createFromUri('http://example.com#foobar');
    }

    public function testCreateFromAuthorityWithoutUserInfoComponent(): void
    {
        $uri = Uri::createFromString('http://example.com:443');
        $auth = Authority::createFromUri($uri);

        self::assertEquals(UserInfo::createFromUri($uri), UserInfo::createFromAuthority($auth));
    }

    public function testCreateFromAuthorityWithActualUserInfoComponent(): void
    {
        $uri = Uri::createFromString('http://user:pass@example.com:443');
        $auth = Authority::createFromUri($uri);

        self::assertEquals(UserInfo::createFromUri($uri), UserInfo::createFromAuthority($auth));
    }
}

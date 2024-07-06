<?php

/**
 * League.Uri (https://uri.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Uri\Components;

use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Http;
use League\Uri\Uri;
use League\Uri\UriString;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;

#[CoversClass(UserInfo::class)]
#[Group('userinfo')]
final class UserInfoTest extends TestCase
{
    #[DataProvider('userInfoProvider')]
    public function testConstructor(
        Stringable|string|null $user,
        Stringable|string|null $pass,
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

    public static function userInfoProvider(): array
    {
        return [
            'using stringable object' => [
                'user' => Host::new('login'),
                'pass' => Scheme::new('pass'),
                'expected_user' => 'login',
                'expected_pass' => 'pass',
                'expected_str' => 'login:pass',
                'uriComponent' => 'login:pass@',
            ],
            'using strings' => [
                'user' => 'login',
                'pass' => 'pass',
                'expected_user' => 'login',
                'expected_pass' => 'pass',
                'expected_str' => 'login:pass',
                'uriComponent' => 'login:pass@',
            ],
            'using encoded string for username' => [
                'user' => 'login%61',
                'pass' => 'pass',
                'expected_user' => 'login%61',
                'expected_pass' => 'pass',
                'expected_str' => 'login%61:pass',
                'uriComponent' => 'login%61:pass@',
            ],
            'with an undefined password' => [
                'user' => 'login',
                'pass' => null,
                'expected_user' => 'login',
                'expected_pass' => null,
                'expected_str' => 'login',
                'uriComponent' => 'login@',
            ],
            'with an undefined username and password' => [
                'user' => null,
                'pass' => null,
                'expected_user' => null,
                'expected_pass' => null,
                'expected_str' => '',
                'uriComponent' => '',
            ],
            'with an undefined password and an empty string as the username' => [
                'user' => '',
                'pass' => null,
                'expected_user' => '',
                'expected_pass' => null,
                'expected_str' => '',
                'uriComponent' => '@',
            ],
            'with empty strings' => [
                'user' => '',
                'pass' => '',
                'expected_user' => '',
                'expected_pass' => '',
                'expected_str' => ':',
                'uriComponent' => ':@',
            ],
            'with encoded username and password' => [
                'user' => 'foò',
                'pass' => 'bar',
                'expected_user' => 'foò',
                'expected_pass' => 'bar',
                'expected_str' => 'fo%C3%B2:bar',
                'uriComponent' => 'fo%C3%B2:bar@',
            ],
            'with encoded username and password containing + sign' => [
                'user' => 'fo+o',
                'pass' => 'ba+r',
                'expected_user' => 'fo+o',
                'expected_pass' => 'ba+r',
                'expected_str' => 'fo+o:ba+r',
                'uriComponent' => 'fo+o:ba+r@',
            ],
        ];
    }

    public static function createFromStringProvider(): array
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

    public function testWithContentReturnSameInstance(): void
    {
        self::assertEquals(
            new UserInfo('user', 'pass'),
            UserInfo::new('user:pass')
        );

        self::assertEquals(
            new UserInfo('user', 'pass'),
            UserInfo::new(Path::new('user:pass'))
        );
    }

    #[DataProvider('withUserInfoProvider')]
    public function testWithUserInfo(?string $user, ?string $pass, ?string $expected): void
    {
        self::assertSame($expected, UserInfo::new()->withUser($user)->withPass($pass)->toString());
    }

    public static function withUserInfoProvider(): array
    {
        return [
            'simple' => ['user', 'pass', 'user:pass'],
            'empty password' => ['user', '', 'user:'],
            'no password' => ['user', null, 'user'],
            'no login but has password' => ['', 'pass', ':pass'],
            'empty all' => ['', '', ':'],
            'null all' => [null, null, ''],
        ];
    }

    public function testItWillThrowIfWeAttemptToModifyAPasswordOnANullUser(): void
    {
        $this->expectException(SyntaxError::class);

        UserInfo::new()->withPass('toto');
    }

    public function testConstructorThrowsException(): void
    {
        $this->expectException(SyntaxError::class);

        new UserInfo("\0");
    }

    #[DataProvider('getURIProvider')]
    public function testCreateFromUri(UriInterface|Psr7UriInterface $uri, ?string $expected): void
    {
        $userInfo = UserInfo::fromUri($uri);

        self::assertSame($expected, $userInfo->value());
    }

    public static function getURIProvider(): iterable
    {
        return [
            'PSR-7 URI object' => [
                'uri' => Http::new('http://foo:bar@example.com?foo=bar'),
                'expected' => 'foo:bar',
            ],
            'PSR-7 URI object with no user info' => [
                'uri' => Http::new('path/to/the/sky?foo'),
                'expected' => null,
            ],
            'PSR-7 URI object with empty string user info' => [
                'uri' => Http::new('http://@example.com?foo=bar'),
                'expected' => '',
            ],
            'League URI object' => [
                'uri' => Uri::new('http://foo:bar@example.com?foo=bar'),
                'expected' => 'foo:bar',
            ],
            'League URI object with no user info' => [
                'uri' => Uri::new('path/to/the/sky?foo'),
                'expected' => null,
            ],
            'League URI object with empty string user info' => [
                'uri' => Uri::new('http://@example.com?foo=bar'),
                'expected' => '',
            ],
            'URI object with encoded user info string' => [
                'uri' => Uri::new('http://login%af:bar@example.com:81'),
                'expected' => 'login%AF:bar',
            ],
        ];
    }

    public function testCreateFromAuthorityWithoutUserInfoComponent(): void
    {
        $uri = Uri::new('http://example.com:443');
        $auth = Authority::fromUri($uri);

        self::assertEquals(
            UserInfo::fromUri($uri),
            UserInfo::fromAuthority($auth)
        );

        self::assertEquals(
            UserInfo::fromUri($uri),
            UserInfo::fromAuthority($uri->getAuthority())
        );
    }

    public function testCreateFromAuthorityWithActualUserInfoComponent(): void
    {
        $uri = Uri::new('http://user:pass@example.com:443');
        $auth = Authority::fromUri($uri);

        self::assertEquals(UserInfo::fromUri($uri), UserInfo::fromAuthority($auth));
    }

    public function testItFailsToCreateANewInstanceWhenTheUsernameIsUndefined(): void
    {
        $this->expectException(SyntaxError::class);

        new UserInfo(null, 'password');
    }

    /**
     * @param array{user: ?string, pass: ?string} $components
     */
    #[DataProvider('providesUriToParse')]
    public function testNewInstanceFromUriParsing(string $uri, ?string $expected, array $components): void
    {
        $userInfo = UserInfo::fromComponents(UriString::parse($uri));

        self::assertSame($expected, $userInfo->value());
        self::assertSame($components, $userInfo->components());
    }

    /**
     * @return iterable<string, array{uri: string, expected: ?string}>
     */
    public static function providesUriToParse(): iterable
    {
        yield 'uri without user info' => [
            'uri' => 'https://example.com',
            'expected' => null,
            'components' => ['user' => null, 'pass' => null],
        ];

        yield 'uri with a full user info' => [
            'uri' => 'https://user:pass@example.com',
            'expected' => 'user:pass',
            'components' => ['user' => 'user', 'pass' => 'pass'],
        ];

        yield 'uri with a user info with an empty user name' => [
            'uri' => 'https://:pass@example.com',
            'expected' => ':pass',
            'components' => ['user' => '', 'pass' => 'pass'],
        ];
    }
}

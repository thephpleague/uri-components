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
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use function parse_url;
use function var_export;

/**
 * @group userinfo
 * @coversDefaultClass \League\Uri\Components\Authority
 */
final class AuthorityTest extends TestCase
{
    /**
     * @covers ::__set_state
     * @covers ::validate
     */
    public function testSetState(): void
    {
        $authority = Authority::createFromString('foo:bar@example.com:443');
        $generatedAuthority = eval('return '.var_export($authority, true).';');
        self::assertEquals($authority, $generatedAuthority);
    }

    /**
     * @dataProvider validAuthorityDataProvider
     *
     * @covers ::createFromNull
     * @covers ::createFromString
     * @covers ::parse
     * @covers ::validate
     * @covers ::getHost
     * @covers ::getPort
     * @covers ::getUserInfo
     * @covers ::value
     * @param ?string $authority
     * @param ?string $host
     * @param ?int    $port
     * @param ?string $userInfo
     * @param ?string $component
     */
    public function testConstructor(
        ?string $authority,
        ?string $host,
        ?int $port,
        ?string $userInfo,
        ?string $component
    ): void {
        if (null === $authority) {
            $instance = Authority::createFromNull();
        } else {
            $instance = Authority::createFromString($authority);
        }

        self::assertSame($host, $instance->getHost());
        self::assertSame($port, $instance->getPort());
        self::assertSame($userInfo, $instance->getUserInfo());
        self::assertSame($component, $instance->value());
    }

    public static function validAuthorityDataProvider(): array
    {
        return [
            'null values' => [
                'authority' => null,
                'host' => null,
                'port' => null,
                'userInfo' => null,
                'component' => null,
            ],
            'empty string' => [
                'authority' => '',
                'host' => '',
                'port' => null,
                'userInfo' => null,
                'component' => '',
            ],
            'auth with port' => [
                'authority' => 'example.com:443',
                'host' => 'example.com',
                'port' => 443,
                'userInfo' => null,
                'component' => 'example.com:443',
            ],
            'auth with user info' => [
                'authority' => 'foo:bar@example.com',
                'host' => 'example.com',
                'port' => null,
                'userInfo' => 'foo:bar',
                'component' => 'foo:bar@example.com',
            ],
            'auth with user info AND port' => [
                'authority' => 'foo:bar@example.com:443',
                'host' => 'example.com',
                'port' => 443,
                'userInfo' => 'foo:bar',
                'component' => 'foo:bar@example.com:443',
            ],
        ];
    }

    /**
     * @dataProvider invalidAuthorityDataProvider
     *
     * @covers ::__construct
     * @covers ::parse
     * @covers ::validate
     */
    public function testConstructorFails(string $authority): void
    {
        $this->expectException(SyntaxError::class);

        Authority::createFromString($authority);
    }

    public static function invalidAuthorityDataProvider(): array
    {
        return [
            'invalid port' => ['foo:bar@example.com:foo'],
            'invalid user info' => ["\0foo:bar@example.com:443"],
            'invalid host' => ['foo:bar@[:1]:80'],
        ];
    }

    /**
     * @covers ::withHost
     * @covers ::validate
     */
    public function testWithHost(): void
    {
        $authority = Authority::createFromString('foo:bar@example.com:443');
        self::assertSame($authority, $authority->withHost('eXAmPle.CoM'));
        self::assertNotEquals($authority, $authority->withHost('[::1]'));
    }

    /**
     * @dataProvider invalidHostDataProvider
     *
     * @covers ::withHost
     * @covers ::validate
     * @param ?string $host
     */
    public function testWithHostFails(?string $host): void
    {
        $this->expectException(SyntaxError::class);

        Authority::createFromString('foo:bar@example.com:443')->withHost($host);
    }

    public static function invalidHostDataProvider(): array
    {
        return [
            'invalid host' => ["foo\0"],
            'null host' => [null],
        ];
    }

    /**
     * @covers ::withPort
     * @covers ::validate
     */
    public function testWithPort(): void
    {
        $authority = Authority::createFromString('foo:bar@example.com:443');

        self::assertSame($authority, $authority->withPort(443));
        self::assertNotEquals($authority, $authority->withPort(80));
    }

    /**
     * @covers ::withPort
     */
    public function testWithPortFails(): void
    {
        $this->expectException(SyntaxError::class);

        Authority::createFromString('foo:bar@example.com:443')->withPort(-1);
    }

    /**
     * @covers ::withUserInfo
     * @covers ::validate
     */
    public function testWithUserInfo(): void
    {
        $authority = Authority::createFromString('foo:bar@example.com:443');

        self::assertSame($authority, $authority->withUserInfo('foo', 'bar'));
        self::assertNotEquals($authority, $authority->withUserInfo('foo'));
    }

    /**
     * @covers ::withUserInfo
     */
    public function testWithUserInfoFails(): void
    {
        $this->expectException(SyntaxError::class);

        Authority::createFromString('foo:bar@example.com:443')->withUserInfo("\0foo", 'bar');
    }

    /**
     * @dataProvider stringRepresentationDataProvider
     *
     * @covers ::jsonSerialize
     * @covers ::__toString
     * @covers ::value
     * @covers ::getUriComponent
    */
    public function testAuthorityStringRepresentation(
        ?string $authority,
        string $string,
        ?string $json,
        ?string $content,
        string $uriComponent
    ): void {
        if (null === $authority) {
            $instance = Authority::createFromNull();
        } else {
            $instance = Authority::createFromString($authority);
        }

        self::assertSame($string, (string) $instance);
        self::assertSame($json, json_encode($instance));
        self::assertSame($content, $instance->value());
        self::assertSame($uriComponent, $instance->getUriComponent());
    }

    public static function stringRepresentationDataProvider(): array
    {
        return [
            'null' => [
                'authority' => null,
                'string' => '',
                'json' => 'null',
                'content' => null,
                'uriComponent' => '',
            ],
            'empty string' => [
                'authority' => '',
                'string' => '',
                'json' => '""',
                'content' => '',
                'uriComponent' => '//',
            ],
            'full authority' => [
                'authority' => 'foo:bar@eXAmPle.cOm:443',
                'string' => 'foo:bar@example.com:443',
                'json' => '"foo:bar@example.com:443"',
                'content' => 'foo:bar@example.com:443',
                'uriComponent' => '//foo:bar@example.com:443',
            ],
            'unicode host' => [
                'authority' => 'foo:bar@مثال.إختبار:443',
                'string' => 'foo:bar@xn--mgbh0fb.xn--kgbechtv:443',
                'json' => '"foo:bar@xn--mgbh0fb.xn--kgbechtv:443"',
                'content' => 'foo:bar@xn--mgbh0fb.xn--kgbechtv:443',
                'uriComponent' => '//foo:bar@xn--mgbh0fb.xn--kgbechtv:443',
            ],
        ];
    }


    /**
     * @dataProvider getURIProvider
     * @covers ::createFromUri
     */
    public function testCreateFromUri(UriInterface|Psr7UriInterface $uri, ?string $expected): void
    {
        $authority = Authority::createFromUri($uri);

        self::assertSame($expected, $authority->value());
    }

    public static function getURIProvider(): iterable
    {
        return [
            'PSR-7 URI object' => [
                'uri' => Http::createFromString('http://foo:bar@example.com?foo=bar'),
                'expected' => 'foo:bar@example.com',
            ],
            'PSR-7 URI object with no authority' => [
                'uri' => Http::createFromString('path/to/the/sky?foo'),
                'expected' => null,
            ],
            'PSR-7 URI object with empty string authority' => [
                'uri' => Http::createFromString('file:///path/to/the/sky'),
                'expected' => null,
            ],
            'League URI object' => [
                'uri' => Uri::createFromString('http://foo:bar@example.com?foo=bar'),
                'expected' => 'foo:bar@example.com',
            ],
            'League URI object with no authority' => [
                'uri' => Uri::createFromString('path/to/the/sky?foo'),
                'expected' => null,
            ],
            'League URI object with empty string authority' => [
                'uri' => Uri::createFromString('file:///path/to/the/sky'),
                'expected' => '',
            ],
        ];
    }

    public function testCreateFromParseUrl(): void
    {
        $instance = Authority::createFromComponents(parse_url('http://user:pass@ExaMplE.CoM:42#foobar'));

        self::assertSame('user:pass@example.com:42', $instance->__toString());
    }

    public function testCreateFromParseUrlWithoutAuthority(): void
    {
        $instance = Authority::createFromComponents(UriString::parse('/example.com:42#foobar'));

        self::assertNull($instance->value());
    }
}

<?php

/**
 * League.Uri (http://uri.thephpleague.com/components)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.0
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Uri\Components;

use League\Uri\Components\Authority;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Http;
use League\Uri\Uri;
use PHPUnit\Framework\TestCase;
use TypeError;
use function date_create;
use function var_export;

/**
 * @group userinfo
 * @coversDefaultClass \League\Uri\Components\Authority
 */
class AuthorityTest extends TestCase
{

    /**
     * @covers ::__set_state
     * @covers ::validate
     */
    public function testSetState(): void
    {
        $authority = new Authority('foo:bar@example.com:443');
        $generatedAuthority = eval('return '.var_export($authority, true).';');
        self::assertEquals($authority, $generatedAuthority);
    }

    /**
     * @dataProvider validAuthorityDataProvider
     *
     * @covers ::__construct
     * @covers ::parse
     * @covers ::validate
     * @covers ::getHost
     * @covers ::getPort
     * @covers ::getUserInfo
     * @covers ::getContent
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
        $authority = new Authority($authority);
        self::assertSame($host, $authority->getHost());
        self::assertSame($port, $authority->getPort());
        self::assertSame($userInfo, $authority->getUserInfo());
        self::assertSame($component, $authority->getContent());
    }

    public function validAuthorityDataProvider(): array
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
        self::expectException(SyntaxError::class);
        $authority = new Authority($authority);
    }

    public function invalidAuthorityDataProvider(): array
    {
        return [
            'invalid port' => ['foo:bar@example.com:foo'],
            'invalid user info' => ["\0foo:bar@example.com:443"],
            'invalid host' => ['foo:bar@[:1]:80'],
        ];
    }

    /**
     * @covers ::__construct
     */
    public function testConstructorFailsWithWrongType(): void
    {
        self::expectException(TypeError::class);
        new Authority(date_create());
    }

    /**
     * @covers ::withContent
     * @covers ::validate
     */
    public function testWithContent(): void
    {
        $authority = new Authority('foo:bar@example.com:443');
        self::assertSame($authority, $authority->withContent('foo:bar@example.com:443'));
        self::assertNotEquals($authority, $authority->withContent('example.com:443'));
    }

    /**
     * @covers ::withHost
     * @covers ::validate
     */
    public function testWithHost(): void
    {
        $authority = new Authority('foo:bar@example.com:443');
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
        self::expectException(SyntaxError::class);
        $authority = (new Authority('foo:bar@example.com:443'))->withHost($host);
    }

    public function invalidHostDataProvider(): array
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
        $authority = new Authority('foo:bar@example.com:443');
        self::assertSame($authority, $authority->withPort(443));
        self::assertNotEquals($authority, $authority->withPort(80));
    }

    /**
     * @covers ::withPort
     */
    public function testWithPortFails(): void
    {
        self::expectException(SyntaxError::class);
        $authority = (new Authority('foo:bar@example.com:443'))->withPort(-1);
    }

    /**
     * @covers ::withUserInfo
     * @covers ::validate
     */
    public function testWithUserInfo(): void
    {
        $authority = new Authority('foo:bar@example.com:443');
        self::assertSame($authority, $authority->withUserInfo('foo', 'bar'));
        self::assertNotEquals($authority, $authority->withUserInfo('foo'));
    }

    /**
     * @covers ::withUserInfo
     */
    public function testWithUserInfoFails(): void
    {
        self::expectException(SyntaxError::class);
        $authority = (new Authority('foo:bar@example.com:443'))->withUserInfo("\0foo", 'bar');
    }

    /**
     * @dataProvider stringRepresentationDataProvider
     *
     * @covers ::jsonSerialize
     * @covers ::__toString
     * @covers ::getContent
     * @covers ::getUriComponent
     *
     * @param ?string $authority
     * @param ?string $json
     * @param ?string $content
    */
    public function testAuthorityStringRepresentation(
        ?string $authority,
        string $string,
        ?string $json,
        ?string $content,
        string $uriComponent
    ): void {
        $authority = new Authority($authority);
        self::assertSame($string, (string) $authority);
        self::assertSame($json, json_encode($authority));
        self::assertSame($content, $authority->getContent());
        self::assertSame($uriComponent, $authority->getUriComponent());
    }

    public function stringRepresentationDataProvider(): array
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
     *
     * @param mixed   $uri      an URI object
     * @param ?string $expected
     */
    public function testCreateFromUri($uri, ?string $expected): void
    {
        $authority = Authority::createFromUri($uri);

        self::assertSame($expected, $authority->getContent());
    }

    public function getURIProvider(): iterable
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

    public function testCreateFromUriThrowsTypeError(): void
    {
        self::expectException(TypeError::class);

        Authority::createFromUri('http://example.com#foobar');
    }
}

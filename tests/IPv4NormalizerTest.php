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

declare(strict_types=1);

namespace LeagueTest\Uri;

use League\Uri\Components\Host;
use League\Uri\Exceptions\Ipv4CalculatorMissing;
use League\Uri\IPv4Normalizer;
use League\Uri\Maths\GMPMath;
use League\Uri\Maths\PHPMath;
use PHPUnit\Framework\TestCase;
use function extension_loaded;
use const PHP_INT_SIZE;

/**
 * @coversDefaultClass \League\Uri\IPv4Normalizer
 */
final class IPv4NormalizerTest extends TestCase
{
    /**
     * @dataProvider providerHost
     * @param ?string $input
     * @param ?string $expected
     */
    public function testParseWithoutGMPAndPHPMath(?string $input, ?string $expected): void
    {
        if (8 !== PHP_INT_SIZE && !extension_loaded('gmp')) {
            self::expectException(Ipv4CalculatorMissing::class);
            IPv4Normalizer::normalize(new Host($input));
        }

        self::markTestSkipped('The PHP is compile for a x64 OS or loads the GMP extension.');
    }

    /**
     * @dataProvider providerHost
     * @param ?string $input
     * @param ?string $expected
     */
    public function testParseWithAutoDefineMath(?string $input, ?string $expected): void
    {
        if (!extension_loaded('gmp') && 8 > PHP_INT_SIZE) {
            self::markTestSkipped('The PHP is compile for a x64 OS or loads the GMP extension.');
        }

        self::assertEquals(new Host($expected), IPv4Normalizer::normalize(new Host($input)));
    }

    /**
     * @dataProvider providerHost
     * @param ?string $input
     * @param ?string $expected
     */
    public function testParseWithGMPMath(?string $input, ?string $expected): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('The GMP extension is needed to execute this test.');
        }

        self::assertEquals(new Host($expected), IPv4Normalizer::normalize(new Host($input), new GMPMath()));
    }

    /**
     * @dataProvider providerHost
     * @param ?string $input
     * @param ?string $expected
     */
    public function testParseWithPHPMath(?string $input, ?string $expected): void
    {
        if (8 > PHP_INT_SIZE) {
            self::markTestSkipped('The PHP must be compile for a x64 OS.');
        }

        self::assertEquals(new Host($expected), IPv4Normalizer::normalize(new Host($input), new PHPMath()));
    }

    public function providerHost(): array
    {
        return [
            'null host' => [null, null],
            'empty host' => ['', ''],
            '0 host' => ['0', '0.0.0.0'],
            'normal IP' => ['192.168.0.1', '192.168.0.1'],
            'octal (1)' => ['030052000001', '192.168.0.1'],
            'octal (2)' => ['0300.0250.0000.0001', '192.168.0.1'],
            'hexadecimal (1)' => ['0x', '0.0.0.0'],
            'hexadecimal (2)' => ['0xffffffff', '255.255.255.255'],
            'decimal (1)' => ['3232235521', '192.168.0.1'],
            'decimal (2)' => ['999999999', '59.154.201.255'],
            'decimal (3)' => ['256', '0.0.1.0'],
            'decimal (4)' => ['192.168.257', '192.168.1.1'],
            'invalid host (0)' => ['256.256.256.256.256', '256.256.256.256.256'],
            'invalid host (1)' => ['256.256.256.256', '256.256.256.256'],
            'invalid host (3)' => ['256.256.256', '256.256.256'],
            'invalid host (4)' => ['999999999.com', '999999999.com'],
            'invalid host (5)' => ['10000000000', '10000000000'],
            'invalid host (6)' => ['192.168.257.com', '192.168.257.com'],
            'invalid host (7)' => ['192..257', '192..257'],
            'invalid host (8)' => ['0foobar', '0foobar'],
            'invalid host (9)' => ['0xfoobar', '0xfoobar'],
            'invalid host (10)' => ['0xffffffff1', '0xffffffff1'],
            'invalid host (11)' => ['0300.5200.0000.0001', '0300.5200.0000.0001'],
        ];
    }
}

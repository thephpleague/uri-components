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

namespace League\Uri;

use League\Uri\Contracts\HostInterface;
use League\Uri\Exceptions\Ipv4CalculatorMissing;
use League\Uri\Maths\GMPMath;
use League\Uri\Maths\Math;
use League\Uri\Maths\PHPMath;
use RuntimeException;
use function array_pop;
use function count;
use function ctype_digit;
use function end;
use function explode;
use function function_exists;
use function ltrim;
use function preg_match;
use function sprintf;
use function strpos;
use const PHP_INT_SIZE;

final class IPv4Normalizer
{
    private const MAX_IPV4_NUMBER = 4294967295;

    private const REGEXP_OCTAL = '/^[0-7]+$/';

    private const REGEXP_NUMBER = '/^(?<base>0x|0)?(?<number>[0-9a-f]*)$/';

    /**
     * Loads a Math implementation depending on the underlying OS settings.
     *
     * @throws RuntimeException If the underlying OS settings are invalid.
     */
    private static function math(): ?Math
    {
        static $math;

        if (null !== $math) {
            return $math;
        }

        if (4 < PHP_INT_SIZE) {
            $math = new PHPMath();

            return $math;
        }

        if (function_exists('gimp_init')) {
            $math = new GMPMath();

            return $math;
        }

        return null;
    }

    /**
     * Normalizes the host content to a IPv4 Host string representation if possible
     * otherwise returns the Host instance unchanged.
     *
     * @param ?Math $math
     *
     * @throws Ipv4CalculatorMissing
     */
    public static function normalize(HostInterface $host, ?Math $math = null): HostInterface
    {
        $hostString = (string) $host;
        if ($host->isIp() || '' === $hostString || false !== strpos($hostString, '..')) {
            return $host;
        }

        $parts = explode('.', $hostString);
        if ('' === end($parts)) {
            array_pop($parts);
        }

        if (4 < count($parts)) {
            return $host;
        }

        $math = $math ?? self::math();
        if (null === $math) {
            throw new Ipv4CalculatorMissing(sprintf(
                'No %s was provided or detected for your platform. Please run you script on a x.64 PHP build or install the GMP extension to enable autodetection.',
                Math::class
            ));
        }

        $numbers = [];
        foreach ($parts as $part) {
            $number = self::filterIPV4Part($part, $math);
            if (null === $number || $number < 0 || $number > self::MAX_IPV4_NUMBER) {
                return $host;
            }

            $numbers[] = $number;
        }

        $ipv4 = array_pop($numbers);
        $max = $math->pow(256, 6 - count($numbers));
        if ($math->compare($ipv4, $max) > 0) {
            return $host;
        }

        foreach ($numbers as $offset => $number) {
            if ($number > 255) {
                return $host;
            }
            $ipv4 += $math->multiply($number, $math->pow(256, 3 - $offset));
        }

        /** @var HostInterface $newHost */
        $newHost = $host->withContent($math->long2Ip($ipv4));

        return $newHost;
    }

    /**
     * Translates a IPv4 part from a non decimal representation to its integer representation.
     *
     * @return mixed Returns null if it can not correctly convert the part to the integer representation
     */
    private static function filterIPV4Part(string $part, Math $math)
    {
        if (1 !== preg_match(self::REGEXP_NUMBER, $part, $matches)) {
            return null;
        }

        $number = ltrim($matches['number'], '0');
        if ('' === $number) {
            return 0;
        }

        if ('0x' === $matches['base']) {
            if (ctype_xdigit($number)) {
                return $math->baseConvert($number, 16);
            }

            return null;
        }

        if ('0' === $matches['base']) {
            if (1 === preg_match(self::REGEXP_OCTAL, $number)) {
                return $math->baseConvert($number, 8);
            }

            return null;
        }

        if ('' === $matches['base'] && ctype_digit($number)) {
            return $math->baseConvert($number, 10);
        }

        return null;
    }
}

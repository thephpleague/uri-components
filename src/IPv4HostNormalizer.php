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
use function array_pop;
use function count;
use function explode;
use function extension_loaded;
use function ltrim;
use function preg_match;
use function sprintf;
use function substr;
use const PHP_INT_SIZE;

final class IPv4HostNormalizer
{
    private const MAX_IPV4_NUMBER = 4294967295;

    private const REGEXP_IPV4_HOST = '/
        (?(DEFINE) # . is missing as it is used to separate labels
            (?<hexadecimal>0x[[:xdigit:]]*) 
            (?<octal>0[0-7]*)
            (?<decimal>\d+)
            (?<ipv4_part>(?:(?&hexadecimal)|(?&octal)|(?&decimal))*)
        )
        ^
            (?:(?&ipv4_part)\.){0,3}
            (?&ipv4_part)\.?
        $
    /x';

    private const REGEXP_IPV4_NUMBER_PER_BASE = [
        '/^0x(?<number>[[:xdigit:]]*)$/' => 16,
        '/^0(?<number>[0-7]*)$/' => 8,
        '/^(?<number>\d+)$/' => 10,
    ];

    /**
     * Normalizes the host content to a IPv4 Host string representation if possible
     * otherwise returns the Host instance unchanged.
     *
     * @see https://url.spec.whatwg.org/#concept-ipv4-parser
     *
     * @param ?Math $math
     */
    public static function normalize(HostInterface $host, ?Math $math = null): HostInterface
    {
        if (!$host->isDomain()) {
            return $host;
        }

        $hostString = (string) $host;
        if ('' === $hostString || 1 !== preg_match(self::REGEXP_IPV4_HOST, $hostString)) {
            return $host;
        }

        if ('.' === substr($hostString, -1, 1)) {
            $hostString = substr($hostString, 0, -1);
        }

        $math = $math ?? self::math();
        $numbers = [];
        foreach (explode('.', $hostString) as $label) {
            $number = self::labelToIpv4Number($label, $math);
            if (null === $number) {
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
            if ($math->compare($number, 255) > 0) {
                return $host;
            }
            $ipv4 += $math->multiply($number, $math->pow(256, 3 - $offset));
        }

        /** @var HostInterface $newHost */
        $newHost = $host->withContent($math->long2Ip($ipv4));

        return $newHost;
    }

    /**
     * Loads a Math implementation depending on the underlying OS settings.
     *
     * @throws Ipv4CalculatorMissing If no class is loaded
     */
    private static function math(): Math
    {
        static $math;

        if (null !== $math) {
            return $math;
        }

        if (extension_loaded('gmp')) {
            $math = new GMPMath();

            return $math;
        }

        if (4 < PHP_INT_SIZE) {
            $math = new PHPMath();

            return $math;
        }

        throw new Ipv4CalculatorMissing(sprintf(
            'No %s was provided or detected for your platform. Please run you script on a x.64 PHP build or install the GMP extension to enable autodetection.',
            Math::class
        ));
    }

    /**
     * Converts a domain label into a IPv4 integer part.
     *
     * @see https://url.spec.whatwg.org/#ipv4-number-parser
     *
     * @return mixed Returns null if it can not correctly convert the label
     */
    private static function labelToIpv4Number(string $part, Math $math)
    {
        foreach (self::REGEXP_IPV4_NUMBER_PER_BASE as $regexp => $base) {
            if (1 !== preg_match($regexp, $part, $matches)) {
                continue;
            }

            $number = ltrim($matches['number'], '0');
            if ('' === $number) {
                return 0;
            }

            $number = $math->baseConvert($number, $base);
            if (0 <= $math->compare($number, 0) && 0 >= $math->compare($number, self::MAX_IPV4_NUMBER)) {
                return $number;
            }
        }

        return null;
    }
}

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
use League\Uri\Exceptions\IPv4CalculatorMissing;
use League\Uri\IPv4Calculators\BCMathCalculator;
use League\Uri\IPv4Calculators\GMPCalculator;
use League\Uri\IPv4Calculators\IPv4Calculator;
use League\Uri\IPv4Calculators\NativeCalculator;
use function extension_loaded;
use function preg_match;
use function sprintf;
use function substr;
use const PHP_INT_SIZE;

final class IPv4HostNormalizer
{
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

    /**
     * Normalizes the host content to a IPv4 Host string representation if possible
     * otherwise returns the Host instance unchanged.
     *
     * @see https://url.spec.whatwg.org/#concept-ipv4-parser
     *
     * @param ?IPv4Calculator $calculator
     */
    public static function normalize(HostInterface $host, ?IPv4Calculator $calculator = null): HostInterface
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

        $ipv4host = ($calculator ?? self::calculator())->convert($hostString);
        if (null === $ipv4host) {
            return $host;
        }

        /** @var HostInterface $newHost */
        $newHost = $host->withContent($ipv4host);

        return $newHost;
    }

    /**
     * Loads a Math implementation depending on the underlying OS settings.
     *
     * @throws IPv4CalculatorMissing If no class is loaded
     */
    private static function calculator(): IPv4Calculator
    {
        static $calculator;

        if (null !== $calculator) {
            return $calculator;
        }

        if (extension_loaded('gmp')) {
            $calculator = new GMPCalculator();

            return $calculator;
        }

        if (extension_loaded('bcmath')) {
            $calculator = new BCMathCalculator();

            return $calculator;
        }

        if (4 < PHP_INT_SIZE) {
            $calculator = new NativeCalculator();

            return $calculator;
        }

        throw new IPv4CalculatorMissing(sprintf(
            'No %s was provided or detected for your platform. Please run you script on a x.64 PHP build or install the GMP or the BCMath extension to enable autodetection.',
            IPv4Calculator::class
        ));
    }
}

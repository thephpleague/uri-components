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

namespace League\Uri\IPv4Calculators;

use function bcadd;
use function bccomp;
use function bcdiv;
use function bcmod;
use function bcmul;
use function bcpow;
use function strlen;

final class BCMathCalculator extends Calculator
{
    private const SCALE = 0;

    private const CONVERSION_TABLE = [
        '0' => '0', '1' => '1', '2' => '2', '3' => '3',
        '4' => '4', '5' => '5', '6' => '6', '7' => '7',
        '8' => '8', '9' => '9', 'a' => '10', 'b' => '11',
        'c' => '12', 'd' => '13', 'e' => '14', 'f' => '15',
    ];

    /**
     * {@inheritDoc}
     */
    protected function baseConvert($var, int $base): string
    {
        $var =  (string) $var;
        if (10 === $base) {
            return $var;
        }

        $base = (string) $base;
        $decimal = '0';
        for ($i = 0, $len = strlen($var); $i < $len; $i++) {
            $decimal = bcadd(
                bcmul($decimal, $base, self::SCALE),
                self::CONVERSION_TABLE[$var[$i]],
                self::SCALE
            );
        }

        return $decimal;
    }

    /**
     * {@inheritDoc}
     */
    protected function pow($base, int $exp): string
    {
        return bcpow((string) $base, (string) $exp, self::SCALE);
    }

    /**
     * {@inheritDoc}
     */
    protected function compare($value1, $value2): int
    {
        return bccomp((string) $value1, (string) $value2, self::SCALE);
    }

    /**
     * {@inheritDoc}
     */
    protected function multiply($value1, $value2): string
    {
        return bcmul((string) $value1, (string) $value2, self::SCALE);
    }

    /**
     * {@inheritDoc}
     */
    protected function long2Ip($ipAddress): string
    {
        $output = '';
        $part = $ipAddress;
        for ($offset = 0; $offset < 4; $offset++) {
            $output = bcmod((string) $part, '256', self::SCALE).$output;
            if ($offset < 3) {
                $output = '.'.$output;
            }
            $part = bcdiv((string) $part, '256', self::SCALE);
        }

        return $output;
    }
}

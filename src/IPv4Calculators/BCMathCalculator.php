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
use function str_split;

final class BCMathCalculator implements IPv4Calculator
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
    public function baseConvert($var, int $base): string
    {
        $var = (string) $var;
        if (10 === $base) {
            return $var;
        }

        $base = (string) $base;
        $decimal = '0';
        foreach (str_split($var) as $char) {
            $decimal = bcadd($this->multiply($decimal, $base), self::CONVERSION_TABLE[$char], self::SCALE);
        }

        return $decimal;
    }

    /**
     * {@inheritDoc}
     */
    public function pow($base, int $exp): string
    {
        return bcpow((string) $base, (string) $exp, self::SCALE);
    }

    /**
     * {@inheritDoc}
     */
    public function compare($value1, $value2): int
    {
        return bccomp((string) $value1, (string) $value2, self::SCALE);
    }

    /**
     * {@inheritDoc}
     */
    public function multiply($value1, $value2): string
    {
        return bcmul((string) $value1, (string) $value2, self::SCALE);
    }

    /**
     * {@inheritDoc}
     */
    public function div($value, $base): string
    {
        return bcdiv((string) $value, (string) $base, self::SCALE);
    }

    /**
     * {@inheritDoc}
     */
    public function mod($value, $base): string
    {
        return bcmod((string) $value, (string) $base, self::SCALE);
    }

    /**
     * {@inheritDoc}
     */
    public function add($value1, $value2): string
    {
        return bcadd((string) $value1, (string) $value2, self::SCALE);
    }
}

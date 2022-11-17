<?php

/**
 * League.Uri (https://uri.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\IPv4Calculators;

use function floor;
use function intval;

final class NativeCalculator implements IPv4Calculator
{
    public function baseConvert($value, int $base): int
    {
        return intval((string) $value, $base);
    }

    public function pow($value, int $exponent)
    {
        return $value ** $exponent;
    }

    public function compare($value1, $value2): int
    {
        return $value1 <=> $value2;
    }

    public function multiply($value1, $value2): int
    {
        return $value1 * $value2;
    }

    public function div($value, $base): int
    {
        return (int) floor($value / $base);
    }

    public function mod($value, $base): int
    {
        return $value % $base;
    }

    public function add($value1, $value2): int
    {
        return $value1 + $value2;
    }

    public function sub($value1, $value2): int
    {
        return $value1 - $value2;
    }
}

<?php

/**
 * League.Uri (http://uri.thephpleague.com/components)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.2
 * @link       https://github.com/thephpleague/uri-components
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
    /**
     * {@inheritDoc}
     */
    public function baseConvert($value, int $base): int
    {
        return intval((string) $value, $base);
    }

    /**
     * {@inheritDoc}
     */
    public function pow($value, int $exponent)
    {
        return $value ** $exponent;
    }

    /**
     * {@inheritDoc}
     */
    public function compare($value1, $value2): int
    {
        return $value1 <=> $value2;
    }

    /**
     * {@inheritDoc}
     */
    public function multiply($value1, $value2): int
    {
        return $value1 * $value2;
    }

    /**
     * {@inheritDoc}
     */
    public function div($value, $base): int
    {
        return (int) floor($value / $base);
    }

    /**
     * {@inheritDoc}
     */
    public function mod($value, $base): int
    {
        return $value % $base;
    }

    /**
     * {@inheritDoc}
     */
    public function add($value1, $value2): int
    {
        return $value1 + $value2;
    }

    /**
     * {@inheritDoc}
     */
    public function sub($value1, $value2): int
    {
        return $value1 - $value2;
    }
}

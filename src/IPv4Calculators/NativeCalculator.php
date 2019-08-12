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

use function intval;
use function long2ip;

final class NativeCalculator extends Calculator
{
    /**
     * {@inheritDoc}
     */
    protected function baseConvert($var, int $base): int
    {
        return intval($var, $base);
    }

    /**
     * {@inheritDoc}
     */
    protected function pow($base, int $exp)
    {
        return pow($base, $exp);
    }

    /**
     * {@inheritDoc}
     */
    protected function compare($value1, $value2): int
    {
        return $value1 <=> $value2;
    }

    /**
     * {@inheritDoc}
     */
    protected function multiply($value1, $value2): int
    {
        return $value1 * $value2;
    }

    /**
     * {@inheritDoc}
     */
    protected function long2Ip($ipAddress): string
    {
        return long2ip($ipAddress);
    }
}

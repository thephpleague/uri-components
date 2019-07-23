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

namespace League\Uri\Maths;

use function gmp_cmp;
use function gmp_init;
use function gmp_mul;
use function gmp_pow;

final class GMPMath implements Math
{
    /**
     * {@inheritDoc}
     */
    public function pow($base, int $exp)
    {
        return gmp_pow($base, $exp);
    }

    /**
     * {@inheritDoc}
     */
    public function baseConvert($var, int $base)
    {
        return gmp_init($var, $base);
    }

    /**
     * {@inheritDoc}
     */
    public function compare($value1, $value2): int
    {
        return gmp_cmp($value1, $value2);
    }

    /**
     * {@inheritDoc}
     */
    public function multiply($value1, $value2)
    {
        return gmp_mul($value1, $value2);
    }

    /**
     * {@inheritDoc}
     */
    public function long2Ip($ipAddress): string
    {
        $output = '';
        $n = $ipAddress;
        for ($i = 0; $i < 4; $i++) {
            $output = intval($n % 256, 10).$output;
            if ($i < 3) {
                $output = '.'.$output;
            }
            $n = floor($n / 256);
        }

        return $output;
    }
}

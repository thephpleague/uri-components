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

use GMP;
use function gmp_cmp;
use function gmp_div_q;
use function gmp_init;
use function gmp_mod;
use function gmp_mul;
use function gmp_pow;
use const GMP_ROUND_MINUSINF;

final class GMPMath implements Math
{
    /**
     * {@inheritDoc}
     */
    public function pow($base, int $exp): GMP
    {
        return gmp_pow($base, $exp);
    }

    /**
     * {@inheritDoc}
     */
    public function baseConvert($var, int $base): GMP
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
    public function multiply($value1, $value2): GMP
    {
        return gmp_mul($value1, $value2);
    }

    /**
     * {@inheritDoc}
     */
    public function long2Ip($ipAddress): string
    {
        $output = '';
        $part = $ipAddress;
        for ($offset = 0; $offset < 4; $offset++) {
            $output = gmp_mod($part, 256).$output;
            if ($offset < 3) {
                $output = '.'.$output;
            }
            $part = gmp_div_q($part, 256, GMP_ROUND_MINUSINF);
        }

        return $output;
    }
}

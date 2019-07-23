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

interface Math
{
    /**
     * Returns base raised to the power of exp.
     *
     * @param mixed $base scalar, the base to use
     *
     * @return mixed base raised to the power of exp.
     */
    public function pow($base, int $exp);

    /**
     * Get the integer value of a variable.
     *
     * @param mixed $var The scalar value being converted to an integer
     *
     * @return mixed the integer value
     */
    public function baseConvert($var, int $base);

    /**
     * Number comparison.
     *
     * @param mixed $value1 the first value
     * @param mixed $value2 the second value
     *
     * @return int Returns < 0 if value1 is less than value2; > 0 if value1 is greater than value2, and 0 if they are equal.
     */
    public function compare($value1, $value2): int;

    /**
     * Multiply numbers.
     *
     * @param mixed $value1 a number that will be multiply by $value2
     * @param mixed $value2 a number that will be multiply by $value1
     *
     * @return mixed the multiplication result
     */
    public function multiply($value1, $value2);

    /**
     * @param mixed $ipAddress the number representation of the IPV4address
     *
     * @return string the string representation of the IPV4address
     */
    public function long2Ip($ipAddress): string;
}

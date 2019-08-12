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

interface IPv4Calculator
{
    /**
     * Get the decimal integer value of a variable.
     *
     * @param mixed $var The scalar value being converted to an integer
     *
     * @return mixed the integer value
     */
    public function baseConvert($var, int $base);

    /**
     * Returns base raised to the power of exp.
     *
     * @param mixed $base scalar, the base to use
     *
     * @return mixed base raised to the power of exp.
     */
    public function pow($base, int $exp);

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
     * Returns the int point remainder (modulo) of the division of the arguments.
     *
     * @param mixed $value The dividend
     * @param mixed $base  The divisor
     *
     * @return mixed The remainder
     */
    public function mod($value, $base);

    /**
     * Divide numbers.
     *
     * @param mixed $value The number being divided.
     * @param mixed $base  The number that $value is being divided by.
     *
     * @return mixed The result of the division
     */
    public function div($value, $base);
}

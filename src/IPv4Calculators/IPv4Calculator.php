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

interface IPv4Calculator
{
    /**
     * Add numbers.
     *
     * @param mixed $value1 a number that will be added to $value2
     * @param mixed $value2 a number that will be added to $value1
     *
     * @return mixed the addition result
     */
    public function add($value1, $value2);

    /**
     * Subtract one number from another.
     *
     * @param mixed $value1 a number that will be substracted of $value2
     * @param mixed $value2 a number that will be substracted to $value1
     *
     * @return mixed the subtraction result
     */
    public function sub($value1, $value2);

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
     * Divide numbers.
     *
     * @param mixed $value The number being divided.
     * @param mixed $base  The number that $value is being divided by.
     *
     * @return mixed the result of the division
     */
    public function div($value, $base);

    /**
     * Raise an number to the power of exponent.
     *
     * @param mixed $value scalar, the base to use
     *
     * @return mixed the value raised to the power of exp.
     */
    public function pow($value, int $exponent);

    /**
     * Returns the int point remainder (modulo) of the division of the arguments.
     *
     * @param mixed $value The dividend
     * @param mixed $base  The divisor
     *
     * @return mixed the remainder
     */
    public function mod($value, $base);

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
     * Get the decimal integer value of a variable.
     *
     * @param mixed $value The scalar value being converted to an integer
     *
     * @return mixed the integer value
     */
    public function baseConvert($value, int $base);
}

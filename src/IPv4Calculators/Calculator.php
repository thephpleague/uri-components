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

use function array_pop;
use function count;
use function explode;
use function ltrim;
use function preg_match;

abstract class Calculator implements IPv4Calculator
{
    private const MAX_IPV4_NUMBER = 4294967295;

    private const REGEXP_IPV4_NUMBER_PER_BASE = [
        '/^0x(?<number>[[:xdigit:]]*)$/' => 16,
        '/^0(?<number>[0-7]*)$/' => 8,
        '/^(?<number>\d+)$/' => 10,
    ];

    /**
     * {@inheritDoc}
     */
    public function convert(string $hostString): ?string
    {
        $numbers = [];
        foreach (explode('.', $hostString) as $label) {
            $number = $this->labelToNumber($label);
            if (null === $number) {
                return null;
            }

            $numbers[] = $number;
        }

        $ipv4 = array_pop($numbers);
        $max = $this->pow(256, 6 - count($numbers));
        if ($this->compare($ipv4, $max) > 0) {
            return null;
        }

        foreach ($numbers as $offset => $number) {
            if ($this->compare($number, 255) > 0) {
                return null;
            }
            $ipv4 += $this->multiply($number, $this->pow(256, 3 - $offset));
        }

        return $this->long2Ip($ipv4);
    }

    /**
     * {@inheritDoc}
     */
    public function labelToNumber(string $label)
    {
        foreach (self::REGEXP_IPV4_NUMBER_PER_BASE as $regexp => $base) {
            if (1 !== preg_match($regexp, $label, $matches)) {
                continue;
            }

            $number = ltrim($matches['number'], '0');
            if ('' === $number) {
                return 0;
            }

            $number = $this->baseConvert($number, $base);
            if (0 <= $this->compare($number, 0) && 0 >= $this->compare($number, self::MAX_IPV4_NUMBER)) {
                return $number;
            }
        }

        return null;
    }

    /**
     * Get the decimal integer value of a variable.
     *
     * @param mixed $var The scalar value being converted to an integer
     *
     * @return mixed the integer value
     */
    abstract protected function baseConvert($var, int $base);

    /**
     * Returns base raised to the power of exp.
     *
     * @param mixed $base scalar, the base to use
     *
     * @return mixed base raised to the power of exp.
     */
    abstract protected function pow($base, int $exp);

    /**
     * Number comparison.
     *
     * @param mixed $value1 the first value
     * @param mixed $value2 the second value
     *
     * @return int Returns < 0 if value1 is less than value2; > 0 if value1 is greater than value2, and 0 if they are equal.
     */
    abstract protected function compare($value1, $value2): int;

    /**
     * Multiply numbers.
     *
     * @param mixed $value1 a number that will be multiply by $value2
     * @param mixed $value2 a number that will be multiply by $value1
     *
     * @return mixed the multiplication result
     */
    abstract protected function multiply($value1, $value2);

    /**
     * @param mixed $ipAddress the number representation of the IPV4address
     *
     * @return string the string representation of the IPV4address
     */
    abstract protected function long2Ip($ipAddress): string;
}

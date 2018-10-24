<?php

/**
 * League.Uri (https://uri.thephpleague.com/components/).
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.8.2
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\Components;

use InvalidArgumentException;

/**
 * Base Exception class for League Uri Schemes.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
class Exception extends InvalidArgumentException
{
    public static function fromInvalidIterable($str)
    {
        return new self(sprintf(
            'Expected data to be an iterable; received "%s"',
            (is_object($str) ? get_class($str) : gettype($str))
        ));
    }

    public static function fromInaccessibleProperty($property)
    {
        return new self(sprintf('"%s" is an undefined or inaccessible property', $property));
    }

    public static function fromInvalidFlag($flag)
    {
        return new self(sprintf('"%s" is an invalid flag', $flag));
    }
}

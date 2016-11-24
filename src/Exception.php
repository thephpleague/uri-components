<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @copyright  2016 Ignace Nyamagana Butera
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.0.0
 * @link       https://github.com/thephpleague/uri-components
 */
namespace League\Uri\Components;

use InvalidArgumentException;

/**
 * Base Exception class for League Uri Schemes
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
class Exception extends InvalidArgumentException
{
    public static function fromInvalidString($str)
    {
        return new self(sprintf(
            'Expected data to be a string; received "%s"',
            (is_object($str) ? get_class($str) : gettype($str))
        ));
    }

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

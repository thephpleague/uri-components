<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Interfaces
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @copyright  2016 Ignace Nyamagana Butera
 * @license    https://github.com/thephpleague/uri-interfaces/blob/master/LICENSE (MIT License)
 * @version    1.0.0
 * @link       https://github.com/thephpleague/uri-interfaces/
 */
declare(strict_types=1);

namespace League\Uri\Components;

/**
 * Defines constants for common encoding algorithm.
 *
 * @see https://tools.ietf.org/html/rfc1738
 * @see https://tools.ietf.org/html/rfc3986
 * @see https://tools.ietf.org/html/rfc3987
 *
 * Usage:
 *
 * <code>
 * class Component implements EncodingInterface
 * {
 *     public function getContent($enc_type = self::RFC3986_ENCODING)
 *     {
 *     }
 * }
 * </code>
 *
 *
 * @package    League\Uri
 * @subpackage League\Uri\Interfaces
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
interface EncodingInterface
{
    const NO_ENCODING = 0;

    const RFC1738_ENCODING = 1;

    const RFC3986_ENCODING = 2;

    const RFC3987_ENCODING = 3;
}

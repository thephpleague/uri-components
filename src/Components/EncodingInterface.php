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

namespace League\Uri\Components;

/**
 * Defines constants for common URI encoding type.
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
 *     public function getContent(int $enc_type = self::RFC3986_ENCODING)
 *     {
 *     }
 * }
 * </code>
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
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

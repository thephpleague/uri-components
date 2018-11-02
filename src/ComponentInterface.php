<?php

/**
 * League.Uri (http://uri.thephpleague.com/components).
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.0
 * @link       https://github.com/thephpleague/uri-schemes
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Value object representing a URI component.
 *
 * Instances of this interface are considered immutable; all methods that
 * might change state MUST be implemented such that they retain the internal
 * state of the current instance and return an instance that contains the
 * changed state.
 *
 * @see        https://tools.ietf.org/html/rfc3986#section-3
 * @package    League\Uri
 * @subpackage League\Uri\Component
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
interface ComponentInterface extends JsonSerializable
{
    /**
     * Returns the instance string representation.
     *
     * If the instance is defined, the value returned MUST be percent-encoded,
     * but MUST NOT double-encode any characters. To determine what characters
     * to encode, please refer to RFC 3986, Sections 2 and 3.
     *
     * If the instance is not defined an empty string is returned
     */
    public function __toString(): string;

    /**
     * Returns the instance json representation.
     *
     * If the instance is defined, the value returned MUST be percent-encoded,
     * but MUST NOT double-encode any characters. To determine what characters
     * to encode, please refer to RFC 3986 or RFC 1738.
     *
     * If the instance is not defined null is returned
     */
    public function jsonSerialize(): ?string;

    /**
     * Returns the instance content.
     *
     * If the instance is defined, the value returned MUST be encoded according to the
     * selected encoding algorithm. In any case, the value MUST NOT double-encode any character
     * depending on the selected encoding algorithm.
     *
     * To determine what characters to encode, please refer to RFC 3986, Sections 2 and 3.
     * or RFC 3987 Section 3. By default the content is encoded according to RFC3986
     *
     * If the instance is not defined null is returned
     */
    public function getContent(): ?string;

    /**
     * Returns an instance with the specified content.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified content.
     *
     * Users can provide both encoded and decoded content characters.
     *
     * A null value is equivalent to removing the component content.
     *
     *
     * @throws InvalidArgumentException for invalid component or transformations
     *                                  that would result in a object in invalid state.
     *
     * @return static
     */
    public function withContent($content);
}

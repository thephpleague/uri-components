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

/**
 * Value object representing a URI Fragment component.
 *
 * Instances of this interface are considered immutable; all methods that
 * might change state MUST be implemented such that they retain the internal
 * state of the current instance and return an instance that contains the
 * changed state.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 * @see        https://tools.ietf.org/html/rfc3986#section-3.5
 */
class Fragment extends AbstractComponent
{

    /**
     * {@inheritdoc}
     */
    public function getContent(int $enc_type = self::RFC3986_ENCODING)
    {
        $this->assertValidEncoding($enc_type);

        if ('' == $this->data || self::NO_ENCODING == $enc_type) {
            return $this->data;
        }

        if (self::RFC3987_ENCODING == $enc_type) {
            $pattern = str_split(self::$invalid_uri_chars);

            return str_replace($pattern, array_map('rawurlencode', $pattern), $this->data);
        }

        $regexp = '/(?:[^'.self::$unreserved_chars.self::$subdelim_chars.'\:\/@\?]+|%(?!'.self::$encoded_chars.'))/ux';

        $content = $this->encode($this->data, $regexp);
        if (self::RFC1738_ENCODING == $enc_type) {
            return $this->toRFC1738($content);
        }

        return $content;
    }

    /**
     * {@inheritdoc}
     */
    public function getUriComponent(): string
    {
        $component = $this->__toString();
        if (null !== $this->data) {
            return '#'.$component;
        }

        return $component;
    }
}

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

/**
 * Value object representing a URI Scheme component.
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
 * @see        https://tools.ietf.org/html/rfc3986#section-3.1
 */
class Scheme extends AbstractComponent
{
    /**
     * @inheritdoc
     */
    protected function validate($scheme)
    {
        if (null === $scheme) {
            return $scheme;
        }

        $scheme = $this->validateString($scheme);
        if (!preg_match(',^[a-z]([-a-z0-9+.]+)?$,i', $scheme)) {
            throw new Exception(sprintf("Invalid Submitted scheme: '%s'", $scheme));
        }

        return strtolower($scheme);
    }

    /**
     * Returns the instance string representation
     * with its optional URI delimiters
     *
     * @return string
     */
    public function getUriComponent()
    {
        $component = $this->__toString();
        if ('' !== $component) {
            return $component.':';
        }

        return $component;
    }
}

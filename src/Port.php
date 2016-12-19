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
 * Value object representing a URI Port component.
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
 * @see        https://tools.ietf.org/html/rfc3986#section-3.2.3
 */
class Port extends AbstractComponent
{
    /**
     * Validate the component content
     *
     * @param mixed $data
     *
     * @throws Exception if the component is no valid
     *
     * @return mixed
     */
    protected function validate($data)
    {
        if (null === $data) {
            return null;
        }

        if (!is_int($data) || $data < 1 || $data > 65535) {
            throw new Exception(sprintf('Expected port to be a int or null; received %s', gettype($data)));
        }

        return $data;
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
            return ':'.$component;
        }

        return $component;
    }
}

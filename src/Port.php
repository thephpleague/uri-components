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
use League\Uri\Interfaces\Component as UriComponent;

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
class Port extends Component implements UriComponent
{
    /**
     * @inheritdoc
     */
    protected function validate($data)
    {
        if ('' === $data) {
            throw new InvalidArgumentException('Expected port to be a int or null; received an empty string');
        }

        return $this->validatePort($data);
    }

    /**
     * Validate a Port number
     *
     * @param mixed $port the port number
     *
     * @throws InvalidArgumentException If the port number is invalid
     *
     * @return null|int
     */
    protected function validatePort($port)
    {
        if (is_bool($port)) {
            throw new InvalidArgumentException('The submitted port is invalid');
        }

        if (in_array($port, [null, ''])) {
            return null;
        }
        $res = filter_var($port, FILTER_VALIDATE_INT, ['options' => [
            'min_range' => 1,
            'max_range' => 65535,
        ]]);
        if (false === $res) {
            throw new InvalidArgumentException('The submitted port is invalid');
        }

        return $res;
    }

    /**
     * @inheritdoc
     */
    public function __debugInfo()
    {
        return ['port' => $this->getContent()];
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

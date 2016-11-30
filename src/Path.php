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

use League\Uri\Components\Traits\PathInfo;
use League\Uri\Interfaces\PathComponent;

/**
 * Value object representing a URI path component.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
class Path extends Component implements PathComponent
{
    use PathInfo;

    /**
     * new instance
     *
     * @param string|null $path the component value
     */
    public function __construct($path = null)
    {
        if (null === $path) {
            $path = '';
        }

        $this->data = $this->validate($this->validateString($path));
    }

    /**
     * @inheritdoc
     */
    protected function validate($data)
    {
        return $this->decodePath($this->validateString($data));
    }

    /**
     * Return the decoded string representation of the component
     *
     * @return string
     */
    public function getDecoded()
    {
        return $this->data;
    }
}

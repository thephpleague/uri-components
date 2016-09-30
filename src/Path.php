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
     * @inheritdoc
     */
    protected function validate($data)
    {
        return $this->decodePath($this->validateString($data));
    }

    /**
     * @inheritdoc
     */
    public function getContent()
    {
        return $this->encodePath($this->data);
    }
}

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
 * Value object representing a URI path component.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
class Path extends AbstractComponent
{
    use PathInfoTrait;

    /**
     * new instance.
     *
     * @param string|null $path the component value
     */
    public function __construct(string $path = null)
    {
        if (null === $path) {
            $path = '';
        }

        parent::__construct($path);
    }

    /**
     * Validate the component content.
     *
     *
     * @throws Exception if the component is no valid
     *
     */
    protected function validate($data)
    {
        return $this->decodePath($this->validateString($data));
    }

    /**
     * Return the decoded string representation of the component.
     *
     */
    protected function getDecoded(): string
    {
        return $this->data;
    }
}

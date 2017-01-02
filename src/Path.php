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
class Path extends AbstractComponent implements PathInterface
{
    use PathInfo;

    /**
     * new instance
     *
     * @param string|null $path the component value
     */
    public function __construct(string $path = null)
    {
        if (null === $path) {
            $path = '';
        }

        $this->data = $this->validate($this->validateString($path));
    }

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
        return $this->decodePath($this->filterEncodedPath($this->validateString($data)));
    }

    /**
     * Return the decoded string representation of the component
     *
     * @return string
     */
    protected function getDecoded(): string
    {
        return $this->data;
    }
}

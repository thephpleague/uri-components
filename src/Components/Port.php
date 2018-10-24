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
     * new instance.
     *
     * @param int|null $data the component value
     */
    public function __construct(int $data = null)
    {
        if (null !== $data) {
            $data = (string) $data;
        }

        parent::__construct($data);
    }

    /**
     * {@inheritdoc}
     */
    protected function validate($data)
    {
        if (null === $data) {
            return null;
        }

        $data = filter_var($data, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if (!$data) {
            throw new Exception(sprintf('Expected port to be a int or null; received %s', gettype($data)));
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getUriComponent(): string
    {
        $component = $this->__toString();
        if ('' !== $component) {
            return ':'.$component;
        }

        return $component;
    }
}

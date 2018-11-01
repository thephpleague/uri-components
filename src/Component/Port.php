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

namespace League\Uri\Component;

use League\Uri\Exception\MalformedUriComponent;
use function filter_var;
use function sprintf;
use const FILTER_VALIDATE_INT;

final class Port extends Component
{
    /**
     * @var int|null
     */
    private $component;

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties)
    {
        return new self($properties['component']);
    }

    /**
     * New instance.
     *
     * @param null|mixed $port
     */
    public function __construct($port = null)
    {
        $this->component = $this->validate($port);
    }

    /**
     * Validate a port.
     *
     * @throws MalformedUriComponent if the port is invalid
     */
    protected function validate($port): ?int
    {
        $port = $this->filterComponent($port);
        if (null === $port) {
            return null;
        }

        $fport = filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if (false !== $fport) {
            return $fport;
        }

        throw new MalformedUriComponent(sprintf('Expected port to be a positive integer or 0; received %s', $port));
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): ?string
    {
        if (null === $this->component) {
            return $this->component;
        }

        return (string) $this->component;
    }

    /**
     * Returns the integer representation of the Port.
     */
    public function toInt(): ?int
    {
        return $this->component;
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($content): self
    {
        $content = $this->validate($this->filterComponent($content));
        if ($content === $this->component) {
            return $this;
        }

        return new self($content);
    }
}

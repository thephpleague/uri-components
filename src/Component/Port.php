<?php

/**
 * League.Uri (http://uri.thephpleague.com/components)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.0
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\Component;

use League\Uri\Contract\PortInterface;
use League\Uri\Contract\UriComponentInterface;
use League\Uri\Exception\SyntaxError;
use function filter_var;
use function sprintf;
use const FILTER_VALIDATE_INT;

final class Port extends Component implements PortInterface
{
    /**
     * @var int|null
     */
    private $port;

    /**
     * {@inheritDoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['port']);
    }

    /**
     * New instance.
     *
     * @param mixed|null $port
     */
    public function __construct($port = null)
    {
        $this->port = $this->validate($port);
    }

    /**
     * Validate a port.
     *
     * @param mixed|null $port
     *
     * @throws SyntaxError if the port is invalid
     */
    private function validate($port): ?int
    {
        $port = self::filterComponent($port);
        if (null === $port) {
            return null;
        }

        $fport = filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if (false !== $fport) {
            return $fport;
        }

        throw new SyntaxError(sprintf('Expected port to be a positive integer or 0; received %s', $port));
    }

    /**
     * {@inheritDoc}
     */
    public function getContent(): ?string
    {
        if (null === $this->port) {
            return $this->port;
        }

        return (string) $this->port;
    }

    /**
     * {@inheritDoc}
     */
    public function getUriComponent(): string
    {
        return (null === $this->port ? '' : ':').$this->getContent();
    }

    /**
     * {@inheritDoc}
     */
    public function toInt(): ?int
    {
        return $this->port;
    }

    /**
     * {@inheritDoc}
     */
    public function withContent($content): UriComponentInterface
    {
        $content = $this->validate(self::filterComponent($content));
        if ($content === $this->port) {
            return $this;
        }

        return new self($content);
    }
}

<?php

/**
 * League.Uri (https://uri.thephpleague.com/components/2.0/)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\Components;

use League\Uri\Contracts\AuthorityInterface;
use League\Uri\Contracts\PortInterface;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use function filter_var;
use function sprintf;
use const FILTER_VALIDATE_INT;

final class Port extends Component implements PortInterface
{
    private readonly ?int $port;

    /**
     * New instance.
     */
    public function __construct(UriComponentInterface|Stringable|float|int|string|bool|null $port = null)
    {
        $this->port = $this->validate($port);
    }

    public static function fromInt(int $port): self
    {
        if (0 > $port) {
            throw new SyntaxError(sprintf('Expected port to be a positive integer or 0; received %s.', $port));
        }

        return new self($port);
    }

    /**
     * Validate a port.
     *
     * @throws SyntaxError if the port is invalid
     */
    private function validate(UriComponentInterface|Stringable|float|int|string|bool|null $port): ?int
    {
        $port = self::filterComponent($port);
        if (null === $port) {
            return null;
        }

        $fport = filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if (false !== $fport) {
            return $fport;
        }

        throw new SyntaxError(sprintf('Expected port to be a positive integer or 0; received %s.', $port));
    }

    public static function __set_state(array $properties): self
    {
        return new self($properties['port']);
    }

    /**
     * Create a new instance from a URI object.
     */
    public static function createFromUri(Psr7UriInterface|UriInterface $uri): self
    {
        return new self($uri->getPort());
    }

    /**
     * Create a new instance from an Authority object.
     */
    public static function createFromAuthority(AuthorityInterface $authority): self
    {
        return new self($authority->getPort());
    }

    public function value(): ?string
    {
        if (null === $this->port) {
            return $this->port;
        }

        return (string) $this->port;
    }

    public function getUriComponent(): string
    {
        return (null === $this->port ? '' : ':').$this->value();
    }

    public function toInt(): ?int
    {
        return $this->port;
    }
}

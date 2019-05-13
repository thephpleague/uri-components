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

use League\Uri\Contract\AuthorityInterface;
use League\Uri\Contract\HostInterface;
use League\Uri\Contract\UriComponentInterface;
use League\Uri\Exception\SyntaxError;
use function explode;
use function preg_match;

final class Authority extends Component implements AuthorityInterface
{
    private const REGEXP_HOST_PORT = ',^(?<host>\[.*\]|[^:]*)(:(?<port>.*))?$,';

    /**
     * @var UserInfo
     */
    private $userInfo;

    /**
     * @var HostInterface
     */
    private $host;

    /**
     * @var Port
     */
    private $port;

    /**
     * {@inheritDoc}
     */
    public static function __set_state(array $properties): self
    {
        $auth = new self();
        $auth->host = $properties['host'];
        $auth->port = $properties['port'];
        $auth->userInfo = $properties['userInfo'];
        $auth->validate();

        return $auth;
    }

    /**
     * New instance.
     *
     * @param mixed|null $authority
     *
     * @throws SyntaxError If the component contains invalid HostInterface part.
     */
    public function __construct($authority = null)
    {
        $components = $this->parse(self::filterComponent($authority));
        $this->host = new Host($components['host']);
        $this->port = new Port($components['port']);
        $this->userInfo = new UserInfo($components['user'], $components['pass']);
        $this->validate();
    }

    /**
     * Check the authority validity against RFC3986 rules.
     *
     * @throws SyntaxError if the host is the only null component.
     */
    private function validate(): void
    {
        if (null === $this->host->getContent() && null !== $this->getContent()) {
            throw new SyntaxError('A non-empty authority must contains a non null host.');
        }
    }

    /**
     * Extracts the authority parts from a given string.
     *
     * @param ?string $authority
     */
    private function parse(?string $authority): array
    {
        $components = ['user' => null, 'pass' => null, 'host' => null, 'port' => null];
        if (null === $authority) {
            return $components;
        }

        if ('' === $authority) {
            $components['host'] = '';

            return $components;
        }

        $parts = explode('@', $authority, 2);
        if (isset($parts[1])) {
            [$components['user'], $components['pass']] = explode(':', $parts[0], 2) + [1 => null];
        }
        preg_match(self::REGEXP_HOST_PORT, $parts[1] ?? $parts[0], $matches);
        $matches += ['port' => ''];
        $components['host'] = $matches['host'];
        $components['port'] = '' === $matches['port'] ? null : $matches['port'];

        return $components;
    }

    /**
     * {@inheritDoc}
     */
    public function getContent(): ?string
    {
        $auth = $this->host->getContent();
        $port = $this->port->getContent();
        if (null !== $port) {
            $auth .= ':'.$port;
        }

        $userInfo = $this->userInfo->getContent();
        if (null === $userInfo) {
            return $auth;
        }

        return $userInfo.'@'.$auth;
    }

    /**
     * {@inheritDoc}
     */
    public function getUriComponent(): string
    {
        return (null === $this->host->getContent() ? '' : '//').$this->getContent();
    }

    /**
     * {@inheritDoc}
     */
    public function getHost(): ?string
    {
        return $this->host->getContent();
    }

    /**
     * {@inheritDoc}
     */
    public function getPort(): ?int
    {
        return $this->port->toInt();
    }

    /**
     * {@inheritDoc}
     */
    public function getUserInfo(): ?string
    {
        return $this->userInfo->getContent();
    }

    /**
     * {@inheritDoc}
     *
     * @return static
     */
    public function withContent($content): UriComponentInterface
    {
        $content = self::filterComponent($content);
        if ($content === $this->getContent()) {
            return $this;
        }

        return new self($content);
    }

    /**
     * @inheritDoc
     *
     * @param mixed|null $host
     */
    public function withHost($host): AuthorityInterface
    {
        if (!$host instanceof HostInterface) {
            $host = new Host($host);
        }

        if ($host->getContent() === $this->host->getContent()) {
            return $this;
        }

        $clone = clone $this;
        $clone->host = $host;
        $clone->validate();

        return $clone;
    }

    /**
     * @inheritDoc
     *
     * @param mixed|null $port
     */
    public function withPort($port): AuthorityInterface
    {
        if (!$port instanceof Port) {
            $port = new Port($port);
        }

        if ($port->getContent() === $this->port->getContent()) {
            return $this;
        }

        $clone = clone $this;
        $clone->port = $port;
        $clone->validate();

        return $clone;
    }

    /**
     * @inheritDoc
     *
     * @param mixed|null $user
     * @param mixed|null $pass
     */
    public function withUserInfo($user, $pass = null): AuthorityInterface
    {
        $userInfo = new UserInfo($user, $pass);
        if ($userInfo->getContent() === $this->userInfo->getContent()) {
            return $this;
        }

        $clone = clone $this;
        $clone->userInfo = $userInfo;
        $clone->validate();

        return $clone;
    }
}

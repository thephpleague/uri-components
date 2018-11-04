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
use League\Uri\HostInterface;
use function explode;
use function preg_match;

final class Authority extends Component
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
     * {@inheritdoc}
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
     * @param null|mixed $authority
     *
     * @throws MalformedUriComponent If the component contains invalid HostInterface part.
     */
    public function __construct($authority = null)
    {
        $components = $this->parse($this->filterComponent($authority));
        $this->host = new Host($components['host']);
        $this->port = new Port($components['port']);
        $this->userInfo = new UserInfo($components['user'], $components['pass']);
        $this->validate();
    }

    /**
     * Check the authority validity against RFC3986 rules.
     *
     * @throws MalformedUriComponent if the host is the only null component.
     */
    private function validate()
    {
        if (null === $this->host->getContent() && null !== $this->getContent()) {
            throw new MalformedUriComponent('A non-empty authority must contains a non null host.');
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getUriComponent(): string
    {
        $str = $this->__toString();
        if (null === $this->host->getContent()) {
            return $str;
        }

        return '//'.$str;
    }

    /**
     * Retrieve the user component of the URI User Info part.
     */
    public function getHost(): ?string
    {
        return $this->host->getContent();
    }

    /**
     * Retrieve the pass component of the URI User Info part.
     */
    public function getPort(): ?int
    {
        return $this->port->toInt();
    }

    /**
     * Retrieve the pass component of the URI User Info part.
     */
    public function getUserInfo(): ?string
    {
        return $this->userInfo->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($content): self
    {
        $content = $this->filterComponent($content);
        if ($content === $this->getContent()) {
            return $this;
        }

        return new self($content);
    }

    /**
     * Return an instance with the specified host.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified host.
     *
     * A null host value is equivalent to removing the host.
     *
     * @param null|mixed $host
     */
    public function withHost($host): self
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
     * Return an instance with the specified port.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified port.
     *
     * A null port value is equivalent to removing the port.
     *
     * @param null|mixed $port
     */
    public function withPort($port): self
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
     * Return an instance with the specified user information.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified user information.
     *
     * Password is optional, but the user information MUST include the
     * user; a null string for the user is equivalent to removing user
     * information.
     *
     * @param null|mixed $pass
     */
    public function withUserInfo($user, $pass = null): self
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

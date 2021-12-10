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
use League\Uri\Contracts\HostInterface;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use TypeError;
use function explode;
use function gettype;
use function is_object;
use function is_string;
use function method_exists;
use function preg_match;
use function sprintf;

final class Authority extends Component implements AuthorityInterface
{
    private const REGEXP_HOST_PORT = ',^(?<host>\[.*\]|[^:]*)(:(?<port>.*))?$,';

    private UserInfo $userInfo;
    private HostInterface $host;
    private Port $port;

    /**
     * @deprecated since version 2.3.0 use a more appropriate named constructor.
     *
     * New instance.
     *
     * @param object|float|int|string|bool|null $authority
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
     * Create a new instance from a URI object.
     *
     * @param mixed $uri an URI object
     *
     * @throws TypeError If the URI object is not supported
     */
    public static function createFromUri($uri): self
    {
        if ($uri instanceof UriInterface) {
            return new self($uri->getAuthority());
        }

        if (!$uri instanceof Psr7UriInterface) {
            throw new TypeError(sprintf('The object must implement the `%s` or the `%s` interface.', Psr7UriInterface::class, UriInterface::class));
        }

        $authority = $uri->getAuthority();
        if ('' === $authority) {
            return new self();
        }

        return new self($authority);
    }

    /**
     * Create a new instance from null.
     */
    public static function createFromNull(): self
    {
        return new self();
    }

    /**
     * Returns a new instance from an string or a stringable object.
     *
     * @param object|string $authority
     */
    public static function createFromString($authority = ''): self
    {
        if (is_object($authority) && method_exists($authority, '__toString')) {
            $authority = (string) $authority;
        }

        if (!is_string($authority)) {
            throw new TypeError(sprintf('The authority must be a string or a stringable object value, `%s` given', gettype($authority)));
        }

        return new self($authority);
    }

    /**
     * Create a new instance from a hash of parse_url parts.
     *
     * Create an new instance from a hash representation of the URI similar
     * to PHP parse_url function result
     *
     * @param array<string,null|int|string> $components
     */
    public static function createFromComponents(array $components): self
    {
        $components += ['user' => null, 'pass' => null, 'host' => null, 'port' => null];

        $authority = $components['host'];
        if (null !== $components['port']) {
            $authority .= ':'.$components['port'];
        }

        $userInfo = null;
        if (null !== $components['user']) {
            $userInfo = $components['user'];
            if (null !== $components['pass']) {
                $userInfo .= ':'.$components['pass'];
            }
        }

        if (null !== $userInfo) {
            $authority = $userInfo.'@'.$authority;
        }

        return new self($authority);
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
     * @param UriComponentInterface|object|float|int|string|bool|null $content
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
     * @param UriComponentInterface|object|float|int|string|bool|null $host
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
     * @param UriComponentInterface|object|float|int|string|bool|null $port
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
     * @param object|float|int|string|bool|null $user
     * @param object|float|int|string|bool|null $pass
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

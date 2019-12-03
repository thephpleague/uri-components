<?php

/**
 * League.Uri (http://uri.thephpleague.com/components)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.2
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\Components;

use League\Uri\Contracts\FragmentInterface;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriInterface;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use TypeError;
use function sprintf;

final class Fragment extends Component implements FragmentInterface
{
    private const REGEXP_FRAGMENT_ENCODING = '/
        (?:[^A-Za-z0-9_\-\.~\!\$&\'\(\)\*\+,;\=%\:\/@\?]+|
        %(?![A-Fa-f0-9]{2}))
    /x';

    /**
     * @var string|null
     */
    private $fragment;

    /**
     * New instance.
     *
     * @param mixed|null $fragment
     */
    public function __construct($fragment = null)
    {
        $this->fragment = $this->validateComponent($fragment);
    }

    /**
     * {@inheritDoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['fragment']);
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
            return new self($uri->getFragment());
        }

        if (!$uri instanceof Psr7UriInterface) {
            throw new TypeError(sprintf('The object must implement the `%s` or the `%s` interface.', Psr7UriInterface::class, UriInterface::class));
        }

        $component = $uri->getFragment();
        if ('' === $component) {
            return new self();
        }

        return new self($component);
    }

    /**
     * {@inheritDoc}
     */
    public function getContent(): ?string
    {
        return $this->encodeComponent($this->fragment, self::REGEXP_FRAGMENT_ENCODING);
    }

    /**
     * {@inheritDoc}
     */
    public function getUriComponent(): string
    {
        return (null === $this->fragment ? '' : '#').$this->getContent();
    }

    /**
     * Returns the decoded fragment.
     */
    public function decoded(): ?string
    {
        return $this->fragment;
    }

    /**
     * {@inheritDoc}
     */
    public function withContent($content): UriComponentInterface
    {
        $content = self::filterComponent($content);
        if ($content === $this->getContent()) {
            return $this;
        }

        return new self($content);
    }
}

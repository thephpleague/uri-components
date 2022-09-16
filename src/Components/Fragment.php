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

use League\Uri\Contracts\FragmentInterface;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Contracts\UriInterface;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;

final class Fragment extends Component implements FragmentInterface
{
    private const REGEXP_FRAGMENT_ENCODING = '/
        (?:[^A-Za-z0-9_\-\.~\!\$&\'\(\)\*\+,;\=%\:\/@\?]+|
        %(?![A-Fa-f0-9]{2}))
    /x';

    private readonly ?string $fragment;

    /**
     * New instance.
     */
    public function __construct(Stringable|float|int|string|bool|null $fragment = null)
    {
        $this->fragment = $this->validateComponent($fragment);
    }

    public static function __set_state(array $properties): self
    {
        return new self($properties['fragment']);
    }

    /**
     * Create a new instance from a URI object.
     */
    public static function createFromUri(Psr7UriInterface|UriInterface $uri): self
    {
        if ($uri instanceof UriInterface) {
            return new self($uri->getFragment());
        }

        $component = $uri->getFragment();
        if ('' === $component) {
            return new self();
        }

        return new self($component);
    }

    public function getContent(): ?string
    {
        return $this->encodeComponent($this->fragment, self::REGEXP_FRAGMENT_ENCODING);
    }

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

    public function withContent($content): UriComponentInterface
    {
        $content = self::filterComponent($content);
        if ($content === $this->getContent()) {
            return $this;
        }

        return new self($content);
    }
}

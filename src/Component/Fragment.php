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

use League\Uri\Contract\FragmentInterface;
use League\Uri\Contract\UriComponentInterface;

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
     * {@inheritDoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['fragment']);
    }

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
    public function getContent(): ?string
    {
        return $this->encodeComponent($this->fragment, self::RFC3986_ENCODING, self::REGEXP_FRAGMENT_ENCODING);
    }

    /**
     * {@inheritDoc}
     */
    public function getUriComponent(): string
    {
        return (null === $this->fragment ? '' : '#').$this->getContent();
    }

    /**
     * Returns the decoded query.
     */
    public function decoded(): ?string
    {
        return $this->encodeComponent($this->fragment, self::NO_ENCODING, self::REGEXP_FRAGMENT_ENCODING);
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

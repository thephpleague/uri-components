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

use League\Uri\Contract\UriComponentInterface;
use League\Uri\Exception\SyntaxError;
use TypeError;
use function preg_match;
use function sprintf;
use function strtolower;

final class Scheme extends Component
{
    private const REGEXP_SCHEME = ',^[a-z]([-a-z0-9+.]+)?$,i';

    /**
     * @var string|null
     */
    private $scheme;

    /**
     * New instance.
     *
     * @param mixed|null $scheme
     */
    public function __construct($scheme = null)
    {
        $this->scheme = $this->validate($scheme);
    }

    /**
     * Validate a scheme.
     *
     * @param mixed|null $scheme
     *
     * @throws SyntaxError if the scheme is invalid
     * @throws TypeError   if the scheme type is not supported
     */
    private function validate($scheme): ?string
    {
        $scheme = self::filterComponent($scheme);
        if (null === $scheme) {
            return $scheme;
        }

        if (1 === preg_match(self::REGEXP_SCHEME, $scheme)) {
            return strtolower($scheme);
        }

        throw new SyntaxError(sprintf("The scheme '%s' is invalid", $scheme));
    }

    /**
     * {@inheritDoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['scheme']);
    }

    /**
     * {@inheritDoc}
     */
    public function getContent(): ?string
    {
        return $this->scheme;
    }

    /**
     * {@inheritDoc}
     */
    public function getUriComponent(): string
    {
        return $this->getContent().(null === $this->scheme ? '' : ':');
    }

    /**
     * {@inheritDoc}
     */
    public function withContent($content): UriComponentInterface
    {
        $content = $this->validate(self::filterComponent($content));
        if ($content === $this->scheme) {
            return $this;
        }

        return new self($content);
    }
}

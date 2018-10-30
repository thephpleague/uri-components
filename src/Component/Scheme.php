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
use function preg_match;
use function sprintf;
use function strtolower;

final class Scheme extends Component
{
    /**
     * @internal
     */
    const REGEXP_SCHEME = ',^[a-z]([-a-z0-9+.]+)?$,i';

    /**
     * @var string|null
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
     * @param null|mixed $scheme
     */
    public function __construct($scheme = null)
    {
        $this->component = $this->validate($scheme);
    }

    /**
     * Validate a scheme.
     *
     * @throws MalformedUriComponent if the scheme is invalid
     */
    private function validate($scheme): ?string
    {
        $scheme = $this->filterComponent($scheme);
        if (null === $scheme) {
            return $scheme;
        }

        if (preg_match(self::REGEXP_SCHEME, $scheme)) {
            return strtolower($scheme);
        }

        throw new MalformedUriComponent(sprintf("The scheme '%s' is invalid", $scheme));
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): ?string
    {
        return $this->component;
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($content)
    {
        $content = $this->validate($this->filterComponent($content));
        if ($content === $this->component) {
            return $this;
        }

        return new self($content);
    }
}

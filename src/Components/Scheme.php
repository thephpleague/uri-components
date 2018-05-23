<?php

/**
 * League.Uri (http://uri.thephpleague.com).
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

namespace League\Uri\Components;

use League\Uri\Exception\InvalidComponentArgument;

final class Scheme extends AbstractComponent
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
     * @param mixed $scheme
     */
    public function __construct($scheme = null)
    {
        $this->component = $this->validate($scheme);
    }

    /**
     * Validate a scheme.
     *
     * @param mixed $scheme
     *
     * @throws InvalidComponentArgument if the scheme is invalid
     *
     * @return null|string
     */
    private function validate($scheme)
    {
        $scheme = $this->filterComponent($scheme);
        if (null === $scheme) {
            return $scheme;
        }

        if (preg_match(self::REGEXP_SCHEME, $scheme)) {
            return strtolower($scheme);
        }

        throw new InvalidComponentArgument(sprintf("The scheme  '%s' is invalid", $scheme));
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(int $enc_type = self::RFC3986_ENCODING)
    {
        $this->filterEncoding($enc_type);

        return $this->component;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return (string) $this->component;
    }

    /**
     * {@inheritdoc}
     */
    public function getUriComponent(): string
    {
        if (null === $this->component) {
            return '';
        }

        return $this->component.':';
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($content)
    {
        $content = $this->validate($content);
        if ($content === $this->component) {
            return $this;
        }

        $clone = clone $this;
        $clone->component = $content;

        return $clone;
    }
}

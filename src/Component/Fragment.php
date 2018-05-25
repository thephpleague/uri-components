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

namespace League\Uri\Component;

final class Fragment extends Component
{
    /**
     * @internal
     */
    const REGEXP_FRAGMENT_ENCODING = '/
        (?:[^A-Za-z0-9_\-\.~\!\$&\'\(\)\*\+,;\=%\:\/@\?]+|
        %(?![A-Fa-f0-9]{2}))
    /x';

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
     * @param null|mixed $fragment
     */
    public function __construct($fragment = null)
    {
        $this->component = $this->validateComponent($fragment);
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(int $enc_type = self::RFC3986_ENCODING)
    {
        return $this->encodeComponent($this->component, $enc_type, self::REGEXP_FRAGMENT_ENCODING, self::REGEXP_INVALID_URI_CHARS);
    }

    /**
     * {@inheritdoc}
     */
    public function getUriComponent(): string
    {
        if (null === $this->component) {
            return '';
        }

        return '#'.$this->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($content)
    {
        $content = $this->validateComponent($content);
        if ($content === $this->component) {
            return $this;
        }

        $clone = clone $this;
        $clone->component = $content;

        return $clone;
    }
}

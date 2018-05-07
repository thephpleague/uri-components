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

final class Port extends AbstractComponent
{
    /**
     * @var int|null
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
     * @param mixed $port
     */
    public function __construct($port = null)
    {
        $this->component = $this->validate($port);
    }

    /**
     * Validate a port.
     *
     * @param mixed $port
     *
     * @throws Exception if the port is invalid
     *
     * @return null|int
     */
    protected function validate($port)
    {
        $port = $this->filterComponent($port);
        if (null === $port) {
            return null;
        }

        if (false !== ($fport = filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]))) {
            return $fport;
        }

        throw new Exception(sprintf('Expected port to be a positive integer or 0; received %s', $port));
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

        return ':'.$this->component;
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
    {
        return ['component' => $this->component];
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

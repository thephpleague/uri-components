<?php

/**
 * League.Uri (https://uri.thephpleague.com/components/).
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.8.2
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\Components;

/**
 * An abstract class to ease component manipulation.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
abstract class AbstractComponent implements ComponentInterface
{
    use ComponentTrait;

    /**
     * The component data.
     *
     * @var mixed
     */
    protected $data;

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties)
    {
        return new static($properties['data']);
    }

    /**
     * new instance.
     *
     * @param string|null $data the component value
     */
    public function __construct(string $data = null)
    {
        $this->data = $this->validate($data);
    }

    /**
     * Validate the component content.
     *
     *
     * @throws InvalidArgumentException if the component is no valid
     *
     */
    protected function validate($data)
    {
        if (null === $data) {
            return $data;
        }

        return $this->decodeComponent($this->validateString($data));
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(int $enc_type = self::RFC3986_ENCODING)
    {
        $this->assertValidEncoding($enc_type);

        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return (string) $this->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function getUriComponent(): string
    {
        return $this->__toString();
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($value): ComponentInterface
    {
        if ($value === $this->getContent()) {
            return $this;
        }

        return new static($value);
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
    {
        return ['component' => $this->getContent()];
    }

    /**
     * {@inheritdoc}
     */
    public function isNull(): bool
    {
        return null === $this->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        return '' == $this->getContent();
    }
}

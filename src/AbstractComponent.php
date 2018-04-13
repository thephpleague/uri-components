<?php
/**
 * League.Uri (http://uri.thephpleague.com).
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

namespace League\Uri\Components;

use TypeError;

/**
 * Value object representing a URI component.
 *
 * Instances of this interface are considered immutable; all methods that
 * might change state MUST be implemented such that they retain the internal
 * state of the current instance and return an instance that contains the
 * changed state.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 * @see        https://tools.ietf.org/html/rfc3986
 */
abstract class AbstractComponent implements ComponentInterface
{
    /**
     * @internal
     */
    const REGEXP_INVALID_URI_CHARS = '/[\x00-\x1f\x7f]/';

    /**
     * @internal
     */
    const ENCODING_LIST = [
        self::RFC1738_ENCODING => 1,
        self::RFC3986_ENCODING => 1,
        self::RFC3987_ENCODING => 1,
        self::NO_ENCODING => 1,
    ];

    /**
     * Filter encoding.
     *
     * @param  int       $enc_type
     * @throws Exception if the encoding is not supported
     */
    protected function filterEncoding(int $enc_type)
    {
        if (!isset(self::ENCODING_LIST[$enc_type])) {
            throw new Exception(sprintf('Unsupported or Unknown Encoding: %s', $enc_type));
        }
    }

    /**
     * Filter the input component.
     *
     * @param mixed $component
     *
     * @throws If the component can not be converted to a string or null
     *
     * @return null|string
     */
    protected function filterComponent($component)
    {
        if ($component instanceof ComponentInterface) {
            return $component->getContent();
        }

        if (null === $component) {
            return $component;
        }

        if (!is_scalar($component) && !method_exists($component, '__toString')) {
            throw new TypeError(sprintf('Expected component to be stringable; received %s', gettype($component)));
        }

        $component = (string) $component;
        if (!preg_match(self::REGEXP_INVALID_URI_CHARS, $component)) {
            return $component;
        }

        throw new Exception(sprintf('Invalid fragment string: %s', $component));
    }

    /**
     * {@inheritdoc}
     */
    abstract public function getContent(int $enc_type = self::RFC3986_ENCODING);

    /**
     * {@inheritdoc}
     */
    abstract public function __toString();

    /**
     * {@inheritdoc}
     */
    abstract public function getUriComponent(): string;

    /**
     * {@inheritdoc}
     */
    abstract public function withContent($content);
}

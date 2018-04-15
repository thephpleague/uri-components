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
     * @internal
     */
    const REGEXP_ENCODED_CHARS = ',%[A-Fa-f0-9]{2},';

    /**
     * @internal
     */
    const REGEXP_DECODED_SEQUENCE = ',%2[D|E]|3[0-9]|4[1-9|A-F]|5[0-9|A|F]|6[1-9|A-F]|7[0-9|E],i';

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
     * Validate the component content.
     *
     * @param mixed $component
     *
     * @throws Exception if the component is not valid
     *
     * @return string|null
     */
    protected function validateComponent($component)
    {
        $component = $this->filterComponent($component);
        if (null === $component) {
            return $component;
        }

        return $this->normalizeComponent($component);
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
     * Filter the URI password component.
     *
     * @param string $str
     *
     * @throws Exception If the content is invalid
     *
     * @return string|null
     */
    protected function normalizeComponent(string $str = null)
    {
        return preg_replace_callback(self::REGEXP_ENCODED_CHARS, [$this, 'decodeMatches'], $str);
    }

    /**
     * Decodes Matches sequence.
     *
     * @param array $matches
     *
     * @return string
     */
    protected function decodeMatches(array $matches): string
    {
        if (preg_match(static::REGEXP_DECODED_SEQUENCE, $matches[0])) {
            return strtoupper($matches[0]);
        }

        return rawurldecode($matches[0]);
    }

    /**
     * Encode Matches sequence.
     *
     * @param array $matches
     *
     * @return string
     */
    protected function encodeMatches(array $matches): string
    {
        return rawurlencode($matches[0]);
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

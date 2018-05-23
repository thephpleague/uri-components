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

use JsonSerializable;
use League\Uri\Exception\InvalidUriComponent;
use League\Uri\Exception\UnknownEncoding;
use TypeError;

abstract class AbstractComponent implements ComponentInterface, JsonSerializable
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
    const REGEXP_PREVENTS_DECODING = ',%2[A-F|1-2|4|6-9]|
        3[0-9|B|D]|
        4[1-9|A-F]|
        5[0-9|A|F]|
        6[1-9|A-F]|
        7[0-9|E]
    ,ix';

    /**
     * @internal
     */
    const REGEXP_NO_ENCODING = '/[^A-Za-z0-9_\-\.~]/';

    /**
     * @internal
     */
    const RFC1738_ENCODING_CHARS = [
        'pattern' => ['+', '~'],
        'replace' => ['%2B', '%7E'],
    ];

    /**
     * @internal
     *
     * IDN Host detector regular expression
     */
    const REGEXP_NON_ASCII_PATTERN = '/[^\x20-\x7f]/';

    /**
     * Filter encoding.
     *
     * @param  int       $enc_type
     * @throws Exception if the encoding is not supported
     */
    protected function filterEncoding(int $enc_type)
    {
        if (!isset(self::ENCODING_LIST[$enc_type])) {
            throw new UnknownEncoding(sprintf('Unsupported or Unknown Encoding: %s', $enc_type));
        }
    }

    /**
     * Validate the component content.
     *
     * @param mixed $component
     *
     * @return string|null
     */
    protected function validateComponent($component)
    {
        $component = $this->filterComponent($component);
        if (null === $component) {
            return $component;
        }

        return $this->decodeComponent($component);
    }

    /**
     * Filter the input component.
     *
     * @param mixed $component
     *
     * @throws InvalidUriComponent If the component can not be converted to a string or null
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

        throw new InvalidUriComponent(sprintf('Invalid fragment string: %s', $component));
    }

    /**
     * Filter the URI password component.
     *
     * @param string $str
     *
     * @return string|null
     */
    protected function decodeComponent(string $str = null)
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
        if (preg_match(static::REGEXP_PREVENTS_DECODING, $matches[0])) {
            return strtoupper($matches[0]);
        }

        return rawurldecode($matches[0]);
    }

    /**
     * Returns the component as converted for RFC3986 or RFC1738.
     *
     * @param null|string $part
     * @param int         $enc_type
     * @param string      $regexp_rfc3986
     * @param string      $regexp_rfc3987
     *
     * @return null|string
     */
    protected function encodeComponent($part, int $enc_type, string $regexp_rfc3986, string $regexp_rfc3987)
    {
        $this->filterEncoding($enc_type);
        if (self::NO_ENCODING === $enc_type || null === $part || !preg_match(self::REGEXP_NO_ENCODING, $part)) {
            return $part;
        }

        if ($enc_type == self::RFC3987_ENCODING) {
            return preg_replace_callback($regexp_rfc3987, [$this, 'encodeMatches'], $part) ?? $part;
        }

        $content = preg_replace_callback($regexp_rfc3986, [$this, 'encodeMatches'], $part) ?? rawurlencode($part);
        if (self::RFC3986_ENCODING === $enc_type) {
            return $content;
        }

        return str_replace(self::RFC1738_ENCODING_CHARS['pattern'], self::RFC1738_ENCODING_CHARS['replace'], $content);
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
    public function jsonSerialize()
    {
        return $this->getContent();
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

<?php

/**
 * League.Uri (http://uri.thephpleague.com/components)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.2
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\Components;

use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Exceptions\SyntaxError;
use TypeError;
use function gettype;
use function is_scalar;
use function method_exists;
use function preg_match;
use function preg_replace_callback;
use function rawurldecode;
use function rawurlencode;
use function sprintf;
use function strtoupper;

abstract class Component implements UriComponentInterface
{
    protected const REGEXP_INVALID_URI_CHARS = '/[\x00-\x1f\x7f]/';

    protected const REGEXP_ENCODED_CHARS = ',%[A-Fa-f0-9]{2},';

    protected const REGEXP_PREVENTS_DECODING = ',%
     	2[A-F|1-2|4-9]|
        3[0-9|B|D]|
        4[1-9|A-F]|
        5[0-9|A|F]|
        6[1-9|A-F]|
        7[0-9|E]
    ,ix';

    protected const REGEXP_NO_ENCODING = '/[^A-Za-z0-9_\-\.~]/';

    protected const REGEXP_NON_ASCII_PATTERN = '/[^\x20-\x7f]/';

    /**
     * Validate the component content.
     *
     * @param mixed $component an URI component
     */
    protected function validateComponent($component): ?string
    {
        $component = self::filterComponent($component);
        if (null === $component) {
            return $component;
        }

        return $this->decodeComponent($component);
    }

    /**
     * Filter the input component.
     *
     * @param mixed $component an URI component
     *
     * @throws SyntaxError If the component can not be converted to a string or null
     * @throws TypeError   If the component type is not supported
     */
    protected static function filterComponent($component): ?string
    {
        if ($component instanceof UriComponentInterface) {
            return $component->getContent();
        }

        if (null === $component) {
            return $component;
        }

        if (!is_scalar($component) && !method_exists($component, '__toString')) {
            throw new TypeError(sprintf('Expected component to be stringable; received %s.', gettype($component)));
        }

        $component = (string) $component;
        if (1 !== preg_match(self::REGEXP_INVALID_URI_CHARS, $component)) {
            return $component;
        }

        throw new SyntaxError(sprintf('Invalid component string: %s.', $component));
    }

    /**
     * Filter the URI password component.
     */
    protected function decodeComponent(string $str): ?string
    {
        return preg_replace_callback(self::REGEXP_ENCODED_CHARS, [$this, 'decodeMatches'], $str);
    }

    /**
     * Decodes Matches sequence.
     */
    protected function decodeMatches(array $matches): string
    {
        if (1 === preg_match(static::REGEXP_PREVENTS_DECODING, $matches[0])) {
            return strtoupper($matches[0]);
        }

        return rawurldecode($matches[0]);
    }

    /**
     * Returns the component as converted for RFC3986.
     *
     * @param ?string $str
     */
    protected function encodeComponent(?string $str, string $regexp): ?string
    {
        if (null !== $str && 1 === preg_match(self::REGEXP_NO_ENCODING, $str)) {
            return preg_replace_callback($regexp, [$this, 'encodeMatches'], $str) ?? rawurlencode($str);
        }

        return $str;
    }

    /**
     * Encode Matches sequence.
     */
    protected function encodeMatches(array $matches): string
    {
        return rawurlencode($matches[0]);
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): ?string
    {
        return $this->getContent();
    }

    /**
     * {@inheritDoc}
     */
    public function getUriComponent(): string
    {
        return (string) $this->getContent();
    }

    /**
     * {@inheritDoc}
     */
    abstract public function getContent(): ?string;

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        return (string) $this->getContent();
    }

    /**
     * {@inheritDoc}
     *
     * @return static
     */
    abstract public function withContent($content): UriComponentInterface;
}

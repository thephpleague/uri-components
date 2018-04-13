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

/**
 * Value object representing a URI Fragment component.
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
 * @see        https://tools.ietf.org/html/rfc3986#section-3.5
 */
final class Fragment extends AbstractComponent
{
    /**
     * @var string|null
     */
    private $fragment;

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['fragment']);
    }

    /**
     * New instance.
     *
     * @param null|mixed $fragment
     */
    public function __construct($fragment = null)
    {
        $this->fragment = $this->validate($fragment);
    }

    /**
     * Validate a port.
     *
     * @param mixed $fragment
     *
     * @throws Exception if the fragment is invalid
     *
     * @return null|string
     */
    private function validate($fragment)
    {
        $fragment = $this->filterComponent($fragment);
        if (null === $fragment) {
            return null;
        }

        static $encoded_pattern = ',%[A-Fa-f0-9]{2},';

        return preg_replace_callback($encoded_pattern, [$this, 'decodeMatches'], $fragment);
    }

    /**
     * Decodes Matches sequence.
     *
     * @param array $matches
     *
     * @return string
     */
    private function decodeMatches(array $matches): string
    {
        static $regexp = ',%2[D|E]|3[0-9]|4[1-9|A-F]|5[0-9|A|F]|6[1-9|A-F]|7[0-9|E],i';
        if (preg_match($regexp, $matches[0])) {
            return strtoupper($matches[0]);
        }

        return rawurldecode($matches[0]);
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(int $enc_type = self::RFC3986_ENCODING)
    {
        $this->filterEncoding($enc_type);

        if (null === $this->fragment || self::NO_ENCODING == $enc_type || !preg_match('/[^A-Za-z0-9_\-\.~]/', $this->fragment)) {
            return $this->fragment;
        }

        if (self::RFC3987_ENCODING == $enc_type) {
            return preg_replace_callback('/[\x00-\x1f\x7f]/', [$this, 'encodeMatches'], $this->fragment) ?? $this->fragment;
        }

        static $regexp = '/(?:[^A-Za-z0-9_\-\.~\!\$&\'\(\)\*\+,;\=%\:\/@\?]+|%(?![A-Fa-f0-9]{2}))/ux';
        $content = preg_replace_callback($regexp, [$this, 'encodeMatches'], $this->fragment) ?? rawurlencode($this->fragment);
        if (self::RFC3986_ENCODING === $enc_type) {
            return $content;
        }

        return str_replace(['+', '~'], ['%2B', '%7E'], $content);
    }

    /**
     * Encode Matches sequence.
     *
     * @param array $matches
     *
     * @return string
     */
    private function encodeMatches(array $matches): string
    {
        return rawurlencode($matches[0]);
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
        if (null === $this->fragment) {
            return '';
        }

        return '#'.$this->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
    {
        return [
            'fragment' => $this->fragment,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($content)
    {
        $content = $this->validate($content);
        if ($content === $this->fragment) {
            return $this;
        }

        $clone = clone $this;
        $clone->fragment = $content;

        return $clone;
    }
}

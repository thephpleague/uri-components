<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.8.0
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace League\Uri\Components;

use League\Uri\ComponentInterface;
use League\Uri\Exception;

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
final class Fragment implements ComponentInterface
{
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
     * Validate a port
     *
     * @param mixed $fragment
     *
     * @throws Exception if the fragment is invalid
     *
     * @return null|string
     */
    private function validate($fragment)
    {
        if ($fragment instanceof ComponentInterface) {
            $fragment = $fragment->getContent();
        }

        if (null === $fragment) {
            return null;
        }

        if ((is_object($fragment) && method_exists($fragment, '__toString')) || is_scalar($fragment)) {
            $fragment = (string) $fragment;
        }

        if (!is_string($fragment)) {
            throw new Exception(sprintf('Expected fragment to be stringable; received %s', gettype($fragment)));
        }

        static $pattern = '/[\x00-\x1f\x7f]/';
        if (preg_match($pattern, $fragment)) {
            throw new Exception(sprintf('Invalid fragment string: %s', $fragment));
        }

        static $encoded_pattern = ',%[A-Fa-f0-9]{2},';

        return preg_replace_callback($encoded_pattern, [$this, 'decode'], $fragment);
    }

    private function decode(array $matches): string
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
        if (!isset(self::ENCODING_LIST[$enc_type])) {
            throw new Exception(sprintf('Unsupported or Unknown Encoding: %s', $enc_type));
        }

        if (null === $this->fragment || self::NO_ENCODING == $enc_type || !preg_match('/[^A-Za-z0-9_\-\.~]/', $this->fragment)) {
            return $this->fragment;
        }

        if (self::RFC3987_ENCODING == $enc_type) {
            return preg_replace_callback('/[\x00-\x1f\x7f]/', [$this, 'encode'], $this->fragment) ?? $this->fragment;
        }

        static $regexp = '/(?:[^A-Za-z0-9_\-\.~\!\$&\'\(\)\*\+,;\=%\:\/@\?]+|%(?![A-Fa-f0-9]{2}))/ux';
        $content = preg_replace_callback($regexp, [$this, 'encode'], $this->fragment) ?? rawurlencode($this->fragment);
        if (self::RFC3986_ENCODING === $enc_type) {
            return $content;
        }

        return str_replace(['+', '~'], ['%2B', '%7E'], $content);
    }

    private function encode(array $matches): string
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

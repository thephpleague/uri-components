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
    public function getContent()
    {
        return $this->encodeComponent($this->component, PHP_QUERY_RFC3986, self::REGEXP_FRAGMENT_ENCODING, self::REGEXP_INVALID_URI_CHARS);
    }

    /**
     * Returns the decoded query.
     *
     * @return null|string
     */
    public function decoded()
    {
        return $this->encodeComponent($this->component, self::NO_ENCODING, self::REGEXP_FRAGMENT_ENCODING, self::REGEXP_INVALID_URI_CHARS);
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($content)
    {
        $content = $this->filterComponent($content);
        if ($content === $this->getContent()) {
            return $this;
        }

        return new self($content);
    }
}

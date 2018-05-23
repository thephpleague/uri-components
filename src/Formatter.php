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

namespace League\Uri;

use League\Uri\Components\ComponentInterface;
use League\Uri\Components\Fragment;
use League\Uri\Components\Host;
use League\Uri\Components\Path;
use League\Uri\Components\Query;
use League\Uri\Components\UserInfo;
use League\Uri\Exception\UnknownEncoding;
use League\Uri\Interfaces\Uri as DeprecatedLeagueUriInterface;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use TypeError;

final class Formatter implements EncodingInterface
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
     * @var int
     */
    private $enc_type = self::RFC3986_ENCODING;

    /**
     * @var string
     */
    private $query_separator = '&';

    /**
     * @var bool
     */
    private $preserve_query = false;

    /**
     * @var bool
     */
    private $preserve_fragment = false;

    /**
     * Formatting encoding type.
     *
     * @param int $enc_type a predefined constant value
     *
     * @return self
     */
    public function setEncoding(int $enc_type): self
    {
        if (!isset(self::ENCODING_LIST[$enc_type])) {
            throw new UnknownEncoding(sprintf('Unsupported or Unknown Encoding: %s', $enc_type));
        }

        $this->enc_type = $enc_type;

        return $this;
    }

    /**
     * Query separator setter.
     *
     * @param string $separator
     *
     * @return self
     */
    public function setQuerySeparator(string $separator): self
    {
        $this->query_separator = trim(filter_var($separator, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW));

        return $this;
    }

    /**
     * Whether we should preserve the Query component
     * regardless of its value.
     *
     * If set to true the query delimiter will be appended
     * to the URI regardless of the query string value
     *
     * @param bool $status
     *
     * @return self
     */
    public function preserveQuery(bool $status): self
    {
        $this->preserve_query = $status;

        return $this;
    }

    /**
     * Whether we should preserve the Fragment component
     * regardless of its value.
     *
     * If set to true the fragment delimiter will be appended
     * to the URI regardless of the query string value
     *
     * @param bool $status
     *
     * @return self
     */
    public function preserveFragment(bool $status): self
    {
        $this->preserve_fragment = $status;

        return $this;
    }

    /**
     * Format an Uri object.
     *
     * Format an object according to the formatter properties.
     * The object must implement one of the following interface:
     * <ul>
     * <li>League\Uri\Interfaces\Uri
     * <li>League\Uri\UriInterface
     * <li>Psr\Http\Message\UriInterface
     * </ul>
     *
     * @param mixed $input
     *
     * @return string
     */
    public function format($input): string
    {
        if ($input instanceof Query) {
            return (string) query_build($input, $this->query_separator, $this->enc_type);
        }

        if ($input instanceof ComponentInterface) {
            return (string) $input->getContent($this->enc_type);
        }

        if ($input instanceof DeprecatedLeagueUriInterface
            || $input instanceof UriInterface
            || $input instanceof Psr7UriInterface
        ) {
            return $this->formatUri($input);
        }

        throw new TypeError('input must be an URI object or a League URI Component object');
    }

    /**
     * Format an Uri according to the Formatter properties.
     *
     * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
     *
     * @return string
     */
    private function formatUri($uri): string
    {
        $scheme = $uri->getScheme();
        if ('' !== $scheme) {
            $scheme = $scheme.':';
        }

        $authority = null;
        $host = $uri->getHost();
        if ('' !== $host) {
            $user_info = $uri->getUserInfo();
            if ('' !== $user_info) {
                $authority .= (new UserInfo())->withContent($user_info)->getContent($this->enc_type).'@';
            }
            $authority .= (new Host($host))->getContent($this->enc_type);
            $port = $uri->getPort();
            if (null !== $port) {
                $authority .= ':'.$port;
            }
            $authority = '//'.$authority;
        }

        $path = (new Path($uri->getPath()))->getContent($this->enc_type);
        if (null !== $authority && '' !== $path && '/' !== $path[0]) {
            $path = '/'.$path;
        }

        $query = $uri->getQuery();
        if ('' !== $query || $this->preserve_query) {
            $query = '?'.query_build(query_parse($query), $this->query_separator, $this->enc_type);
        }

        $fragment = $uri->getFragment();
        if ('' !== $fragment || $this->preserve_fragment) {
            $fragment = '#'.(new Fragment($fragment))->getContent($this->enc_type);
        }

        return $scheme.$authority.$path.$query.$fragment;
    }
}

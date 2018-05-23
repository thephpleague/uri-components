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

namespace League\Uri\Converter;

use League\Uri;
use League\Uri\Components\ComponentInterface;
use League\Uri\Components\Fragment;
use League\Uri\Components\Host;
use League\Uri\Components\Path;
use League\Uri\Components\Query;
use League\Uri\Components\UserInfo;
use League\Uri\EncodingInterface;
use League\Uri\Interfaces\Uri as DeprecatedLeagueUriInterface;
use League\Uri\UriInterface;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use TypeError;

/**
 * @internal Use the function League\Uri\to_ascii or League\Uri\to_unicode instead
 */
final class StringConverter implements EncodingInterface
{
    /**
     * Convert an Uri object or a Component Object into a string.
     *
     * Converts according to the given parameters.
     * The object must implement one of the following interface:
     * <ul>
     * <li>League\Uri\Interfaces\Uri
     * <li>League\Uri\UriInterface
     * <li>League\Uri\ComponentInterface
     * <li>Psr\Http\Message\UriInterface
     * </ul>
     *
     * @param mixed  $input
     * @param int    $enc_type  a predefined constant value
     * @param string $separator
     *
     * @return string
     */
    public function convert($input, int $enc_type = self::RFC3986_ENCODING, string $separator = '&'): string
    {
        $separator = trim(filter_var($separator, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW));
        if ($input instanceof DeprecatedLeagueUriInterface
            || $input instanceof UriInterface
            || $input instanceof Psr7UriInterface
        ) {
            return $this->convertURI($input, $enc_type, $separator);
        }

        if ($input instanceof Query) {
            return (string) Uri\query_build($input, $separator, $enc_type);
        }

        if ($input instanceof ComponentInterface) {
            return (string) $input->getContent($enc_type);
        }

        throw new TypeError('input must be an URI object or a League URI Component object');
    }

    /**
     * Format an Uri according to the Formatter properties.
     *
     * @param DeprecatedLeagueUriInterface|Psr7UriInterface|UriInterface $uri
     * @param int                                                        $enc_type
     * @param string                                                     $separator
     *
     * @return string
     */
    private function convertURI($uri, int $enc_type, string $separator): string
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
                $authority .= (new UserInfo())->withContent($user_info)->getContent($enc_type).'@';
            }
            $authority .= (new Host($host))->getContent($enc_type);
            $port = $uri->getPort();
            if (null !== $port) {
                $authority .= ':'.$port;
            }
            $authority = '//'.$authority;
        }

        $path = (new Path($uri->getPath()))->getContent($enc_type);
        if (null !== $authority && '' !== $path && '/' !== $path[0]) {
            $path = '/'.$path;
        }

        list($remaining_uri, $fragment) = explode('#', (string) $uri, 2) + ['', null];
        list(, $query) = explode('?', $remaining_uri, 2) + ['', null];

        if (null !== $query) {
            $query = '?'.Uri\query_build(Uri\query_parse($uri->getQuery()), $separator, $enc_type);
        }

        if (null !== $fragment) {
            $fragment = '#'.(new Fragment($uri->getFragment()))->getContent($enc_type);
        }

        return $scheme.$authority.$path.$query.$fragment;
    }
}

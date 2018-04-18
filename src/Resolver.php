<?php
/**
 * League.Uri (http://uri.thephpleague.com).
 *
 * @package    League\Uri
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @copyright  2016 Ignace Nyamagana Butera
 * @license    https://github.com/thephpleague/uri-manipulations/blob/master/LICENSE (MIT License)
 * @version    1.5.0
 * @link       https://github.com/thephpleague/uri-manipulations
 */
declare(strict_types=1);

namespace League\Uri;

use League\Uri\Components\Path;
use League\Uri\Interfaces\Uri as LeagueUriInterface;
use Psr\Http\Message\UriInterface;

/**
 * Resolve an URI according to a base URI using
 * RFC3986 rules.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Modifiers
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
final class Resolver
{
    /**
     * Process and return an Uri.
     *
     * This method MUST retain the state of the submitted URI instance, and return
     * an URI instance of the same type that contains the applied modifications.
     *
     * This method MUST be transparent when dealing with error and exceptions.
     * It MUST not alter of silence them apart from validating its own parameters.
     *
     * @param LeagueUriInterface|UriInterface $uri
     * @param LeagueUriInterface|UriInterface $base_uri
     *
     * @return LeagueUriInterface|UriInterface
     */
    public function resolve($uri, $base_uri)
    {
        $base_uri = filter_uri($base_uri);
        $target_path = filter_uri($uri)->getPath();
        if ('' !== $uri->getScheme()) {
            return $uri
                ->withPath((string) (new Path($target_path))->withoutDotSegments());
        }

        if ('' !== $uri->getAuthority()) {
            return $uri
                ->withScheme($base_uri->getScheme())
                ->withPath((string) (new Path($target_path))->withoutDotSegments());
        }

        $components = $this->resolvePathAndQuery($uri, $base_uri);
        list($user, $pass) = explode(':', $base_uri->getUserInfo(), 2) + ['', null];

        return $uri
            ->withPath($this->formatPath($components['path'], $base_uri->getAuthority()))
            ->withQuery($components['query'])
            ->withHost($base_uri->getHost())
            ->withPort($base_uri->getPort())
            ->withUserInfo($user, $pass)
            ->withScheme($base_uri->getScheme())
        ;
    }

    /**
     * Resolve the URI for a Authority-less target URI.
     *
     * @param LeagueUriInterface|UriInterface $uri
     * @param LeagueUriInterface|UriInterface $base_uri
     *
     * @return string[]
     */
    private function resolvePathAndQuery($uri, $base_uri): array
    {
        $components = ['path' => $uri->getPath(), 'query' => $uri->getQuery()];

        if ('' === $components['path']) {
            $components['path'] = $base_uri->getPath();
            if ('' === $components['query']) {
                $components['query'] = $base_uri->getQuery();
            }

            return $components;
        }

        if ('/' === $components['path'][0]) {
            return $components;
        }

        $base_path = $base_uri->getPath();
        if ('' !== $base_uri->getAuthority() && '' === $base_path) {
            $components['path'] = '/'.$components['path'];

            return $components;
        }

        $segments = explode('/', $base_path);
        array_pop($segments);
        $components['path'] = implode('/', $segments).'/'.$components['path'];

        return $components;
    }

    /**
     * Format the resolved path.
     *
     * @param string $path
     * @param string $authority
     *
     * @return string
     */
    private function formatPath(string $path, string $authority): string
    {
        $path = (string) (new Path($path))->withoutDotSegments();
        if ('' === $authority || '' === $path) {
            return $path;
        }

        if ('/' !== ($path[0] ?? '')) {
            return '/'.$path;
        }

        return $path;
    }
}

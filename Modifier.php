<?php

/**
 * League.Uri (https://uri.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri;

use Deprecated;
use Dom\HTMLDocument;
use DOMDocument;
use DOMException;
use JsonSerializable;
use League\Uri\Components\DataPath;
use League\Uri\Components\Domain;
use League\Uri\Components\Fragment;
use League\Uri\Components\FragmentDirectives;
use League\Uri\Components\HierarchicalPath;
use League\Uri\Components\Host;
use League\Uri\Components\Path;
use League\Uri\Components\Query;
use League\Uri\Components\URLSearchParams;
use League\Uri\Contracts\Conditionable;
use League\Uri\Contracts\FragmentDirective;
use League\Uri\Contracts\FragmentInterface;
use League\Uri\Contracts\PathInterface;
use League\Uri\Contracts\SegmentedPathInterface;
use League\Uri\Contracts\UriAccess;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\MissingFeature;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Idna\Converter as IdnaConverter;
use League\Uri\IPv4\Converter as IPv4Converter;
use League\Uri\IPv6\Converter as IPv6Converter;
use League\Uri\KeyValuePair\Converter as KeyValuePairConverter;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use SensitiveParameter;
use Stringable;
use Uri\Rfc3986\Uri as Rfc3986Uri;
use Uri\WhatWg\Url as WhatWgUrl;
use ValueError;

use function array_keys;
use function class_exists;
use function count;
use function filter_var;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function ltrim;
use function rtrim;
use function str_ends_with;
use function str_starts_with;
use function strpos;
use function strtolower;
use function substr;
use function trim;

use const FILTER_FLAG_IPV4;
use const FILTER_VALIDATE_IP;

class Modifier implements Stringable, JsonSerializable, UriAccess, Conditionable
{
    private const MASK = '*****';

    final public function __construct(protected readonly Rfc3986Uri|WhatWgUrl|Psr7UriInterface|UriInterface $uri)
    {
    }

    public static function wrap(Rfc3986Uri|WhatWgUrl|Stringable|string $uri): static
    {
        return new static(match (true) {
            $uri instanceof self => $uri->uri,
            $uri instanceof Psr7UriInterface,
            $uri instanceof UriInterface,
            $uri instanceof Rfc3986Uri,
            $uri instanceof WhatWgUrl => $uri,
            default => Uri::new($uri),
        });
    }

    public function unwrap(): Rfc3986Uri|WhatWgUrl|Psr7UriInterface|UriInterface
    {
        return $this->uri;
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        return match (true) {
            $this->uri instanceof Rfc3986Uri,
            $this->uri instanceof UriInterface => $this->uri->toString(),
            $this->uri instanceof WhatWgUrl => $this->uri->toAsciiString(),
            $this->uri instanceof Psr7UriInterface => $this->uri->__toString(),
        };
    }

    public function toDisplayString(): string
    {
        return ($this->uri instanceof Uri ? $this->uri : Uri::new($this->toString()))->toDisplayString();
    }

    /**
     * Returns the Markdown string representation of the anchor tag with the current instance as its href attribute.
     */
    public function toMarkdownAnchor(?string $textContent = null): string
    {
        return '['.strtr($textContent ?? '{uri}', ['{uri}' => $this->toDisplayString()]).']('.$this->toString().')';
    }

    /**
     * Returns the HTML string representation of the anchor tag with the current instance as its href attribute.
     *
     * @param iterable<string, string|null|array<string>> $attributes an ordered map of key value. you must quote the value if needed
     *
     * @throws DOMException
     */
    public function toHtmlAnchor(Stringable|string|null $textContent = null, iterable $attributes = []): string
    {
        FeatureDetection::supportsDom();
        $uriString = $this->toString();
        $rfc3987String = UriString::toIriString($uriString);
        $doc = class_exists(HTMLDocument::class) ? HTMLDocument::createEmpty() : new DOMDocument(encoding:'utf-8'); /* @phpstan-ignore-line */
        $element = $doc->createElement('a');
        $element->setAttribute('href', $uriString);
        $element->appendChild(match (true) {
            null === $textContent => $doc->createTextNode($rfc3987String),
            default => $doc->createTextNode(strtr((string) $textContent, ['{uri}' => $rfc3987String])),
        });

        foreach ($attributes as $name => $value) {
            if ('href' === strtolower($name) || null === $value) {
                continue;
            }

            if (is_array($value)) {
                $value = implode(' ', $value);
            }

            is_string($value) || throw new ValueError('The attribute `'.$name.'` contains an invalid value.');
            $value = trim($value);
            if ('' === $value) {
                continue;
            }

            $element->setAttribute($name, $value);
        }

        false !== ($html = $doc->saveHTML($element)) || throw new DOMException('The HTML generation failed.');

        return $html;
    }

    public function resolve(Rfc3986Uri|WhatWgUrl|Psr7UriInterface|UriInterface|Stringable|string $uri): static
    {
        $uriString = match (true) {
            $uri instanceof Rfc3986Uri,
            $uri instanceof UriInterface => $uri->toString(),
            $uri instanceof WhatWgUrl => $uri->toAsciiString(),
            default => (string) $uri,
        };

        if (!$this->uri instanceof Psr7UriInterface) {
            return new static($this->uri->resolve($uriString));
        }

        $components = UriString::parse(UriString::resolve($uriString, $this->toString()));

        return new static(
            $this->uri
                ->withFragment($components['fragment'] ?? '')
                ->withQuery($components['query'] ?? '')
                ->withPath($components['path'] ?? '')
                ->withHost($components['host'] ?? '')
                ->withPort($components['port'] ?? null)
                ->withUserInfo($components['user'] ?? '', $components['pass'] ?? null)
                ->withScheme($components['scheme'] ?? '')
        );
    }

    public function normalize(): static
    {
        if ($this->uri instanceof WhatWgUrl) {
            return $this;
        }

        if ($this->uri instanceof Rfc3986Uri) {
            return new static(new Rfc3986Uri($this->uri->toString()));
        }

        if ($this->uri instanceof UriInterface) {
            return new static($this->uri->normalize());
        }

        $uri = Uri::new($this->uri->__toString())->normalize();
        if ($uri->toString() === $this->uri->__toString()) {
            return $this;
        }

        return new static(
            $this->uri
                ->withPath($uri->getPath())
                ->withHost($uri->getHost() ?? '')
                ->withUserInfo($uri->getUsername() ?? '', $uri->getPassword())
        );
    }

    public function withScheme(Stringable|string|null $scheme): static
    {
        return new static($this->uri->withScheme(self::normalizeComponent($scheme, $this->uri)));
    }

    public function withUserInfo(
        Stringable|string|null $username,
        #[SensitiveParameter] Stringable|string|null $password
    ): static {
        if ($this->uri instanceof Rfc3986Uri) {
            $userInfo = Encoder::encodeUser($username);
            if (null !== $password) {
                $userInfo .= ':'.Encoder::encodePassword($password);
            }

            return new static($this->uri->withUserInfo($userInfo));
        }

        if ($this->uri instanceof WhatWgUrl) {
            if (null !== $username) {
                $username = (string) $username;
            }

            if (null !== $password) {
                $password = (string) $password;
            }

            return new static($this->uri->withUsername($username)->withPassword($password));
        }

        if (null == $username && $this->uri instanceof Psr7UriInterface) {
            $username = '';
        }

        return new static($this->uri->withUserInfo(
            $username instanceof Stringable ? (string) $username : $username,
            $password instanceof Stringable ? (string) $password : $password,
        ));
    }

    /**
     * Returns a new instance with the entire UserInfo component redacted.
     *
     * Examples:
     *   http://user:pass@host → http://[REDACTED]@host
     *   http://user@host      → http://[REDACTED]@host
     */
    public function redactUserInfo(): static
    {
        if ($this->uri instanceof WhatWgUrl) {
            if (null !== $this->uri->getUsername() || null !== $this->uri->getPassword()) {
                return new static($this->uri->withUsername(self::MASK)->withPassword(null));
            }

            return $this;
        }

        if (null === $this->uri->getUserInfo()) {
            return $this;
        }

        return new static($this->uri->withUserInfo(self::MASK));
    }

    public function withHost(Stringable|string|null $host): static
    {
        $host = self::normalizeComponent($host, $this->uri);
        if ($this->uri instanceof Rfc3986Uri) {
            if (null !== $host) {
                $host = IdnaConverter::toAscii($host)->domain();
            }
        }

        return new static($this->uri->withHost($host));
    }

    public function withFragment(Stringable|string|null $fragment): static
    {
        if ($fragment instanceof FragmentDirective) {
            $fragment = new FragmentDirectives($fragment);
        }

        if (!$fragment instanceof FragmentInterface) {
            $fragment = str_starts_with((string) $fragment, FragmentDirectives::DELIMITER)
                ? FragmentDirectives::fromFragment($fragment)
                : Fragment::new($fragment);
        }

        return new static($this->uri->withFragment(
            $this->uri instanceof Psr7UriInterface
                ? $fragment->toString()
                : $fragment->value()
        ));
    }

    public function withPort(?int $port): static
    {
        return new static($this->uri->withPort($port));
    }

    public function withPath(Stringable|string $path): static
    {
        $path = (string) $path;
        if ($this->uri instanceof Rfc3986Uri) {
            $path = Encoder::encodePath($path);
        }

        return new static(self::normalizePath($this->uri, Path::new($path)));
    }

    final public function when(callable|bool $condition, callable $onSuccess, ?callable $onFail = null): static
    {
        if (!is_bool($condition)) {
            $condition = $condition($this);
        }

        return match (true) {
            $condition => $onSuccess($this),
            null !== $onFail => $onFail($this),
            default => $this,
        } ?? $this;
    }

    /*********************************
     * Query modifier methods
     *********************************/

    public function withQuery(Stringable|string|null $query): static
    {
        $query = self::normalizeComponent($query, $this->uri);
        $query = match (true) {
            $this->uri instanceof Rfc3986Uri => match (true) {
                Encoder::isQueryEncoded($query) => $query,
                default => Encoder::encodeQueryOrFragment($query),
            },
            $this->uri instanceof WhatWgUrl => URLSearchParams::new($query)->value(),
            default => $query,
        };

        return match (true) {
            $this->uri instanceof Rfc3986Uri && $query === $this->uri->getRawQuery(),
            $query === $this->uri->getQuery() => $this,
            default => new static($this->uri->withQuery($query)),
        };
    }

    /**
     * Change the encoding of the query.
     */
    public function encodeQuery(KeyValuePairConverter|int $to, KeyValuePairConverter|int|null $from = null): static
    {
        if (!$to instanceof KeyValuePairConverter) {
            $to = KeyValuePairConverter::fromEncodingType($to);
        }

        $from = match (true) {
            null === $from => KeyValuePairConverter::fromRFC3986(),
            !$from instanceof KeyValuePairConverter => KeyValuePairConverter::fromEncodingType($from),
            default => $from,
        };

        if ($to == $from) {
            return $this;
        }

        $originalQuery = $this->uri->getQuery();
        if (null === $originalQuery || '' === trim($originalQuery)) {
            return $this;
        }

        /** @var string $query */
        $query = QueryString::buildFromPairs(QueryString::parseFromValue($originalQuery, $from), $to);
        if ($query === $originalQuery) {
            return $this;
        }

        return $this->withQuery($query);
    }

    /**
     * Sort the URI query by keys.
     */
    public function sortQuery(): static
    {
        return $this->withQuery(Query::fromUri($this->uri)->sort()->value());
    }

    /**
     * Append the new query data to the existing URI query.
     */
    public function appendQuery(Stringable|string|null $query): static
    {
        return $this->withQuery(Query::fromUri($this->uri)->append($query)->value());
    }

    /**
     * Prepend the new query data to the existing URI query.
     */
    public function prependQuery(Stringable|string|null $query): static
    {
        return $this->withQuery(Query::fromUri($this->uri)->prepend($query)->value());
    }

    /**
     * Merge query pairs with the existing URI query.
     *
     * @param iterable<int, array{0:string, 1:string|null}> $pairs
     */
    public function appendQueryPairs(iterable $pairs, string $prefix = ''): self
    {
        return $this->appendQuery(Query::fromPairs($pairs, prefix: $prefix)->value());
    }

    public function prefixQueryPairs(string $prefix): self
    {
        return $this->withQuery(Query::fromPairs(Query::fromUri($this->uri), prefix: $prefix));
    }

    public function prefixQueryParameters(string $prefix): self
    {
        return $this->withQuery(Query::fromVariable(Query::fromUri($this->uri)->parameters(), prefix: $prefix));
    }

    /**
     * Append PHP query parameters to the existing URI query.
     */
    public function appendQueryParameters(object|array $parameters, string $prefix = ''): self
    {
        return $this->appendQuery(Query::fromVariable($parameters, prefix: $prefix)->value());
    }

    /**
     * Prepend PHP query parameters to the existing URI query.
     */
    public function prependQueryParameters(object|array $parameters, string $prefix = ''): self
    {
        return $this->withQuery(Query::fromVariable($parameters, prefix: $prefix)->append(Query::fromUri($this->uri)->value())->value());
    }

    public function replaceQueryParameter(string $name, mixed $value): self
    {
        return $this->withQuery(Query::fromUri($this->uri)->replaceParameter($name, $value)->value());
    }

    /**
     * Merge a new query with the existing URI query.
     */
    public function mergeQuery(Stringable|string|null $query): static
    {
        return $this->withQuery(Query::fromUri($this->uri)->merge($query)->value());
    }

    /**
     * Returns a new instance with the specified query values redacted.
     *
     * Only values are redacted. Missing keys are ignored.
     *
     * Example: redactQueryPairs(token)
     *   ?token=abc&mode=edit  → token=[REDACTED]&mode=edit (when 'token' is passed)
     */
    public function redactQueryPairs(string ...$keys): static
    {
        if ([] === $keys) {
            return $this;
        }

        $hasChanged = false;
        $pairs = [];
        foreach (Query::fromUri($this->uri) as $pair) {
            if (in_array($pair[0], $keys, true)) {
                $hasChanged = true;
                $pair[1] = self::MASK;
            }

            $pairs[] = $pair[0].'='.$pair[1];
        }

        return $hasChanged ? $this->withQuery(implode('&', $pairs)) : $this;
    }

    /**
     * Merge query pairs with the existing URI query.
     *
     * @param iterable<int, array{0:string, 1:string|null}> $pairs
     */
    public function mergeQueryPairs(iterable $pairs, string $prefix = ''): self
    {
        $currentPairs = [...Query::fromUri($this->uri)->pairs()];
        $pairs = [...$pairs];

        return match (true) {
            [] === $pairs,
            $currentPairs === $pairs => $this,
            default => $this->mergeQuery(Query::fromPairs($pairs, prefix: $prefix)->value()),
        };
    }

    /**
     * Merge PHP query parameters with the existing URI query.
     */
    public function mergeQueryParameters(object|array $parameters, string $prefix = ''): self
    {
        return $this->withQuery(Query::fromUri($this->uri)->mergeParameters($parameters, prefix: $prefix)->value());
    }

    /**
     * Remove query data according to their key name.
     */
    public function removeQueryPairsByKey(string ...$keys): static
    {
        $query = Query::fromUri($this->uri);
        $newQuery = $query->withoutPairByKey(...$keys)->value();

        return match ($query->value()) {
            $newQuery => $this,
            default => $this->withQuery($newQuery),
        };
    }

    /**
     * Remove query pair according to their value.
     */
    public function removeQueryPairsByValue(Stringable|string|int|float|bool|null ...$values): static
    {
        $query = Query::fromUri($this->uri);
        $newQuery = $query->withoutPairByValue(...$values)->value();

        return match ($query->value()) {
            $newQuery => $this,
            default => $this->withQuery($newQuery),
        };
    }

    /**
     * Remove query-pair according to their key/value name.
     */
    public function removeQueryPairsByKeyValue(string $key, Stringable|string|int|bool|null $value): static
    {
        $query = Query::fromUri($this->uri);
        $newQuery = $query->withoutPairByKeyValue($key, $value)->value();

        return match ($newQuery) {
            $query->value() => $this,
            default => $this->withQuery($newQuery),
        };
    }

    /**
     * Remove query data according to their PHP parameter key name.
     */
    public function removeQueryParameters(string ...$keys): static
    {
        $query = Query::fromUri($this->uri);
        $newQuery = $query->withoutParameters(...$keys)->value();

        return match ($newQuery) {
            $query->value() => $this,
            default => $this->withQuery($newQuery),
        };
    }

    /**
     * Remove empty pairs from the URL query component.
     *
     * A pair is considered empty if its name is the empty string
     * and its value is either the empty string or the null value
     */
    public function removeEmptyQueryPairs(): static
    {
        return $this->withQuery(Query::fromUri($this->uri)->withoutEmptyPairs()->value());
    }

    /**
     * Returns an instance where numeric indices associated to PHP's array like key are removed.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the query component normalized so that numeric indexes
     * are removed from the pair key value.
     *
     * ie.: toto[3]=bar[3]&foo=bar becomes toto[]=bar[3]&foo=bar
     */
    public function removeQueryParameterIndices(): static
    {
        $query = Query::fromUri($this->uri);
        $newQuery = $query->withoutNumericIndices()->value();

        return match ($newQuery) {
            $query->value() => $this,
            default => $this->withQuery($newQuery),
        };
    }

    public function replaceQueryPair(int $offset, string $key, Stringable|string|int|float|bool|null $value): static
    {
        return $this->withQuery(Query::fromUri($this->uri)->replace($offset, $key, $value)->value());
    }

    /*********************************
     * Host modifier methods
     *********************************/

    /**
     * Add the root label to the URI.
     */
    public function addRootLabel(): static
    {
        $host = $this->uri instanceof WhatWgUrl ? $this->uri->getAsciiHost() : $this->uri->getHost();

        return match (true) {
            null === $host,
            str_ends_with($host, '.') => $this,
            default => $this->withHost($host.'.'),
        };
    }

    /**
     * Append a label or a host to the current URI host.
     *
     * @throws SyntaxError If the host cannot be appended
     */
    public function appendLabel(Stringable|string|null $label): static
    {
        $host = $this->uri instanceof WhatWgUrl ? $this->uri->getAsciiHost() : $this->uri->getHost();
        $isAsciiDomain = null === $host || IdnaConverter::toAscii($host)->domain() === $host;

        $host = Host::new($host);
        $label = Host::new($label);

        if (null === $label->value()) {
            return $this;
        }

        if ($host->isIpv4()) {
            return $this->withHost($host->value().'.'.ltrim($label->value(), '.'));
        }

        if (!$host->isDomain()) {
            throw new SyntaxError('The URI host '.$host->toString().' cannot be appended.');
        }

        $newHost = Domain::new($host)->append($label);
        $newHost = !$isAsciiDomain ? $newHost->toUnicode() : $newHost->toAscii();

        return $this->withHost($newHost);
    }

    /**
     * Convert the URI host part to its ASCII value.
     */
    public function hostToAscii(): static
    {
        $currentHost = $this->uri instanceof WhatWgUrl ? $this->uri->getAsciiHost() : $this->uri->getHost();
        $host = IdnaConverter::toAsciiOrFail((string) $currentHost);

        return match (true) {
            null === $currentHost,
            '' === $currentHost,
            $host === $currentHost => $this,
            default => $this->withHost($host),
        };
    }

    /**
     * Convert the URI host part to its Unicode value.
     */
    public function hostToUnicode(): static
    {
        $currentHost = $this->uri instanceof WhatWgUrl ? $this->uri->getAsciiHost() : $this->uri->getHost();
        $host = IdnaConverter::toUnicode((string) $currentHost)->domain();

        return match (true) {
            null === $currentHost,
            '' === $currentHost,
            $host === $currentHost => $this,
            default => $this->withHost($host),
        };
    }

    /**
     * Normalizes the URI host content to an IPv4 dot-decimal notation if possible
     * otherwise returns the uri instance unchanged.
     *
     * @see https://url.spec.whatwg.org/#concept-ipv4-parser
     */
    public function hostToDecimal(): static
    {
        $currentHost = $this->uri instanceof WhatWgUrl ? $this->uri->getAsciiHost() : $this->uri->getHost();
        $hostIp = self::ipv4Converter()->toDecimal($currentHost);

        return match (true) {
            null === $currentHost,
            '' === $currentHost,
            null === $hostIp,
            $currentHost === $hostIp => $this,
            default => $this->withHost($hostIp),
        };
    }

    /**
     * Normalizes the URI host content to a IPv4 octal notation if possible
     * otherwise returns the uri instance unchanged.
     *
     * @see https://url.spec.whatwg.org/#concept-ipv4-parser
     */
    public function hostToOctal(): static
    {
        $currentHost = $this->uri instanceof WhatWgUrl ? $this->uri->getAsciiHost() : $this->uri->getHost();
        $hostIp = self::ipv4Converter()->toOctal($currentHost);

        return match (true) {
            null === $currentHost,
            '' === $currentHost,
            null === $hostIp,
            $currentHost === $hostIp  => $this,
            default => $this->withHost($hostIp),
        };
    }

    /**
     * Normalizes the URI host content to a IPv4 octal notation if possible
     * otherwise returns the uri instance unchanged.
     *
     * @see https://url.spec.whatwg.org/#concept-ipv4-parser
     */
    public function hostToHexadecimal(): static
    {
        $currentHost = $this->uri instanceof WhatWgUrl ? $this->uri->getAsciiHost() : $this->uri->getHost();
        $hostIp = self::ipv4Converter()->toHexadecimal($currentHost);

        return match (true) {
            null === $currentHost,
            '' === $currentHost,
            null === $hostIp,
            $currentHost === $hostIp  => $this,
            default => $this->withHost($hostIp),
        };
    }

    public function hostToIpv6Compressed(): static
    {
        return $this->withHost(IPv6Converter::compress($this->uri instanceof WhatWgUrl ? $this->uri->getAsciiHost() : $this->uri->getHost()));
    }

    public function hostToIpv6Expanded(): static
    {
        return $this->withHost(IPv6Converter::expand($this->uri instanceof WhatWgUrl ? $this->uri->getAsciiHost() : $this->uri->getHost()));
    }

    /**
     * Prepend a label or a host to the current URI host.
     *
     * @throws SyntaxError If the host cannot be prepended
     */
    public function prependLabel(Stringable|string|null $label): static
    {
        $host = $this->uri instanceof WhatWgUrl ? $this->uri->getAsciiHost() : $this->uri->getHost();
        $isAsciiDomain = null === $host || IdnaConverter::toAscii($host)->domain() === $host;

        $host = Host::new($host);
        $label = Host::new($label);

        if (null === $label->value()) {
            return $this;
        }

        if ($host->isIpv4()) {
            return $this->withHost(rtrim($label->value(), '.').'.'.$host->value());
        }

        if (!$host->isDomain()) {
            throw new SyntaxError('The URI host '.$host->toString().' cannot be prepended.');
        }

        $newHost = Domain::new($host)->prepend($label);
        $newHost = !$isAsciiDomain ? $newHost->toUnicode() : $newHost->toAscii();

        return $this->withHost($newHost);
    }

    /**
     * Remove host labels according to their offset.
     */
    public function removeLabels(int ...$keys): static
    {
        $host = $this->uri instanceof WhatWgUrl ? $this->uri->getAsciiHost() : $this->uri->getHost();
        if (null === $host || ('' === $host && $this->uri instanceof Psr7UriInterface)) {
            return $this;
        }

        $isAsciiDomain = IdnaConverter::toAscii($host)->domain() === $host;
        $newHost = Domain::new($host)->withoutLabel(...$keys);
        $newHost = !$isAsciiDomain ? $newHost->toUnicode() : $newHost->toAscii();

        return $this->withHost($newHost);
    }

    /**
     * Remove the root label to the URI.
     */
    public function removeRootLabel(): static
    {
        $host = $this->uri instanceof WhatWgUrl ? $this->uri->getAsciiHost() : $this->uri->getHost();

        return match (true) {
            null === $host,
            '' === $host,
            !str_ends_with($host, '.') => $this,
            default => $this->withHost(substr($host, 0, -1)),
        };
    }

    /**
     * Slice the host from the URI.
     */
    public function sliceLabels(int $offset, ?int $length = null): static
    {
        $currentHost = $this->uri instanceof WhatWgUrl ? $this->uri->getAsciiHost() : $this->uri->getHost();
        if (null === $currentHost || ('' === $currentHost && $this->uri instanceof Psr7UriInterface)) {
            return $this;
        }

        $isAsciiDomain = IdnaConverter::toAscii($currentHost)->domain() === $currentHost;
        $host = Domain::new($currentHost)->slice($offset, $length);
        $host = !$isAsciiDomain ? $host->toUnicode() : $host->toAscii();

        if ($currentHost === $host) {
            return $this;
        }

        return $this->withHost($host);
    }

    /**
     * Remove the host zone identifier.
     */
    public function removeZoneId(): static
    {
        $host = Host::fromUri($this->uri);

        return match (true) {
            $host->hasZoneIdentifier() => $this->withHost($host->withoutZoneIdentifier()->value()),
            default => $this,
        };
    }

    /**
     * Replace a label of the current URI host.
     */
    public function replaceLabel(int $offset, Stringable|string|null $label): static
    {
        $host = $this->uri instanceof WhatWgUrl ? $this->uri->getAsciiHost() : $this->uri->getHost();
        $isAsciiDomain = null === $host || IdnaConverter::toAscii($host)->domain() === $host;
        $newHost = Domain::new($host)->withLabel($offset, $label);
        $newHost = !$isAsciiDomain ? $newHost->toUnicode() : $newHost->toAscii();

        return $this->withHost($newHost);
    }

    public function normalizeIp(): static
    {
        $host = $this->uri instanceof WhatWgUrl ? $this->uri->getAsciiHost() : $this->uri->getHost();
        if (in_array($host, [null, ''], true)) {
            return $this;
        }

        try {
            $converted = IPv4Converter::fromEnvironment()->toDecimal($host);
        } catch (MissingFeature) {
            $converted = null;
        }

        if (false === filter_var($converted, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $converted = IPv6Converter::compress($host);
        }

        if ($converted !== $host) {
            return $this->withHost($converted);
        }

        return $this;
    }

    public function normalizeHost(): static
    {
        $host = $this->uri instanceof WhatWgUrl ? $this->uri->getAsciiHost() : $this->uri->getHost();
        if (in_array($host, [null, ''], true)) {
            return $this;
        }

        $new = $this->normalizeIp();
        $newHost = $new->uri instanceof WhatWgUrl ? $new->uri->getAsciiHost() : $new->uri->getHost();
        if ($newHost !== $host) {
            return $new;
        }

        return $this->withHost(Host::new($host)->toAscii());
    }

    /*********************************
     * Path modifier methods
     *********************************/

    /**
     * Add a new base path to the URI path.
     */
    public function addBasePath(Stringable|string $path): static
    {
        /** @var HierarchicalPath $path */
        $path = HierarchicalPath::new($path)->withLeadingSlash();
        /** @var HierarchicalPath $currentPath */
        $currentPath = HierarchicalPath::fromUri($this->uri)->withLeadingSlash();

        return match (true) {
            !str_starts_with($currentPath->toString(), $path->toString()) => $this->withPath($path->append($currentPath)->toString()),
            default => $this->withPath($currentPath),
        };
    }

    /**
     * Add a leading slash to the URI path.
     */
    public function addLeadingSlash(): static
    {
        $path = $this->uri->getPath();

        return match (true) {
            str_starts_with($path, '/') => $this,
            default => $this->withPath('/'.$path),
        };
    }

    /**
     * Add a trailing slash to the URI path.
     */
    public function addTrailingSlash(): static
    {
        $path = $this->uri->getPath();

        return match (true) {
            str_ends_with($path, '/') => $this,
            default => $this->withPath($path.'/'),
        };
    }

    /**
     * Append a new path or add a path to the URI path.
     */
    public function appendPath(Stringable|string $path): static
    {
        return $this->withPath(HierarchicalPath::fromUri($this->uri)->append($path));
    }

    /**
     * Prepend a path or add a new path to the URI path.
     */
    public function prependPath(Stringable|string $path): static
    {
        return $this->withPath(HierarchicalPath::fromUri($this->uri)->prepend($path));
    }

    /**
     * Append a list of segments or a new path to the URI path.
     *
     * @param iterable<Stringable|string> $segments
     */
    public function appendSegments(iterable $segments): static
    {
        return $this->withPath(HierarchicalPath::fromUri($this->uri)->appendSegments($segments));
    }

    /**
     * Prepend a list of segments or a new path to the URI path.
     *
     * @param iterable<Stringable|string> $segments
     */
    public function prependSegments(iterable $segments): static
    {
        return $this->withPath(HierarchicalPath::fromUri($this->uri)->prependSegments($segments));
    }

    /**
     * Convert the Data URI path to its ascii form.
     */
    public function dataPathToAscii(): static
    {
        return $this->withPath(DataPath::fromUri($this->uri)->toAscii()->toString());
    }

    /**
     * Convert the Data URI path to its binary (base64encoded) form.
     */
    public function dataPathToBinary(): static
    {
        return $this->withPath(DataPath::fromUri($this->uri)->toBinary()->toString());
    }

    /**
     * Remove a base path from the URI path.
     */
    public function removeBasePath(Stringable|string $path): static
    {
        $basePath = HierarchicalPath::new($path)->withLeadingSlash()->toString();
        $currentPath = HierarchicalPath::fromUri($this->uri)->withLeadingSlash()->toString();
        $newPath = substr($currentPath, strlen($basePath));

        return match (true) {
            '/' === $basePath,
            !str_starts_with($currentPath, $basePath),
            !str_starts_with($newPath, '/') => $this,
            default => $this->withPath($newPath),
        };
    }

    /**
     * Remove dot segments from the URI path.
     */
    public function removeDotSegments(): static
    {
        return $this->withPath(Path::fromUri($this->uri)->withoutDotSegments()->toString());
    }

    /**
     * Remove empty segments from the URI path.
     */
    public function removeEmptySegments(): static
    {
        return $this->withPath(HierarchicalPath::fromUri($this->uri)->withoutEmptySegments()->toString());
    }

    /**
     * Remove the leading slash from the URI path.
     */
    public function removeLeadingSlash(): static
    {
        return $this->withPath(Path::fromUri($this->uri)->withoutLeadingSlash());
    }

    /**
     * Remove the trailing slash from the URI path.
     */
    public function removeTrailingSlash(): static
    {
        $path = $this->uri->getPath();

        return match (true) {
            !str_ends_with($path, '/') => $this,
            default => $this->withPath(substr($path, 0, -1)),
        };
    }

    /**
     * Remove path segments from the URI path according to their offsets.
     */
    public function removeSegments(int ...$keys): static
    {
        return $this->withPath(HierarchicalPath::fromUri($this->uri)->withoutSegment(...$keys)->toString());
    }

    /**
     * Replace the URI path basename.
     */
    public function replaceBasename(Stringable|string $basename): static
    {
        return $this->withPath(HierarchicalPath::fromUri($this->uri)->withBasename($basename));
    }

    /**
     * Replace the data URI path parameters.
     */
    public function replaceDataUriParameters(Stringable|string $parameters): static
    {
        return $this->withPath(DataPath::fromUri($this->uri)->withParameters($parameters)->toString());
    }

    /**
     * Replace the URI path dirname.
     */
    public function replaceDirname(Stringable|string $dirname): static
    {
        return $this->withPath(HierarchicalPath::fromUri($this->uri)->withDirname($dirname));
    }

    /**
     * Replace the URI path basename extension.
     */
    public function replaceExtension(Stringable|string $extension): static
    {
        return $this->withPath(HierarchicalPath::fromUri($this->uri)->withExtension($extension)->toString());
    }

    /**
     * Replace a segment from the URI path according its offset.
     */
    public function replaceSegment(int $offset, Stringable|string $segment): static
    {
        return $this->withPath(HierarchicalPath::fromUri($this->uri)->withSegment($offset, $segment)->toString());
    }

    /**
     * Slice the host from the URI.
     */
    public function sliceSegments(int $offset, ?int $length = null): static
    {
        return $this->withPath(HierarchicalPath::fromUri($this->uri)->slice($offset, $length));
    }

    /**
     * Returns a new instance with specific path segments redacted by index.
     *
     * Indexing starts at 0 for the first segment after the leading slash.
     * Negative indexing is supported>
     * Out-of-range offsets are ignored.
     *
     * Example: redactPathSegmentsByOffset(2, -2)
     *   /api/users/john/orders/55/details → /api/users/[REDACTED]/orders/[REDACTED]/details
     */
    public function redactPathSegmentsByOffset(int ...$offsets): static
    {
        if ([] === $offsets || [] === ($path = [...HierarchicalPath::fromUri($this->uri)])) {
            return $this;
        }

        $nbSegments = count($path);
        $hasChanged = false;
        foreach ($offsets as $offset) {
            if ($offset < - $nbSegments - 1 || $offset > $nbSegments) {
                continue;
            }

            if (0 > $offset) {
                $offset += $nbSegments;
            }

            if (!in_array($path[$offset] ?? null, [null, self::MASK], true)) {
                $hasChanged = true;
                $path[$offset] = self::MASK;
            }
        }

        return !$hasChanged ? $this : $this->withPath(implode('/', $path));
    }

    /**
     * Returns a new instance with all path segments matching the given names redacted.
     *
     * Matching is strict string comparison on raw (decoded) segments.
     *
     * Example: redactPathSegments('john')
     *  /api/user/john/orders -> /api/user/[REDACTED]/orders
     */
    public function redactPathSegments(string ...$segments): static
    {
        if ([] === $segments || [] === ($path = [...HierarchicalPath::fromUri($this->uri)])) {
            return $this;
        }

        $hasChanged = false;
        foreach ($segments as $segment) {
            foreach (array_keys($path, $segment, true) as $key) {
                $hasChanged = true;
                $path[$key] = self::MASK;
            }
        }

        return !$hasChanged ? $this : $this->withPath(implode('/', $path));
    }

    /**
     * Returns a new instance where, for each matched segment,
     * the **immediately following** segment is redacted.
     *
     * Only the next segment is masked — not all subsequent ones.
     * If no following segment exists, it is ignored.
     *
     * Example: redactPathNextSegments('john')
     *   /api/users/john/orders/55/details → /api/users/john/[REDACTED]/55/details
     */
    public function redactPathNextSegments(string ...$segments): static
    {
        if ([] === $segments || [] === ($path = [...HierarchicalPath::fromUri($this->uri)])) {
            return $this;
        }

        $hasChanged = false;
        foreach ($segments as $segment) {
            foreach (array_keys($path, $segment, true) as $key) {
                $nextKey = $key + 1;
                if (!in_array($path[$nextKey] ?? null, [null, self::MASK], true)) {
                    $hasChanged = true;
                    $path[$nextKey] = self::MASK;
                }
            }
        }

        return !$hasChanged ? $this : $this->withPath(implode('/', $path));
    }

    /**
     * Normalize a URI path.
     *
     * Make sure the path always has a leading slash if an authority is present
     * and the path is not the empty string.
     */
    final protected static function normalizePath(WhatWgUrl|Rfc3986Uri|Psr7UriInterface|UriInterface $uri, PathInterface $path): WhatWgUrl|Rfc3986Uri|Psr7UriInterface|UriInterface
    {
        if (!$uri instanceof Psr7UriInterface) {
            return $uri->withPath($path->toString());
        }

        $pathString = $path->toString();
        if ('' === $pathString) {
            return $uri->withPath($pathString);
        }

        $authority = $uri->getAuthority();
        if ('' !== $authority) {
            return $uri->withPath(str_starts_with($pathString, '/') ? $pathString : '/'.$pathString);
        }

        // If there is no authority, the path cannot start with `//`
        if (str_starts_with($pathString, '//')) {
            return $uri->withPath('/.'.$pathString);
        }

        $colonPos = strpos($pathString, ':');
        if (false !== $colonPos && '' === $uri->getScheme()) {
            // In the absence of a scheme and of an authority,
            // the first path segment cannot contain a colon (":") character.'
            $slashPos = strpos($pathString, '/');
            (false !== $slashPos && $colonPos > $slashPos) || throw new SyntaxError(
                'In absence of the scheme and authority components, the first path segment cannot contain a colon (":") character.'
            );
        }

        return $uri->withPath($pathString);
    }

    /**
     * Normalize the URI component value depending on the subject interface.
     *
     * null value MUST be converted to the empty string if a Psr7 UriInterface is being manipulated.
     */
    final protected static function normalizeComponent(Stringable|string|null $component, Rfc3986Uri|WhatWgUrl|Psr7UriInterface|UriInterface $uri): ?string
    {
        return match (true) {
            $uri instanceof Psr7UriInterface,
            $component instanceof Stringable => (string) $component,
            default => $component,
        };
    }

    final protected static function ipv4Converter(): IPv4Converter
    {
        static $converter;

        $converter = $converter ?? IPv4Converter::fromEnvironment();

        return $converter;
    }

    public function displayUriString(): string
    {
        if ($this->uri instanceof Uri) {
            return $this->uri->toDisplayString();
        }

        return Uri::new($this->uri)->toDisplayString();
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.6.0
     * @codeCoverageIgnore
     * @see Modifier::displayUriString()
     *
     * Remove query data according to their key name.
     */
    #[Deprecated(message:'use League\Uri\Modifier::displayUriString() instead', since:'league/uri-components:7.6.0')]
    public function getIdnUriString(): string
    {
        if ($this->uri instanceof WhatWgUrl) {
            return $this->uri->toUnicodeString();
        }

        $currentHost = $this->uri->getHost();
        if (null === $currentHost || '' === $currentHost) {
            return $this->toString();
        }

        $host = IdnaConverter::toUnicode($currentHost)->domain();
        if ($host === $currentHost) {
            return $this->toString();
        }

        $components = match (true) {
            $this->uri instanceof Rfc3986Uri => UriString::parse($this->uri->toRawString()),
            $this->uri instanceof UriInterface => $this->uri->toComponents(),
            default => UriString::parse($this->uri),
        };
        $components['host'] = $host;

        return UriString::build($components);
    }

    /*********************************
     * Fragment modifier methods
     *********************************/

    public function appendFragmentDirectives(FragmentDirectives|FragmentDirective|Stringable|string ...$directives): static
    {
        return $this->applyFragmentChanges(FragmentDirectives::fromUri($this->unwrap())->append(...$directives));
    }

    final protected function applyFragmentChanges(FragmentDirectives $fragmentDirectives): static
    {
        $fValue = Fragment::fromUri($this->unwrap())->value();
        if (null === $fValue) {
            return $this->withFragment($fragmentDirectives);
        }

        $pos = strpos($fValue, FragmentDirectives::DELIMITER);
        if (false === $pos) {
            return $this->withFragment($fValue.$fragmentDirectives->value());
        }

        return $this->withFragment(substr($fValue, 0, $pos).$fragmentDirectives->value());
    }

    public function prependFragmentDirectives(FragmentDirectives|FragmentDirective|Stringable|string ...$directives): static
    {
        return $this->applyFragmentChanges(FragmentDirectives::fromUri($this->unwrap())->prepend(...$directives));
    }

    public function removeFragmentDirectives(int ...$offset): static
    {
        return $this->applyFragmentChanges(FragmentDirectives::fromUri($this->unwrap())->remove(...$offset));
    }

    public function replaceFragmentDirective(int $offset, FragmentDirective|Stringable|string $directive): static
    {
        return $this->applyFragmentChanges(FragmentDirectives::fromUri($this->unwrap())->replace($offset, $directive));
    }

    public function sliceFragmentDirectives(int $offset, ?int $length): static
    {
        return $this->applyFragmentChanges(FragmentDirectives::fromUri($this->unwrap())->slice($offset, $length));
    }

    public function filterFragmentDirectives(callable $callback): static
    {
        return $this->applyFragmentChanges(FragmentDirectives::fromUri($this->unwrap())->filter($callback));
    }

    public function stripFragmentDirectives(): static
    {
        $fragment = Fragment::fromUri($this->unwrap())->value();
        if (null === $fragment || (false === ($pos = strpos($fragment, FragmentDirectives::DELIMITER)))) {
            return $this;
        }

        return $this->withFragment(substr($fragment, 0, $pos));
    }

    public function retainFragmentDirectives(): static
    {
        return $this->withFragment(FragmentDirectives::fromUri($this->unwrap()));
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.7.0
     * @codeCoverageIgnore
     * @see Modifier::appendPath
     */
    #[Deprecated(message:'use League\Uri\Modifier::appendPath() instead', since:'league/uri-components:7.7.0')]
    public function appendSegment(Stringable|string $segment): static
    {
        return $this->appendPath($segment);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.7.0
     * @codeCoverageIgnore
     * @see Modifier::prependPath
     */
    #[Deprecated(message:'use League\Uri\Modifier::prependPath() instead', since:'league/uri-components:7.7.0')]
    public function prependSegment(Stringable|string $segment): static
    {
        return $this->prependPath($segment);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.6.0
     * @codeCoverageIgnore
     * @see Modifier::wrap()
     *
     * @param UriFactoryInterface|null $uriFactory deprecated, will be removed in the next major release
     */
    #[Deprecated(message:'use League\Uri\Modifier::wrap() instead', since:'league/uri-components:7.6.0')]
    public static function from(Rfc3986Uri|WhatWgUrl|Stringable|string $uri, ?UriFactoryInterface $uriFactory = null): static
    {
        return new static(match (true) {
            $uri instanceof self => $uri->uri,
            $uri instanceof Psr7UriInterface,
            $uri instanceof UriInterface,
            $uri instanceof Rfc3986Uri,
            $uri instanceof WhatWgUrl => $uri,
            $uriFactory instanceof UriFactoryInterface => $uriFactory->createUri((string) $uri),  // using UriFactoryInterface is deprecated
            default => Uri::new($uri),
        });
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.2.0
     * @codeCoverageIgnore
     * @see Modifier::removeQueryParameters()
     *
     * Remove query data according to their key name.
     */
    #[Deprecated(message:'use League\Uri\Modifier::removeQueryParameters() instead', since:'league/uri-components:7.2.0')]
    public function removeParams(string ...$keys): static
    {
        return $this->removeQueryParameters(...$keys);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.2.0
     * @codeCoverageIgnore
     * @see Modifier::removeEmptyQueryPairs()
     *
     * Remove empty pairs from the URL query component.
     *
     * A pair is considered empty if it's name is the empty string
     * and its value is either the empty string or the null value
     */
    #[Deprecated(message:'use League\Uri\Modifier::removeEmptyQueryPairs() instead', since:'league/uri-components:7.2.0')]
    public function removeEmptyPairs(): static
    {
        return $this->removeEmptyQueryPairs();
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.2.0
     * @codeCoverageIgnore
     * @see Modifier::removeQueryPairsByKey()
     *
     * Remove query data according to their key name.
     */
    #[Deprecated(message:'use League\Uri\Modifier::removeQueryPairsByKey() instead', since:'league/uri-components:7.2.0')]
    public function removePairs(string ...$keys): static
    {
        return $this->removeQueryPairsByKey(...$keys);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.2.0
     * @codeCoverageIgnore
     * @see Modifier::removeQueryPairsByKey()
     *
     * Remove query data according to their key name.
     */
    #[Deprecated(message:'use League\Uri\Modifier::removeQueryPairsByKey() instead', since:'league/uri-components:7.2.0')]
    public function removeQueryPairs(string ...$keys): static
    {
        return $this->removeQueryPairsByKey(...$keys);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.6.0
     * @codeCoverageIgnore
     * @see Modifier::unwrap()
     *
     * Remove query data according to their key name.
     */
    #[Deprecated(message:'use League\Uri\Modifier::unwrap() instead', since:'league/uri-components:7.6.0')]
    public function getUri(): Psr7UriInterface|UriInterface
    {
        if ($this->uri instanceof Rfc3986Uri || $this->uri instanceof WhatWgUrl) {
            return Uri::new($this->uri);
        }

        return $this->uri;
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 7.6.0
     * @codeCoverageIgnore
     * @see Modifier::toString()
     *
     * Remove query data according to their key name.
     */
    #[Deprecated(message:'use League\Uri\Modifier::toString() instead', since:'league/uri-components:7.6.0')]
    public function getUriString(): string
    {
        return $this->toString();
    }
}

<?php

/**
 * League.Uri (http://uri.thephpleague.com/components).
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

use finfo;
use League\Uri\Exception\MalformedUriComponent;
use League\Uri\Exception\PathNotFound;
use SplFileObject;
use function base64_decode;
use function base64_encode;
use function count;
use function explode;
use function file_get_contents;
use function implode;
use function preg_match;
use function preg_replace_callback;
use function rawurldecode;
use function rawurlencode;
use function sprintf;
use function str_replace;
use function strlen;
use function strtolower;
use const FILEINFO_MIME;

final class DataPath extends Component
{
    private const DEFAULT_MIMETYPE = 'text/plain';

    private const DEFAULT_PARAMETER = 'charset=us-ascii';

    private const BINARY_PARAMETER = 'base64';

    private const REGEXP_MIMETYPE = ',^\w+/[-.\w]+(?:\+[-.\w]+)?$,';

    private const REGEXP_DATAPATH_ENCODING = '/
        (?:[^A-Za-z0-9_\-\.~\!\$&\'\(\)\*\+,;\=%\:\/@]+
        |%(?![A-Fa-f0-9]{2}))
    /x';

    private const REGEXP_DATAPATH = '/^\w+\/[-.\w]+(?:\+[-.\w]+)?;,$/';

    /**
     * @var Path
     */
    private $path;

    /**
     * The mediatype mimetype.
     *
     * @var string
     */
    private $mimetype;

    /**
     * The mediatype parameters.
     *
     * @var string[]
     */
    private $parameters;

    /**
     * Is the Document bas64 encoded.
     *
     * @var bool
     */
    private $is_binary_data;

    /**
     * The document string representation.
     *
     * @var string
     */
    private $document;

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['path']);
    }

    /**
     * Create a new instance from a file path.
     *
     * @param null|resource $context
     *
     * @throws PathNotFound If the File is not readable
     *
     */
    public static function createFromPath(string $path, $context = null): self
    {
        $args = [$path, false];
        if (null !== $context) {
            $args[] = $context;
        }

        $content = @file_get_contents(...$args);
        if (false === $content) {
            throw new PathNotFound(sprintf('`%s` failed to open stream: No such file or directory', $path));
        }

        return new self(
            str_replace(' ', '', (new finfo(FILEINFO_MIME))->file($path))
            .';base64,'.base64_encode($content)
        );
    }

    /**
     * {@inheritdoc}
     */
    private function validate($path): ?string
    {
        if (null === $path) {
            return $path;
        }

        if ('' === $path || ',' === $path) {
            return 'text/plain;charset=us-ascii,';
        }

        if (preg_match(self::REGEXP_DATAPATH, $path)) {
            return substr($path, 0, -1).'charset=us-ascii,';
        }

        return $path;
    }

    /**
     * {@inheritdoc}
     */
    public function __construct($path = '')
    {
        $path = $this->validate($this->filterComponent($path));
        $this->path = new Path($path);

        $path = (string) $path;
        if (preg_match(self::REGEXP_NON_ASCII_PATTERN, $path) && false === strpos($path, ',')) {
            throw new MalformedUriComponent(sprintf('The path `%s` is invalid according to RFC2937', $path));
        }

        $is_binary_data = false;
        [$mediatype, $this->document] = explode(',', $path, 2) + [1 => ''];
        [$mimetype, $parameters] = explode(';', $mediatype, 2) + [1 => ''];
        $this->mimetype = $this->filterMimeType($mimetype);
        $this->parameters = $this->filterParameters($parameters, $is_binary_data);
        $this->is_binary_data = $is_binary_data;
        $this->validateDocument();
    }

    /**
     * Filter the mimeType property.
     *
     * @throws MalformedUriComponent If the mimetype is invalid
     */
    private function filterMimeType(string $mimetype): string
    {
        if ('' == $mimetype) {
            return static::DEFAULT_MIMETYPE;
        }

        if (preg_match(static::REGEXP_MIMETYPE, $mimetype)) {
            return $mimetype;
        }

        throw new MalformedUriComponent(sprintf('invalid mimeType, `%s`', $mimetype));
    }

    /**
     * Extract and set the binary flag from the parameters if it exists.
     *
     * @param bool $is_binary_data the binary flag to set
     *
     * @throws MalformedUriComponent If the mediatype parameters contain invalid data
     *
     * @return string[]
     */
    private function filterParameters(string $parameters, bool &$is_binary_data): array
    {
        if ('' === $parameters) {
            return [static::DEFAULT_PARAMETER];
        }

        if (preg_match(',(;|^)'.static::BINARY_PARAMETER.'$,', $parameters, $matches)) {
            $parameters = substr($parameters, 0, - strlen($matches[0]));
            $is_binary_data = true;
        }

        $params = array_filter(explode(';', $parameters));
        if ([] !== array_filter($params, [$this, 'validateParameter'])) {
            throw new MalformedUriComponent(sprintf('invalid mediatype parameters, `%s`', $parameters));
        }

        return $params;
    }

    /**
     * Validate mediatype parameter.
     */
    private function validateParameter(string $parameter): bool
    {
        $properties = explode('=', $parameter);

        return 2 != count($properties) || strtolower($properties[0]) === static::BINARY_PARAMETER;
    }

    /**
     * Validate the path document string representation.
     *
     * @throws MalformedUriComponent If the data is invalid
     */
    private function validateDocument(): void
    {
        if (!$this->is_binary_data) {
            return;
        }

        $res = base64_decode($this->document, true);
        if (false === $res || $this->document !== base64_encode($res)) {
            throw new MalformedUriComponent(sprintf('invalid document, `%s`', $this->document));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): ?string
    {
        return $this->path->getContent();
    }

    /**
     * Retrieves the data string.
     *
     * Retrieves the data part of the path. If no data part is provided return
     * a empty string
     */
    public function getData(): string
    {
        return $this->document;
    }

    /**
     * Tells whether the data is binary safe encoded.
     */
    public function isBinaryData(): bool
    {
        return $this->is_binary_data;
    }

    /**
     * Retrieve the data mime type associated to the URI.
     *
     * If no mimetype is present, this method MUST return the default mimetype 'text/plain'.
     *
     * @see http://tools.ietf.org/html/rfc2397#section-2
     *
     * @return string The URI scheme.
     */
    public function getMimeType(): string
    {
        return $this->mimetype;
    }

    /**
     * Retrieve the parameters associated with the Mime Type of the URI.
     *
     * If no parameters is present, this method MUST return the default parameter 'charset=US-ASCII'.
     *
     * @see http://tools.ietf.org/html/rfc2397#section-2
     *
     * @return string The URI scheme.
     */
    public function getParameters(): string
    {
        return implode(';', $this->parameters);
    }

    /**
     * Retrieve the mediatype associated with the URI.
     *
     * If no mediatype is present, this method MUST return the default parameter 'text/plain;charset=US-ASCII'.
     *
     * @see http://tools.ietf.org/html/rfc2397#section-3
     *
     * @return string The URI scheme.
     */
    public function getMediaType(): string
    {
        return $this->getMimeType().';'.$this->getParameters();
    }

    /**
     * {@inheritdoc}
     */
    public function isAbsolute(): bool
    {
        return $this->path->isAbsolute();
    }

    /**
     * Save the data to a specific file.
     *
     */
    public function save(string $path, string $mode = 'w'): SplFileObject
    {
        $file = new SplFileObject($path, $mode);
        $data = $this->is_binary_data ? base64_decode($this->document) : rawurldecode($this->document);
        $file->fwrite((string) $data);

        return $file;
    }

    /**
     * Returns an instance where the data part is base64 encoded.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance where the data part is base64 encoded
     *
     */
    public function toBinary(): self
    {
        if ($this->is_binary_data) {
            return $this;
        }

        return new self($this->formatComponent(
            $this->mimetype,
            $this->getParameters(),
            !$this->is_binary_data,
            base64_encode(rawurldecode($this->document))
        ));
    }

    /**
     * Format the DataURI string.
     */
    private function formatComponent(
        string $mimetype,
        string $parameters,
        bool $is_binary_data,
        string $data
    ): string {
        if ('' != $parameters) {
            $parameters = ';'.$parameters;
        }

        if ($is_binary_data) {
            $parameters .= ';base64';
        }

        $path = $mimetype.$parameters.','.$data;

        return preg_replace_callback(self::REGEXP_DATAPATH_ENCODING, [$this, 'encodeMatches'], $path) ?? $path;
    }

    /**
     * Returns an instance where the data part is url encoded following RFC3986 rules.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance where the data part is url encoded
     *
     * @return static
     */
    public function toAscii(): self
    {
        if (false === $this->is_binary_data) {
            return $this;
        }

        return new self($this->formatComponent(
            $this->mimetype,
            $this->getParameters(),
            false,
            rawurlencode((string) base64_decode($this->document))
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function withoutDotSegments(): self
    {
        return new self($this->path->withoutDotSegments());
    }

    /**
     * {@inheritdoc}
     */
    public function withLeadingSlash(): self
    {
        return new self($this->path->withLeadingSlash());
    }

    /**
     * {@inheritdoc}
     */
    public function withoutLeadingSlash(): self
    {
        return new self($this->path->withoutLeadingSlash());
    }

    /**
     * {@inheritdoc}
     */
    public function withContent($content): self
    {
        $content = $this->filterComponent($content);
        if ($content === $this->path->getContent()) {
            return $this;
        }

        return new self($content);
    }

    /**
     * Return an instance with the specified mediatype parameters.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified mediatype parameters.
     *
     * Users must provide encoded characters.
     *
     * An empty parameters value is equivalent to removing the parameter.
     */
    public function withParameters(string $parameters): self
    {
        if ($parameters === $this->getParameters()) {
            return $this;
        }

        return new self($this->formatComponent($this->mimetype, $parameters, $this->is_binary_data, $this->document));
    }
}

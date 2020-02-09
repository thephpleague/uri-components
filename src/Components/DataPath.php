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

use League\Uri\Contracts\DataPathInterface;
use League\Uri\Contracts\PathInterface;
use League\Uri\Contracts\UriComponentInterface;
use League\Uri\Exceptions\FileinfoSupportMissing;
use League\Uri\Exceptions\SyntaxError;
use function base64_decode;
use function base64_encode;
use function count;
use function explode;
use function file_get_contents;
use function gettype;
use function implode;
use function is_scalar;
use function method_exists;
use function preg_match;
use function preg_replace_callback;
use function rawurldecode;
use function rawurlencode;
use function sprintf;
use function str_replace;
use function strlen;
use function strtolower;
use const FILEINFO_MIME;

final class DataPath extends Component implements DataPathInterface
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
     * All ASCII letters sorted by typical frequency of occurrence.
     *
     * @var string
     */
    private const ASCII = "\x20\x65\x69\x61\x73\x6E\x74\x72\x6F\x6C\x75\x64\x5D\x5B\x63\x6D\x70\x27\x0A\x67\x7C\x68\x76\x2E\x66\x62\x2C\x3A\x3D\x2D\x71\x31\x30\x43\x32\x2A\x79\x78\x29\x28\x4C\x39\x41\x53\x2F\x50\x22\x45\x6A\x4D\x49\x6B\x33\x3E\x35\x54\x3C\x44\x34\x7D\x42\x7B\x38\x46\x77\x52\x36\x37\x55\x47\x4E\x3B\x4A\x7A\x56\x23\x48\x4F\x57\x5F\x26\x21\x4B\x3F\x58\x51\x25\x59\x5C\x09\x5A\x2B\x7E\x5E\x24\x40\x60\x7F\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F";

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
     * New instance.
     *
     * @param mixed|string $path
     */
    public function __construct($path = '')
    {
        $this->path = new Path($this->filterPath(self::filterComponent($path)));
        $str = $this->path->__toString();
        $is_binary_data = false;
        [$mediatype, $this->document] = explode(',', $str, 2) + [1 => ''];
        [$mimetype, $parameters] = explode(';', $mediatype, 2) + [1 => ''];
        $this->mimetype = $this->filterMimeType($mimetype);
        $this->parameters = $this->filterParameters($parameters, $is_binary_data);
        $this->is_binary_data = $is_binary_data;
        $this->validateDocument();
    }

    /**
     * Filter the data path.
     *
     * @param ?string $path
     *
     * @throws SyntaxError If the path is null
     * @throws SyntaxError If the path is not valid according to RFC2937
     */
    private function filterPath(?string $path): string
    {
        if (null === $path) {
            throw new SyntaxError('The path can not be null.');
        }

        if ('' === $path || ',' === $path) {
            return 'text/plain;charset=us-ascii,';
        }

        if (1 === preg_match(self::REGEXP_DATAPATH, $path)) {
            $path = substr($path, 0, -1).'charset=us-ascii,';
        }

        if (strlen($path) !== strspn($path, self::ASCII) || false === strpos($path, ',')) {
            throw new SyntaxError(sprintf('The path `%s` is invalid according to RFC2937.', $path));
        }

        return $path;
    }

    /**
     * Filter the mimeType property.
     *
     * @throws SyntaxError If the mimetype is invalid
     */
    private function filterMimeType(string $mimetype): string
    {
        if ('' == $mimetype) {
            return static::DEFAULT_MIMETYPE;
        }

        if (1 === preg_match(static::REGEXP_MIMETYPE, $mimetype)) {
            return $mimetype;
        }

        throw new SyntaxError(sprintf('Invalid mimeType, `%s`.', $mimetype));
    }

    /**
     * Extract and set the binary flag from the parameters if it exists.
     *
     * @param bool $is_binary_data the binary flag to set
     *
     * @throws SyntaxError If the mediatype parameters contain invalid data
     *
     * @return string[]
     */
    private function filterParameters(string $parameters, bool &$is_binary_data): array
    {
        if ('' === $parameters) {
            return [static::DEFAULT_PARAMETER];
        }

        if (1 === preg_match(',(;|^)'.static::BINARY_PARAMETER.'$,', $parameters, $matches)) {
            $parameters = substr($parameters, 0, - strlen($matches[0]));
            $is_binary_data = true;
        }

        $params = array_filter(explode(';', $parameters));
        if ([] !== array_filter($params, [$this, 'validateParameter'])) {
            throw new SyntaxError(sprintf('Invalid mediatype parameters, `%s`.', $parameters));
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
     * @throws SyntaxError If the data is invalid
     */
    private function validateDocument(): void
    {
        if (!$this->is_binary_data) {
            return;
        }

        $res = base64_decode($this->document, true);
        if (false === $res || $this->document !== base64_encode($res)) {
            throw new SyntaxError(sprintf('Invalid document, `%s`.', $this->document));
        }
    }

    /**
     * {@inheritDoc}
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
     * @throws SyntaxError If the File is not readable
     */
    public static function createFromPath(string $path, $context = null): self
    {
        static $finfo_support = null;
        $finfo_support = $finfo_support ?? class_exists(\finfo::class);

        // @codeCoverageIgnoreStart
        if (!$finfo_support) {
            throw new FileinfoSupportMissing(sprintf('Please install ext/fileinfo to use the %s() method.', __METHOD__));
        }
        // @codeCoverageIgnoreEnd

        $file_args = [$path, false];
        $mime_args = [$path, FILEINFO_MIME];
        if (null !== $context) {
            $file_args[] = $context;
            $mime_args[] = $context;
        }

        $content = @file_get_contents(...$file_args);
        if (false === $content) {
            throw new SyntaxError(sprintf('`%s` failed to open stream: No such file or directory.', $path));
        }

        $mimetype = (string) (new \finfo(FILEINFO_MIME))->file(...$mime_args);

        return new self(
            str_replace(' ', '', $mimetype)
            .';base64,'.base64_encode($content)
        );
    }

    /**
     * Create a new instance from a URI object.
     *
     * @param mixed $uri an URI object
     *
     * @throws \TypeError If the URI object is not supported
     */
    public static function createFromUri($uri): self
    {
        return new self(Path::createFromUri($uri)->__toString());
    }

    /**
     * {@inheritDoc}
     */
    public function getContent(): ?string
    {
        return $this->path->getContent();
    }

    /**
     * {@inheritDoc}
     */
    public function getData(): string
    {
        return $this->document;
    }

    /**
     * {@inheritDoc}
     */
    public function isBinaryData(): bool
    {
        return $this->is_binary_data;
    }

    /**
     * {@inheritDoc}
     */
    public function getMimeType(): string
    {
        return $this->mimetype;
    }

    /**
     * {@inheritDoc}
     */
    public function getParameters(): string
    {
        return implode(';', $this->parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function getMediaType(): string
    {
        return $this->getMimeType().';'.$this->getParameters();
    }

    /**
     * {@inheritDoc}
     */
    public function isAbsolute(): bool
    {
        return $this->path->isAbsolute();
    }

    /**
     * {@inheritDoc}
     */
    public function hasTrailingSlash(): bool
    {
        return $this->path->hasTrailingSlash();
    }

    /**
     * {@inheritDoc}
     */
    public function decoded(): string
    {
        return $this->path->decoded();
    }

    /**
     * {@inheritDoc}
     */
    public function save(string $path, string $mode = 'w'): \SplFileObject
    {
        $file = new \SplFileObject($path, $mode);
        $data = $this->is_binary_data ? base64_decode($this->document, true) : rawurldecode($this->document);
        $file->fwrite((string) $data);

        return $file;
    }

    /**
     * {@inheritDoc}
     */
    public function toBinary(): DataPathInterface
    {
        if ($this->is_binary_data) {
            return $this;
        }

        return new self($this->formatComponent(
            $this->mimetype,
            $this->getParameters(),
            true,
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
     * {@inheritDoc}
     */
    public function toAscii(): DataPathInterface
    {
        if (false === $this->is_binary_data) {
            return $this;
        }

        return new self($this->formatComponent(
            $this->mimetype,
            $this->getParameters(),
            false,
            rawurlencode((string) base64_decode($this->document, true))
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function withoutDotSegments(): PathInterface
    {
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function withLeadingSlash(): PathInterface
    {
        return new self($this->path->withLeadingSlash());
    }

    /**
     * {@inheritDoc}
     */
    public function withoutLeadingSlash(): PathInterface
    {
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function withoutTrailingSlash(): PathInterface
    {
        $path = $this->path->withoutTrailingSlash();
        if ($path === $this->path) {
            return $this;
        }

        return new self($path);
    }

    /**
     * {@inheritDoc}
     */
    public function withTrailingSlash(): PathInterface
    {
        $path = $this->path->withTrailingSlash();
        if ($path === $this->path) {
            return $this;
        }

        return new self($path);
    }

    /**
     * {@inheritDoc}
     */
    public function withContent($content): UriComponentInterface
    {
        $content = self::filterComponent($content);
        if ($content === $this->path->getContent()) {
            return $this;
        }

        return new self($content);
    }

    /**
     * @param mixed|string $parameters
     */
    public function withParameters($parameters): DataPathInterface
    {
        if (!is_scalar($parameters) && !method_exists($parameters, '__toString')) {
            throw new \TypeError(sprintf('Expected parameter to be stringable; received %s.', gettype($parameters)));
        }

        $parameters = (string) $parameters;
        if ($parameters === $this->getParameters()) {
            return $this;
        }

        return new self($this->formatComponent($this->mimetype, $parameters, $this->is_binary_data, $this->document));
    }
}

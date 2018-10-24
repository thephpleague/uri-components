<?php

/**
 * League.Uri (https://uri.thephpleague.com/components/).
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.8.2
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\Components;

use SplFileObject;

/**
 * Value object representing a URI Path component.
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
 * @see        https://tools.ietf.org/html/rfc3986#section-3.3
 */
class DataPath extends AbstractComponent
{
    use PathInfoTrait;

    const DEFAULT_MIMETYPE = 'text/plain';

    const DEFAULT_PARAMETER = 'charset=us-ascii';

    const BINARY_PARAMETER = 'base64';

    const REGEXP_MIMETYPE = ',^\w+/[-.\w]+(?:\+[-.\w]+)?$,';

    /**
     * The mediatype mimetype.
     *
     * @var string
     */
    protected $mimetype;

    /**
     * The mediatype parameters.
     *
     * @var string[]
     */
    protected $parameters;

    /**
     * Is the Document bas64 encoded.
     *
     * @var bool
     */
    protected $is_binary_data;

    /**
     * The document string representation.
     *
     * @var string
     */
    protected $document;

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties)
    {
        return new static($properties['data']);
    }

    /**
     * Create a new instance from a file path.
     *
     *
     * @throws Exception If the File is not readable
     *
     * @return static
     */
    public static function createFromPath(string $path): self
    {
        if (!file_exists($path) || !is_readable($path)) {
            throw new Exception(sprintf('`%s` does not exist or is not readabele', $path));
        }

        return new static(static::format(
            str_replace(' ', '', (new \finfo(FILEINFO_MIME))->file($path)),
            '',
            true,
            base64_encode(file_get_contents($path))
        ));
    }

    /**
     * new instance.
     *
     * @param string|null $path the component value
     */
    public function __construct(string $path = null)
    {
        if (null === $path) {
            $path = '';
        }

        parent::__construct($path);
    }

    /**
     * Return the decoded string representation of the component.
     *
     */
    protected function getDecoded(): string
    {
        return $this->data;
    }

    /**
     * validate the submitted path.
     *
     * @param string $path
     */
    protected function validate($path)
    {
        if ('' === $path) {
            $this->document = '';
            $this->mimetype = static::DEFAULT_MIMETYPE;
            $this->parameters = [static::DEFAULT_PARAMETER];
            $this->is_binary_data = false;
            return static::DEFAULT_MIMETYPE.';'.static::DEFAULT_PARAMETER.',';
        }

        static $idn_pattern = '/[^\x20-\x7f]/';
        if (preg_match($idn_pattern, $path) || false === strpos($path, ',')) {
            throw new Exception(sprintf(
                'The submitted path `%s` is invalid according to RFC2937',
                $path
            ));
        }

        $parts = explode(',', $path, 2);
        $mediatype = array_shift($parts);
        $this->document = (string) array_shift($parts);
        $mediatype = explode(';', $mediatype, 2);
        $mimetype = (string) array_shift($mediatype);
        $parameters = (string) array_shift($mediatype);
        $this->mimetype = $this->filterMimeType($mimetype);
        $this->parameters = $this->filterParameters($parameters);
        $this->validateDocument();
        return $this->format($this->mimetype, $this->getParameters(), $this->is_binary_data, $this->document);
    }

    /**
     * Filter the mimeType property.
     *
     *
     * @throws Exception If the mimetype is invalid
     *
     */
    protected function filterMimeType(string $mimetype): string
    {
        if ('' == $mimetype) {
            return static::DEFAULT_MIMETYPE;
        }

        if (!preg_match(static::REGEXP_MIMETYPE, $mimetype)) {
            throw new Exception(sprintf('invalid mimeType, `%s`', $mimetype));
        }

        return $mimetype;
    }

    /**
     * Extract and set the binary flag from the parameters if it exists.
     *
     *
     * @throws Exception If the mediatype parameters contain invalid data
     *
     * @return string[]
     */
    protected function filterParameters(string $parameters): array
    {
        $this->is_binary_data = false;
        if ('' === $parameters) {
            return [static::DEFAULT_PARAMETER];
        }

        if (preg_match(',(;|^)'.static::BINARY_PARAMETER.'$,', $parameters, $matches)) {
            $parameters = substr($parameters, 0, - strlen($matches[0]));
            $this->is_binary_data = true;
        }

        $params = array_filter(explode(';', $parameters));
        if (!empty(array_filter($params, [$this, 'validateParameter']))) {
            throw new Exception(sprintf('invalid mediatype parameters, `%s`', $parameters));
        }

        return $params;
    }

    /**
     * Validate mediatype parameter.
     *
     * @param string $parameter a mediatype parameter
     *
     */
    protected function validateParameter(string $parameter): bool
    {
        $properties = explode('=', $parameter);

        return 2 != count($properties) || strtolower($properties[0]) === static::BINARY_PARAMETER;
    }

    /**
     * Validate the path document string representation.
     *
     * @throws Exception If the data is invalid
     */
    protected function validateDocument()
    {
        if (!$this->is_binary_data) {
            return;
        }

        $res = base64_decode($this->document, true);
        if (false === $res || $this->document !== base64_encode($res)) {
            throw new Exception(sprintf('invalid document, `%s`', $this->document));
        }
    }

    /**
     * Format the DataURI string.
     *
     *
     */
    protected static function format(
        string $mimetype,
        string $parameters,
        bool $is_binary_data,
        string $data
    ): string {
        if ('' != $parameters) {
            $parameters = ';'.$parameters;
        }

        if ($is_binary_data) {
            $parameters .= ';'.static::BINARY_PARAMETER;
        }

        return static::encodePath($mimetype.$parameters.','.$data);
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
    {
        return [
            'component' => $this->getContent(),
            'mimetype' => $this->mimetype,
            'parameters' => $this->parameters,
            'is_binary' => $this->is_binary_data,
            'data' => $this->document,
        ];
    }

    /**
     * Retrieves the data string.
     *
     * Retrieves the data part of the path. If no data part is provided return
     * a empty string
     *
     */
    public function getData(): string
    {
        return $this->document;
    }

    /**
     * Tells whether the data is binary safe encoded.
     *
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
     * Save the data to a specific file.
     *
     * @param string $path The path to the file where to save the data
     * @param string $mode The mode parameter specifies the type of access you require to the stream.
     *
     */
    public function save(string $path, string $mode = 'w'): SplFileObject
    {
        $file = new SplFileObject($path, $mode);
        $data = $this->is_binary_data ? base64_decode($this->document) : rawurldecode($this->document);
        $file->fwrite($data);

        return $file;
    }

    /**
     * Returns an instance where the data part is base64 encoded.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance where the data part is base64 encoded
     *
     * @return static
     */
    public function toBinary(): self
    {
        if ($this->is_binary_data) {
            return $this;
        }

        return new static($this->format(
            $this->mimetype,
            $this->getParameters(),
            !$this->is_binary_data,
            base64_encode(rawurldecode($this->document))
        ));
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
        if (!$this->is_binary_data) {
            return $this;
        }

        return new static($this->format(
            $this->mimetype,
            $this->getParameters(),
            !$this->is_binary_data,
            rawurlencode(base64_decode($this->document))
        ));
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
     *
     * @param string $parameters The mediatype parameters to use with the new instance.
     *
     * @throws Exception for invalid query strings.
     *
     * @return static A new instance with the specified mediatype parameters.
     */
    public function withParameters(string $parameters): self
    {
        if ($parameters === $this->getParameters()) {
            return $this;
        }

        return new static($this->format(
            $this->mimetype,
            $parameters,
            $this->is_binary_data,
            $this->document
        ));
    }
}

<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @copyright  2016 Ignace Nyamagana Butera
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.0.0
 * @link       https://github.com/thephpleague/uri-components
 */
namespace League\Uri\Components\Traits;

use InvalidArgumentException;

/**
 * A Trait to validate a Hostname
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
trait Hostname
{
    /**
     * Tells whether we have a IDN or not
     *
     * @var bool
     */
    protected $isIdn = false;

    /**
     * Format an label collection for string representation of the Host
     *
     * @param array $labels  host labels
     * @param bool  $convert should we transcode the labels into their ascii equivalent
     *
     * @return array
     */
    protected function convertToAscii(array $labels, $convert)
    {
        if (!$convert) {
            return $labels;
        }

        foreach ($labels as &$label) {
            if ('' !== $label) {
                $label = idn_to_ascii($label);
            }
        }
        unset($label);

        return $labels;
    }

    /**
     * Validate a string only host
     *
     * @param string $str
     *
     * @return array
     */
    protected function validateStringHost($str)
    {
        $host = strtolower($this->setIsAbsolute($str));
        $raw_labels = explode('.', $host);
        $labels = array_map(function ($value) {
            return idn_to_ascii($value);
        }, $raw_labels);

        $this->assertValidHost($labels);
        $this->isIdn = $raw_labels !== $labels;

        return array_reverse(array_map(function ($label) {
            return idn_to_utf8($label);
        }, $labels));
    }

    /**
     * set the FQDN property
     *
     * @param string $str
     *
     * @return string
     */
    abstract protected function setIsAbsolute($str);

    /**
     * Validate a String Label
     *
     * @param array $labels found host labels
     *
     * @throws InvalidArgumentException If the validation fails
     */
    protected function assertValidHost(array $labels)
    {
        $verifs = array_filter($labels, function ($value) {
            return '' !== trim($value);
        });

        if ($verifs !== $labels) {
            throw new InvalidArgumentException('Invalid Hostname, empty labels are not allowed');
        }

        $this->assertLabelsCount($labels);
        $this->isValidContent($labels);
    }

    /**
     * Validated the Host Label Count
     *
     * @param array $labels host labels
     *
     * @throws InvalidArgumentException If the validation fails
     */
    abstract protected function assertLabelsCount(array $labels);

    /**
     * Validated the Host Label Pattern
     *
     * @param array $data host labels
     *
     * @throws InvalidArgumentException If the validation fails
     */
    protected function isValidContent(array $data)
    {
        if (count(preg_grep('/^[0-9a-z]([0-9a-z-]{0,61}[0-9a-z])?$/i', $data, PREG_GREP_INVERT))) {
            throw new InvalidArgumentException('Invalid Hostname, some labels contain invalid characters');
        }
    }
}

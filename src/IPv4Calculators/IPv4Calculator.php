<?php

/**
 * League.Uri (http://uri.thephpleague.com/components)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.0
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\IPv4Calculators;

interface IPv4Calculator
{
    /**
     * Converts a domain name into a IPv4 domain name.
     *
     * @see https://url.spec.whatwg.org/#concept-ipv4-parser
     *
     * If no conversion can be done null is returned.
     */
    public function convert(string $hostString): ?string;

    /**
     * Converts a domain label into a IPv4 integer part.
     *
     * @see https://url.spec.whatwg.org/#ipv4-number-parser
     *
     * @return mixed Returns null if it can not correctly convert the label
     */
    public function labelToNumber(string $label);
}

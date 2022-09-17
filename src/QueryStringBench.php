<?php

/**
 * League.Uri (https://uri.thephpleague.com/components/2.0/)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @link       https://github.com/thephpleague/uri-components
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri;

use PhpBench\Attributes as Bench;

final class QueryStringBench
{
    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 2097152'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchParsingARegularQueryString(): void
    {
        $query = 'module=home&action=show&page=3&module=away&state';
        for ($i = 0; $i < 100000; ++$i) {
            QueryString::parse($query);
        }
    }

    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 2097152'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchExtractingValueFromARegularQueryString(): void
    {
        $query = 'module=home&action=show&page=3&module=away&state';
        for ($i = 0; $i < 100_000; ++$i) {
            QueryString::extract($query);
        }
    }

    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 2097152'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchBuildingARegularQueryString(): void
    {
        $pairs = [['module', 'home'], ['action', 'show'], ['page', 3], ['module', 'away'], ['state', null]];
        for ($i = 0; $i < 100_000; ++$i) {
            QueryString::build($pairs);
        }
    }
}

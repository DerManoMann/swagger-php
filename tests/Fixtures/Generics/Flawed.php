<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Generics;

use OpenApi\Spec as OA;

/**
 * A generic class with members that are reported, once for the class and all its copies.
 *
 * @template T
 */
#[OA\Schema]
class Flawed
{
    /** @var T */
    #[OA\Property]
    public mixed $value;

    /** @var Unlisted */
    #[OA\Property]
    public mixed $missing;

    /** @var Customer<int> */
    #[OA\Property]
    public Customer $refused;
}

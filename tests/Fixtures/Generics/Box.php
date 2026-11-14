<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Generics;

use OpenApi\Spec as OA;

/**
 * A generic class.
 *
 * @template T
 */
#[OA\Schema]
class Box
{
    /** @var T */
    #[OA\Property]
    public mixed $value;

    /** @var list<T> */
    #[OA\Property]
    public array $history = [];

    #[OA\Property]
    public int $count = 0;
}

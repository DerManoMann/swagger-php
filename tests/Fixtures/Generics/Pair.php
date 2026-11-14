<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Generics;

use OpenApi\Spec as OA;

/**
 * A generic class with two templates, one of them used as an argument of another generic class.
 *
 * @template TFirst
 * @template TSecond
 */
#[OA\Schema]
class Pair
{
    /** @var TFirst */
    #[OA\Property]
    public mixed $first;

    /** @var Box<TSecond> */
    #[OA\Property]
    public Box $second;
}

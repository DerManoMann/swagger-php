<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Generics;

use OpenApi\Spec as OA;

/**
 * A generic class using itself with a growing argument.
 *
 * @template T
 */
#[OA\Schema]
class Node
{
    /** @var Node<list<T>>|null */
    #[OA\Property]
    public ?Node $next = null;
}

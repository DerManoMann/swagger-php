<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Generics;

use OpenApi\Spec as OA;

/**
 * Uses of generic classes with type arguments.
 */
#[OA\Schema]
class Uses
{
    /** @var Box<Customer> */
    #[OA\Property]
    public Box $customer;

    /** @var Box<Customer>|null */
    #[OA\Property]
    public ?Box $sameAgain = null;

    /** @var list<Box<int>> */
    #[OA\Property]
    public array $numbers = [];

    /** @var Box<Status> */
    #[OA\Property]
    public Box $status;

    /** @var Pair<string, Customer> */
    #[OA\Property]
    public Pair $pair;

    /** @var Box<Box<bool>> */
    #[OA\Property]
    public Box $nested;

    #[OA\Property]
    public Box $plain;

    /** @var Box<mixed> */
    #[OA\Property]
    public Box $mixed;
}

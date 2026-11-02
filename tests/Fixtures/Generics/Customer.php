<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Generics;

use OpenApi\Spec as OA;

#[OA\Schema]
class Customer
{
    #[OA\Property]
    public int $id;
}

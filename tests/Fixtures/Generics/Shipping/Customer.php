<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Generics\Shipping;

use OpenApi\Spec as OA;

/**
 * A third class named `Customer`, not named by hand, so its schema's name is `Customer` too.
 */
#[OA\Schema]
class Customer
{
    #[OA\Property]
    public string $address;
}

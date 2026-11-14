<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Generics;

use OpenApi\Spec as OA;

/**
 * Two uses whose schemas come out with the same name.
 */
#[OA\Schema]
class Collides
{
    /** @var Box<Customer> */
    #[OA\Property]
    public Box $customer;

    /** @var Box<Billing\Customer> */
    #[OA\Property]
    public Box $billingCustomer;
}

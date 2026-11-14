<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Generics\Billing;

use OpenApi\Spec as OA;

/**
 * A second class named `Customer`, named apart by hand.
 */
#[OA\Schema(component: 'BillingCustomer')]
class Customer
{
    #[OA\Property]
    public int $number;
}

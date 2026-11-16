<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Generics;

use OpenApi\Spec as OA;

/**
 * Two uses with two classes of one short name, one of them named by hand, so the default namer
 * names their schemas apart and a namer that leaves the arguments out does not.
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

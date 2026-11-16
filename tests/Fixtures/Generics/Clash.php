<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Generics;

use OpenApi\Spec as OA;

/**
 * Two uses whose schemas come out with the same name: two classes with one short name, neither
 * named by hand.
 */
#[OA\Schema]
class Clash
{
    /** @var Box<Customer> */
    #[OA\Property]
    public Box $customer;

    /** @var Box<Shipping\Customer> */
    #[OA\Property]
    public Box $shippingCustomer;
}

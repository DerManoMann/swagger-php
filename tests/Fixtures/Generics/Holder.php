<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Generics;

use OpenApi\Spec as OA;

#[OA\Schema]
class Holder
{
    /** @var Envelope<Customer> */
    #[OA\Property]
    public Envelope $customer;

    /** @var Envelope<int> */
    #[OA\Property]
    public Envelope $number;

    /** @var Envelope<Envelope<Customer>> */
    #[OA\Property]
    public Envelope $nested;
}

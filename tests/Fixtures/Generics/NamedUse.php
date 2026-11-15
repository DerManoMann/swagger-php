<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Generics;

use OpenApi\Spec as OA;

/**
 * A use of a generic class named by hand.
 */
#[OA\Schema]
class NamedUse
{
    /** @var Named<Customer> */
    #[OA\Property]
    public Named $parcel;
}

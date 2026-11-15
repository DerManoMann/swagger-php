<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Generics;

use OpenApi\Spec as OA;

/**
 * Uses that are reported: in unions dropped for a class that is no component, and of a generic
 * class whose members are reported.
 */
#[OA\Schema]
class Reported
{
    /** @var Unlisted|list<AlsoUnlisted> */
    #[OA\Property]
    public mixed $nested;

    /** @var Unlisted|Customer<int> */
    #[OA\Property]
    public mixed $refused;

    /** @var Unlisted|Box<Customer> */
    #[OA\Property]
    public mixed $copied;

    /** @var Flawed<int> */
    #[OA\Property]
    public Flawed $int;

    /** @var Flawed<string> */
    #[OA\Property]
    public Flawed $string;
}

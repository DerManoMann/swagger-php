<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Generics;

use OpenApi\Spec as OA;

/**
 * Uses of generic classes that keep the plain schema, each with a warning.
 */
#[OA\Schema]
class Refused
{
    /** @var Box<int, string> */
    #[OA\Property]
    public Box $tooMany;

    /** @var Customer<int> */
    #[OA\Property]
    public Customer $noTemplates;

    /** @var Box<int|string> */
    #[OA\Property]
    public Box $unnamed;
}

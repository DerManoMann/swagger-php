<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Generics;

use OpenApi\Spec as OA;

/**
 * A generic class named by hand.
 *
 * @template T
 */
#[OA\Schema(component: 'Parcel')]
class Named
{
    /** @var T */
    #[OA\Property]
    public mixed $content;
}

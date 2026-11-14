<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Generics;

use OpenApi\Spec as OA;

/**
 * A use of a generic class whose schema asks for itself with a growing argument.
 */
#[OA\Schema]
class Deep
{
    /** @var Node<int> */
    #[OA\Property]
    public Node $node;
}

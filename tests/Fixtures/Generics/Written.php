<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Generics;

use OpenApi\Spec as OA;

/**
 * Generic classes referenced by hand with type arguments, and the refs that keep the plain schema.
 */
#[OA\Schema]
class Written
{
    #[OA\Property(schema: new OA\Schema\Ref(ref: Box::class, of: [Customer::class]))]
    public mixed $customer;

    #[OA\Property(schema: new OA\Schema\Ref(ref: Pair::class, of: ['string', Customer::class]))]
    public mixed $pair;

    #[OA\Property(schema: new OA\Schema(type: 'array', items: new OA\Schema\Ref(ref: Box::class, of: ['int'])))]
    public array $numbers;

    #[OA\Property(schema: new OA\Schema\Ref(ref: Box::class, of: ['Unknown']))]
    public mixed $unreadable;

    #[OA\Property(schema: new OA\Schema\Ref(ref: Box::class, of: ['int', 'string']))]
    public mixed $mismatch;

    #[OA\Property(schema: new OA\Schema\Ref(ref: '#/components/schemas/Box', of: ['int']))]
    public mixed $pointer;
}

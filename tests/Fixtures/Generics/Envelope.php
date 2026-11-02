<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Generics;

use OpenApi\Spec as OA;

/**
 * @template T
 */
#[OA\Schema]
class Envelope
{
    /** @var T */
    #[OA\Property]
    public mixed $data;

    /** @var list<T> */
    #[OA\Property]
    public array $history;

    #[OA\Property]
    public string $version;
}

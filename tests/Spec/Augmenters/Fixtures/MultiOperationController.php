<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Spec\Augmenters\Fixtures;

use OpenApi\Spec as OA;

#[OA\Info(title: 'Multi Op', version: '1.0.0')]
class MultiOperationController
{
    #[OA\Operation(path: '/items', method: 'get', summary: 'List items')]
    #[OA\Response(response: 200, description: 'OK')]
    public function listItems(): void
    {
    }

    #[OA\Operation(path: '/items', method: 'post', summary: 'Create item')]
    #[OA\Response(response: 201, description: 'Created')]
    public function createItem(): void
    {
    }
}

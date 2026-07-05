<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Spec\Fixtures;

use OpenApi\Spec as OA;

#[OA\OpenApi(version: '3.1.0')]
#[OA\Info(title: 'Pet Store', version: '1.0.0')]
#[OA\Tag(name: 'pets', description: 'Pet operations')]
#[OA\Server(url: 'https://api.example.com/v1', description: 'Production')]
#[OA\Schema(
    schema: 'Pet',
    type: 'object',
    required: ['id', 'name'],
    properties: [
        new OA\Property(property: 'id', schema: new OA\Schema(type: 'integer', format: 'int64')),
        new OA\Property(property: 'name', schema: new OA\Schema(type: 'string', maxLength: 255)),
        new OA\Property(property: 'tag', schema: new OA\Schema(type: 'string')),
    ],
)]
class PetStore
{
    #[OA\Operation(path: '/pets', method: 'get', operationId: 'listPets', tags: ['pets'], summary: 'List all pets')]
    #[OA\Parameter(name: 'limit', in: 'query', description: 'How many items to return', required: false, schema: new OA\Schema(type: 'integer', format: 'int32', maximum: 100))]
    #[OA\Response(
        response: 200,
        description: 'A list of pets',
        content: [new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(type: 'array', items: new OA\Schema(ref: '#/components/schemas/Pet')))],
    )]
    #[OA\Response(response: 'default', description: 'Unexpected error')]
    public function listPets(): void
    {
    }
}

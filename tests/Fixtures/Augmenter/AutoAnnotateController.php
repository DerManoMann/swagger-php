<?php declare(strict_types=1);

/*
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Augmenter;

use OpenApi\Spec as OA;

class AutoAnnotateController
{
    #[OA\Operation\Get(path: '/products')]
    #[OA\Response(response: 200, description: 'OK', content: [
        new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(ref: UnannotatedProduct::class)),
    ])]
    public function list()
    {
    }
}

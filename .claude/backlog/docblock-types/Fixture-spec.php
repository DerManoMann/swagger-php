<?php declare(strict_types=1);

/**
 * Spec half of PR 38's reproduction. See README.md.
 */

namespace OpenApi\Backlog\DocblockTypes;

use OpenApi\Spec as OA;

#[OA\Schema(schema: 'Target')]
class TargetSpec
{
    #[OA\Property]
    public string $name;
}

#[OA\Schema(schema: 'Holder')]
class HolderSpec
{
    /** No docblock: the native type is all the resolver has to go on. */
    #[OA\Property]
    public TargetSpec $native;

    /** @var TargetSpec<string> */
    #[OA\Property]
    public TargetSpec $generic;

    /** @var TargetSpec */
    #[OA\Property]
    public TargetSpec $docblock;
}

#[OA\Info(title: 'PR 38', version: '1.0')]
#[OA\Operation\Get(path: '/holder', operationId: 'holder')]
#[OA\Response(response: 200, description: 'ok', content: [
    new OA\MediaType\Json(ref: HolderSpec::class),
])]
class ControllerSpec
{
}

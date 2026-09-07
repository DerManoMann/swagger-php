<?php declare(strict_types=1);

/**
 * Classic half of PR 38's reproduction. See README.md.
 */

namespace OpenApi\Backlog\DocblockTypes;

use OpenApi\Attributes as OAT;

#[OAT\Schema(schema: 'Target')]
class Target
{
    #[OAT\Property]
    public string $name;
}

#[OAT\Schema(schema: 'Holder')]
class Holder
{
    /** No docblock: the native type is all the resolver has to go on. */
    #[OAT\Property]
    public Target $native;

    /** @var Target<string> */
    #[OAT\Property]
    public Target $generic;

    /** @var Target */
    #[OAT\Property]
    public Target $docblock;
}

#[OAT\Info(title: 'PR 38', version: '1.0')]
#[OAT\Get(path: '/holder', operationId: 'holder', responses: [
    new OAT\Response(response: 200, description: 'ok', content: new OAT\JsonContent(ref: Holder::class)),
])]
class Controller
{
}

<?php

namespace Openapi\Snippets\Cookbook\DefaultSecurity;

use OpenApi\Spec as OA;

#[OA\Security\Scheme\Http(securityScheme: 'bearerAuth')]
class OpenApiSpec
{
}

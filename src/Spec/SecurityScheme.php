<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
#[AllowedParents]
class SecurityScheme extends AbstractAttribute
{
    /**
     * @param list<Flow>|null          $flows
     * @param array<string,mixed>|null $x
     * @param list<object>|null        $attachables
     */
    public function __construct(
        public ?string $securityScheme = null,
        public ?string $ref = null,
        public ?string $type = null,
        public ?string $description = null,
        public ?string $name = null,
        public ?string $in = null,
        public ?string $scheme = null,
        public ?string $bearerFormat = null,
        public ?string $openIdConnectUrl = null,
        public ?array $flows = null,
        ?array $x = null,
        array $attachables = [],
    ) {
        parent::__construct(attachables: $attachables, x: $x);
    }
}

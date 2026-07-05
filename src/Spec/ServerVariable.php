<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
#[AllowedParents(Server::class)]
class ServerVariable extends AbstractAttribute
{
    /**
     * @param list<string>|null        $enum
     * @param array<string,mixed>|null $x
     * @param list<Attachable>         $attachables
     */
    public function __construct(
        public ?string $serverVariable = null,
        public ?string $default = null,
        public ?string $description = null,
        public ?array $enum = null,
        ?array $x = null,
        array $attachables = [],
    ) {
        parent::__construct(attachables: $attachables, x: $x);
    }
}

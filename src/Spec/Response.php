<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
#[AllowedParents(Operation::class)]
class Response extends AbstractAttribute
{
    /**
     * @param list<Header>|null        $headers
     * @param list<MediaType>|null     $content
     * @param list<Link>|null          $links
     * @param array<string,mixed>|null $x
     * @param list<object>|null        $attachables
     */
    public function __construct(
        public string|int|null $response = null,
        public ?string $description = null,
        public ?string $ref = null,
        public ?array $headers = null,
        public ?array $content = null,
        public ?array $links = null,
        ?array $x = null,
        array $attachables = [],
    ) {
        parent::__construct(attachables: $attachables, x: $x);
    }
}

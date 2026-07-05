<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

/**
 * Restricts which parent attributes an Attachable subclass can nest into.
 *
 * Absent    → unrestricted (any parent)
 * Present   → restricted to listed types
 * Empty     → no valid parents (root-level only)
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AllowedParents
{
    /** @var list<class-string<OpenApiAttributeInterface>> */
    public readonly array $parents;

    /**
     * @param class-string<OpenApiAttributeInterface> ...$parents
     */
    public function __construct(string ...$parents)
    {
        $this->parents = $parents;
    }
}

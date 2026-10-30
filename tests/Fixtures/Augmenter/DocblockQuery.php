<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Augmenter;

/**
 * Query parameters as a DTO; an integration maps each property to a parameter.
 */
class DocblockQuery
{
    /**
     * The page to return.
     *
     * @example 2
     */
    public int $page;

    /**
     * @var string|null the search term
     */
    public ?string $term;

    /**
     * @deprecated use page
     */
    public ?int $offset;

    /**
     * The page size.
     *
     * @example 10
     */
    public int $limit;

    public function __construct(
        /**
         * The sort order.
         *
         * @example desc
         */
        public string $sort = 'asc',
    ) {
    }
}

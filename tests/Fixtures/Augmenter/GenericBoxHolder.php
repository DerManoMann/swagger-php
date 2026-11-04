<?php declare(strict_types=1);

/*
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Augmenter;

/**
 * Uses of a generic class.
 */
class GenericBoxHolder
{
    /** @var GenericBox<int> */
    public GenericBox $box;

    /** @var GenericBox<RefTarget> */
    public GenericBox $undescribed;

    /**
     * @return GenericBox<string>
     */
    public function boxed(): GenericBox
    {
        return new GenericBox();
    }
}

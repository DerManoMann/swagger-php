<?php declare(strict_types=1);

/*
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Fixtures\Augmenter;

class UnannotatedProduct
{
    public function __construct(
        public string $name,
        public float $price,
        public UnannotatedAddress $address,
    ) {
    }
}

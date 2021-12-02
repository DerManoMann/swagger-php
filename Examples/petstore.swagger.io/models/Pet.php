<?php

namespace OpenApi\Examples\PetstoreSwaggerIo;

/**
 * @OA\Schema(@OA\Xml(name="Pet"))
 */
class Pet
{

    /**
     * @OA\Property(format="int64")
     * @var int
     */
    public $id;

    /**
     * @OA\Property(required=true, example="doggie")
     * @var string
     */
    public $name;

    /**
     * @var Category
     * @OA\Property()
     */
    public $category;

    /**
     * @var string[]
     * @OA\Property(required=true, @OA\Xml(name="photoUrl", wrapped=true))
     */
    public $photoUrls;

    /**
     * @var Tag[]
     * @OA\Property(@OA\Xml(name="tag", wrapped=true))
     */
    public $tags;

    /**
     * pet status in the store
     * @var string
     * @OA\Property(enum={"available", "pending", "sold"})
     */
    public $status;
}

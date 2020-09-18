<?php

/**
 * Single file API using PHP8 attributes.
 */

namespace OpenApi\Tests\Fixtures\Apis;

use OpenApi\Attributes as OT;

#[OT\Info(version:'1.0.0', title:'Basic single file PHP8 API')]
class Php8Api
{

}

interface Php8ProductInterface {

}

#[OT\Schema([])]
trait Php8NameTrait {
    /**
     * The name.
     */
    #[OT\Property([])]
    public $name;

}

#[OT\Schema(['title' => 'Product', 'description' => 'Product'])]
class Php8Product implements Php8ProductInterface
{
    use Php8NameTrait;

    /**
     * The id.
     */
    #[OT\Property(['format' => 'int64', 'example' => 1])]
    public $id;
}

class Php8ProductController
{

    #[OT\Get(['tags' => ['Products'], 'path' => '/products/{product_id}'])]
    #[OT\Response(['response' => 200, 'description' => 'successful operation'])]
    #[OT\Response(['response' => 401, 'description' => 'oops'])]
    public function getProduct($id)
    {
    }
}

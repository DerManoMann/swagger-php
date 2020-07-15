<?php

/**
 * Single file API using PHP8 attributes.
 */

namespace OpenApi\Tests\Fixtures\Apis;

use OpenApi\Attributes as OAT;

<<OAT\Info(['version' => '1.0.0', 'title' => 'Basic single file PHP8 API'])>>
class Php8Api
{

}

interface Php8ProductInterface {

}

<<OAT\Schema([])>>
trait Php8NameTrait {
    /**
     * The name.
     */
    <<OAT\Property([])>>
    public $name;

}

<<OAT\Schema(['title' => 'Product', 'description' => 'Product'])>>
class Php8Product implements Php8ProductInterface
{
    use Php8NameTrait;

    /**
     * The id.
     */
    <<OAT\Property(['format' => 'int64', 'example' => 1])>>
    public $id;
}

class Php8ProductController
{

    <<OAT\Get(['tags' => ['Products'], 'path' => '/products/{product_id}'])>>
        <<OAT\Response(['response' => 200, 'description' => 'successful operation'])>>
        <<OAT\Response(['response' => 401, 'description' => 'oops'])>>
    public function getProduct($id)
    {
    }
}

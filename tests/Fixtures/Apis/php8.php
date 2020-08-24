<?php

namespace OpenApi\Tests\Fixtures\Apis;

use OpenApi\Analysis;
use OpenApi\Context;
use OpenApi\Attributes as OA;

/* ====== APP =================== */

/**
 * @OA\Info(
 *   version="1.0.0",
 *   title="Basic single file API",
 * )
 */
@@OA\Info(['version' => '1.0.0', 'title' => 'Basic single file PHP8 API'])
class Php8Api
{

}

interface Php8SomeInterface
{
}

trait Php8OtherTrait
{

}

trait Php8IdTrait
{
    use Php8OtherTrait;

    @@Property('id')
    public function getId()
    {
        return $this->id;
    }
}

class Php8Model
{

}

class Php8Pet extends Php8Model implements Php8SomeInterface
{
    use Php8IdTrait;

    protected $id;
}



/* ====== OA =================== */


class OpenApiAttribute
{
    public function __construct(public $name)
    {
    }

    public function __toString(): string
    {
        return '['.$this::class . ': name='.$this->name.']';
    }
}

class Operation extends OpenApiAttribute
{
    public function __construct($name, public  $method)
    {
        parent::__construct('O:'.$name);
    }

    public function __toString(): string
    {
        return '['.$this::class . ': name='.$this->name.', method='.$this->method.']';
    }
}

@@\Attribute(\Attribute::TARGET_METHOD|\Attribute::TARGET_PROPERTY)
class Property extends OpenApiAttribute
{
    public function __construct($name)
    {
        parent::__construct($name);
    }
}

@@\Attribute(\Attribute::TARGET_METHOD)
class Get extends Operation
{
    public function __construct($name)
    {
        parent::__construct('G:'.$name, 'GET');
    }
}

class Php8Controller
{
    @@Get("foo")
    public function getPets()
    {
    }
}

<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Spec;

use OpenApi\Spec\Assembler;
use OpenApi\Spec\Info;
use OpenApi\Spec\MediaType;
use OpenApi\Spec\Operation;
use OpenApi\Spec\Parameter;
use OpenApi\Spec\Property;
use OpenApi\Spec\Response;
use OpenApi\Spec\Schema;
use OpenApi\Spec\Server;
use OpenApi\Spec\Tag;
use OpenApi\Tests\Spec\Fixtures\PetStore;
use PHPUnit\Framework\TestCase;

class AssemblerTest extends TestCase
{
    public function testPetStoreAssembly(): void
    {
        $assembler = new Assembler();
        $assembler->collect(new \ReflectionClass(PetStore::class));

        $spec = $assembler->getSpecification();

        $this->assertNotNull($spec->openapi);
        $this->assertSame('3.1.0', $spec->openapi->version);

        // Info
        $this->assertInstanceOf(Info::class, $spec->info);
        $this->assertSame('Pet Store', $spec->info->title);
        $this->assertSame('1.0.0', $spec->info->version);

        // Tags
        $this->assertCount(1, $spec->tags);
        $this->assertInstanceOf(Tag::class, $spec->tags[0]);
        $this->assertSame('pets', $spec->tags[0]->name);
        $this->assertSame('Pet operations', $spec->tags[0]->description);

        // Servers
        $this->assertCount(1, $spec->servers);
        $this->assertInstanceOf(Server::class, $spec->servers[0]);
        $this->assertSame('https://api.example.com/v1', $spec->servers[0]->url);
        $this->assertSame('Production', $spec->servers[0]->description);

        // Schemas
        $this->assertCount(1, $spec->schemas);
        $pet = $spec->schemas[0];
        $this->assertInstanceOf(Schema::class, $pet);
        $this->assertSame('Pet', $pet->schema);
        $this->assertSame('object', $pet->type);
        $this->assertSame(['id', 'name'], $pet->required);
        $this->assertCount(3, $pet->properties);

        // Schema properties
        $this->assertInstanceOf(Property::class, $pet->properties[0]);
        $this->assertSame('id', $pet->properties[0]->property);
        $this->assertInstanceOf(Schema::class, $pet->properties[0]->schema);
        $this->assertSame('integer', $pet->properties[0]->schema->type);
        $this->assertSame('int64', $pet->properties[0]->schema->format);

        $this->assertInstanceOf(Property::class, $pet->properties[1]);
        $this->assertSame('name', $pet->properties[1]->property);
        $this->assertSame('string', $pet->properties[1]->schema->type);
        $this->assertSame(255, $pet->properties[1]->schema->maxLength);

        $this->assertInstanceOf(Property::class, $pet->properties[2]);
        $this->assertSame('tag', $pet->properties[2]->property);
        $this->assertSame('string', $pet->properties[2]->schema->type);

        // Operations
        $this->assertCount(1, $spec->operations);
        $op = $spec->operations[0];
        $this->assertInstanceOf(Operation::class, $op);
        $this->assertSame('/pets', $op->path);
        $this->assertSame('get', $op->method);
        $this->assertSame('listPets', $op->operationId);
        $this->assertSame(['pets'], $op->tags);
        $this->assertSame('List all pets', $op->summary);

        // Parameters nested into operation
        $this->assertCount(1, $op->parameters);
        $param = $op->parameters[0];
        $this->assertInstanceOf(Parameter::class, $param);
        $this->assertSame('limit', $param->name);
        $this->assertSame('query', $param->in);
        $this->assertSame('How many items to return', $param->description);
        $this->assertFalse($param->required);
        $this->assertInstanceOf(Schema::class, $param->schema);
        $this->assertSame('integer', $param->schema->type);
        $this->assertSame('int32', $param->schema->format);
        $this->assertSame(100, $param->schema->maximum);

        // Responses nested into operation
        $this->assertCount(2, $op->responses);
        $this->assertInstanceOf(Response::class, $op->responses[0]);
        $this->assertSame(200, $op->responses[0]->response);
        $this->assertSame('A list of pets', $op->responses[0]->description);
        $this->assertCount(1, $op->responses[0]->content);
        $this->assertInstanceOf(MediaType::class, $op->responses[0]->content[0]);
        $this->assertSame('application/json', $op->responses[0]->content[0]->mediaType);
        $this->assertInstanceOf(Schema::class, $op->responses[0]->content[0]->schema);
        $this->assertSame('array', $op->responses[0]->content[0]->schema->type);
        $this->assertInstanceOf(Schema::class, $op->responses[0]->content[0]->schema->items);
        $this->assertSame('#/components/schemas/Pet', $op->responses[0]->content[0]->schema->items->ref);

        $this->assertInstanceOf(Response::class, $op->responses[1]);
        $this->assertSame('default', $op->responses[1]->response);
        $this->assertSame('Unexpected error', $op->responses[1]->description);
    }
}

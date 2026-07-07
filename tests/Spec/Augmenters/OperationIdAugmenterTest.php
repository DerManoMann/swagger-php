<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Spec\Augmenters;

use OpenApi\Spec\Assembler;
use OpenApi\Spec\Augmenters\OperationIdAugmenter;
use OpenApi\Tests\Spec\Fixtures\PetStore;
use PHPUnit\Framework\TestCase;

class OperationIdAugmenterTest extends TestCase
{
    public function testSkipsExplicitOperationId(): void
    {
        $assembler = new Assembler();
        $assembler->collect(new \ReflectionClass(PetStore::class));
        $spec = $assembler->getSpecification();

        $this->assertSame('listPets', $spec->operations[0]->operationId);

        $augmenter = new OperationIdAugmenter(hash: false);
        $augmenter->augment($spec);

        $this->assertSame('listPets', $spec->operations[0]->operationId);
    }

    public function testGeneratesOperationIdFromReflector(): void
    {
        $assembler = new Assembler();
        $assembler->collect(new \ReflectionClass(PetStore::class));
        $spec = $assembler->getSpecification();

        // Clear the explicit operationId to trigger generation
        $spec->operations[0]->operationId = null;

        $augmenter = new OperationIdAugmenter(hash: false);
        $augmenter->augment($spec);

        $expected = 'GET::/pets::' . PetStore::class . '::listPets';
        $this->assertSame($expected, $spec->operations[0]->operationId);
    }

    public function testGeneratesHashedOperationId(): void
    {
        $assembler = new Assembler();
        $assembler->collect(new \ReflectionClass(PetStore::class));
        $spec = $assembler->getSpecification();

        $spec->operations[0]->operationId = null;

        $augmenter = new OperationIdAugmenter(hash: true);
        $augmenter->augment($spec);

        $expected = md5('GET::/pets::' . PetStore::class . '::listPets');
        $this->assertSame($expected, $spec->operations[0]->operationId);
    }

    public function testMultipleOperations(): void
    {
        $assembler = new Assembler();
        $assembler->collect(new \ReflectionClass(Fixtures\MultiOperationController::class));
        $spec = $assembler->getSpecification();

        $augmenter = new OperationIdAugmenter(hash: false);
        $augmenter->augment($spec);

        $this->assertCount(2, $spec->operations);

        $expected1 = 'GET::/items::' . Fixtures\MultiOperationController::class . '::listItems';
        $expected2 = 'POST::/items::' . Fixtures\MultiOperationController::class . '::createItem';
        $this->assertSame($expected1, $spec->operations[0]->operationId);
        $this->assertSame($expected2, $spec->operations[1]->operationId);
    }
}

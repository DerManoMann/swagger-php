<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Augmenter;

use OpenApi\Augmenter;
use OpenApi\Tests\Concerns\AssemblesSpecification;
use OpenApi\Tests\Fixtures;
use PHPUnit\Framework\TestCase;

final class OperationInheritanceTest extends TestCase
{
    use AssemblesSpecification;

    public function testInheritedOperationClonedToChild(): void
    {
        $spec = $this->assemble(
            Fixtures\Augmenter\AbstractDocumentController::class,
            Fixtures\Augmenter\InvoiceDocumentController::class,
        );

        $this->assertCount(1, $spec->operations, 'Assembled from abstract parent');
        $this->assertSame(
            Fixtures\Augmenter\AbstractDocumentController::class,
            $spec->operations[0]->getClassName(),
        );

        (new Augmenter\OperationInheritance())($spec);

        $this->assertCount(1, $spec->operations, 'Still one operation (original replaced by clone)');
        $this->assertSame(
            Fixtures\Augmenter\InvoiceDocumentController::class,
            $spec->operations[0]->getClassName(),
            'Operation now associated with child class',
        );
    }

    public function testPathItemPrefixAppliedToInheritedOperation(): void
    {
        $spec = $this->assemble(
            Fixtures\Augmenter\AbstractDocumentController::class,
            Fixtures\Augmenter\InvoiceDocumentController::class,
        );

        (new Augmenter\OperationInheritance())($spec);
        (new Augmenter\PathItems())($spec);

        $this->assertSame('/invoices', $spec->operations[0]->path);
    }

    public function testTagsClonedToInheritedOperation(): void
    {
        $spec = $this->assemble(
            Fixtures\Augmenter\AbstractDocumentController::class,
            Fixtures\Augmenter\InvoiceDocumentController::class,
        );

        (new Augmenter\OperationInheritance())($spec);
        (new Augmenter\PathItems())($spec);

        $this->assertSame(['Invoices'], $spec->operations[0]->tags);
    }

    public function testDirectOperationsNotDuplicated(): void
    {
        $spec = $this->assemble(
            Fixtures\Augmenter\PathItemBaseController::class,
            Fixtures\Augmenter\PathItemUserController::class,
        );

        $countBefore = count($spec->operations);

        (new Augmenter\OperationInheritance())($spec);

        $this->assertCount($countBefore, $spec->operations, 'Already-collected operations not duplicated');
    }
}

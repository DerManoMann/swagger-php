<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Utils;

use OpenApi\Tests\Concerns\ExpectsLoggerContains;
use OpenApi\Tests\Concerns\UsesExamples;
use OpenApi\Utils\SourceFinder;
use OpenApi\Utils\SourceScanner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SourceScannerTest extends TestCase
{
    use ExpectsLoggerContains;
    use UsesExamples;

    public static function sourcesProvider(): iterable
    {
        $sourceDir = self::examplePath('petstore/annotations');

        yield 'dir-string' => [[$sourceDir]];
        yield 'finder' => [new SourceFinder($sourceDir)];
        yield 'finder-list' => [[new SourceFinder($sourceDir)]];
    }

    #[DataProvider('sourcesProvider')]
    public function testScan(iterable $sources): void
    {
        $scanner = new SourceScanner(new NullLogger());
        $files = $scanner->scan($sources);

        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $this->assertFileExists($file);
            $this->assertStringEndsWith('.php', $file);
        }
    }

    public function testScanInvalidSource(): void
    {
        $this->expectLoggerContains('Skipping invalid source: /tmp/__swagger_php_does_not_exist__');

        $scanner = new SourceScanner($this->getAssertingLogger());
        $files = $scanner->scan(['/tmp/__swagger_php_does_not_exist__']);

        $this->assertEmpty($files);
    }

    public function testScanNestedIterables(): void
    {
        $sourceDir = self::examplePath('petstore/annotations');
        $nested = [[new SourceFinder($sourceDir)]];

        $scanner = new SourceScanner(new NullLogger());
        $files = $scanner->scan($nested);

        $this->assertNotEmpty($files);
    }

    public function testScanSplFileInfo(): void
    {
        $sourceDir = self::examplePath('petstore/annotations');
        $finder = new SourceFinder($sourceDir);
        $splFiles = iterator_to_array($finder);
        $first = reset($splFiles);

        $scanner = new SourceScanner(new NullLogger());
        $files = $scanner->scan([$first]);

        $this->assertCount(1, $files);
        $this->assertFileExists($files[0]);
    }
}

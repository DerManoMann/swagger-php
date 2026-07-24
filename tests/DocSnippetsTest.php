<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests;

use OpenApi\Attributes\OpenApi;
use OpenApi\Builder;
use OpenApi\Builder\Mode;
use OpenApi\Generator;
use OpenApi\Processors\OperationId;
use OpenApi\Tests\Concerns\AssertsSchemaStructure;
use OpenApi\Tests\Concerns\UsesExamples;
use OpenApi\Utils\Pipeline;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Finder\Finder;

final class DocSnippetsTest extends OpenApiTestCase
{
    use UsesExamples;

    public static function snippetSets(): iterable
    {
        $finder = (new Finder())
            ->in(__DIR__ . '/../docs/snippets/')
            ->name('*.php');

        foreach ($finder as $anFile) {
            if (str_ends_with($anFile->getPathname(), '_an.php')) {
                $atFile = str_replace('_an.php', '_at.php', $anFile->getPathname());
                $specFile = str_replace('_an.php', '_spec.php', $anFile->getPathname());

                $snippets = [
                    'an' => $anFile->getPathname(),
                    'at' => $atFile,
                    'spec' => $specFile,
                ];

                $key = str_replace('_an', '', $anFile->getBasename('.php'));
                foreach ([OpenApi::VERSION_3_0_0, OpenApi::VERSION_3_1_0, OpenApi::VERSION_3_2_0] as $version) {
                    yield "{$key}-classic-{$version}" => [$snippets, Mode::CLASSIC, $version];
                    yield "{$key}-hybrid-{$version}" => [$snippets, Mode::HYBRID, $version];
                }
            }
        }
    }

    /**
     * Compare snippets and ensure they result in the same spec fragment.
     */
    #[DataProvider('snippetSets')]
    public function testSnippets(array $filenames, Mode $mode, string $version): void
    {
        $lastANSpec = null;
        foreach ($filenames as $type => $filename) {
            if (!file_exists($filename)) {
                continue;
            }
echo $filename . "\n";
            $contents = preg_replace('/(namespace [^;]+);/', "\\1\\{$type};", file_get_contents($filename));
            $namespace = basename((string) $filename, '.php');

            $snippet = sys_get_temp_dir() . "/{$namespace}.php";
            file_put_contents($snippet, $contents);
            require_once $snippet;

            $result = (new Builder())
                ->setMode($type === 'spec' ? Mode::SPEC : $mode)
                ->setVersion($version)
                ->withGenerator(function (Generator $generator) use ($snippet) {
                    $generator
                        ->setTypeResolver($this->getTypeResolver())
                        ->withProcessorPipeline(fn (Pipeline $processorPipeline): Pipeline => $processorPipeline->remove(OperationId::class))
                        ->generate([$snippet], null, false);
                })
            ->build();

            $this->assertTrue($result->isValid());
            if ($type === 'an') {
                $lastANSpec = $result->toArray();
                echo 'last' . PHP_EOL;
            } else {
                $this->assertSpecEquals($result->toArray(), $lastANSpec);
                echo 'compare' . PHP_EOL;
            }
        }
    }
}

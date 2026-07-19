<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Concerns;

use OpenApi\Assembler;
use OpenApi\Specification;
use OpenApi\Tests\OpenApiTestCase;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

trait ExpectsLoggerContains
{
    public static array $expectedLoggerMessages = [];

    public function expectLoggerContains(string|\Throwable $needle, string $message = ''): void
    {
        static::$expectedLoggerMessages[] = [function ($logLine) use ($needle, $message): void {
            if ($logLine instanceof \Throwable) {
                $logLine = $logLine->getMessage();
            }
            $this->assertStringContainsString($needle, $logLine, $message);
        }, $needle];
    }

    public function getAssertingLogger(?LoggerInterface $delegate = null): LoggerInterface
    {
        return new class ($this, $delegate) extends AbstractLogger {
            public function __construct(protected TestCase $testCase, protected ?LoggerInterface $delegate)
            {
            }

            public function log($level, $message, array $context = []): void
            {
                $this->delegate?->log($level, $message, $context);

                if (count($this->testCase::$expectedLoggerMessages)) {
                    [$assertion] = array_shift($this->testCase::$expectedLoggerMessages);
                    $assertion($message, $level);
                } else {
                    $this->testCase::fail('Unexpected log line: ' . $level . '("' . $message . '")');
                }
            }
        };
    }
}

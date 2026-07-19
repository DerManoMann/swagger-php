<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Concerns;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

trait ExpectsLoggerContains
{
    public static array $expectedLoggerMessages = [];

    public static array $actualLoggerMessages = [];

    public function expectLoggerContains(string|\Throwable $needle, string $message = ''): void
    {
        static::$expectedLoggerMessages[] = [$needle, $message];
    }

    public function getAssertingLogger(?LoggerInterface $delegate = null): LoggerInterface
    {
        $testCase = $this;

        return new class ($testCase, $delegate) extends AbstractLogger {
            public function __construct(protected $testCase, protected ?LoggerInterface $delegate)
            {
            }

            public function log($level, $message, array $context = []): void
            {
                $this->delegate?->log($level, $message, $context);

                $this->testCase::$actualLoggerMessages[] = [$level, $message, $context];
            }
        };
    }
}

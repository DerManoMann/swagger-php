<?php declare(strict_types=1);

/*
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Concerns\Subscribers;

use OpenApi\Tests\Concerns\ExpectsLoggerContains;
use PHPUnit\Event\Test\AfterTestMethodCalled;
use PHPUnit\Event\Test\AfterTestMethodCalledSubscriber;
use PHPUnit\Event\Test\BeforeTestMethodCalled;
use PHPUnit\Event\Test\BeforeTestMethodCalledSubscriber;
use PHPUnit\Framework\TestCase;

class ExpectsLoggerContainsSubscriber
{
    public static function subscribers(): array
    {
        $subscriber = new ExpectsLoggerContainsSubscriber();

        return [
            new class ($subscriber) implements BeforeTestMethodCalledSubscriber {
                public function __construct(protected ExpectsLoggerContainsSubscriber $subscriber)
                {
                }

                public function notify(BeforeTestMethodCalled $event): void
                {
                    /** @var class-string<TestCase> $testCase */
                    $testCase = $event->test()->className();

                    if (in_array(ExpectsLoggerContains::class, class_uses($testCase))) {
                        $this->subscriber->beforeTestMethodCalled($event);
                    }
                }
            },
            new class ($subscriber) implements AfterTestMethodCalledSubscriber {
                public function __construct(protected ExpectsLoggerContainsSubscriber $subscriber)
                {
                }

                public function notify(AfterTestMethodCalled $event): void
                {
                    /** @var class-string<TestCase> $testCase */
                    $testCase = $event->test()->className();

                    if (in_array(ExpectsLoggerContains::class, class_uses($testCase))) {
                        $this->subscriber->afterTestMethodCalled($event);
                    }
                }
            },
        ];
    }

    public function beforeTestMethodCalled(BeforeTestMethodCalled $event): void
    {
        /** @var class-string<TestCase> $testCase */
        $testCase = $event->test()->className();

        $testCase::$expectedLoggerMessages = [];
        $testCase::$actualLoggerMessages = [];
    }

    public function afterTestMethodCalled(AfterTestMethodCalled $event): void
    {
        /** @var class-string<TestCase> $testCase */
        $testCase = $event->test()->className();

        /** @var array{0:string,1:string} $expected needle, message */
        foreach ($testCase::$expectedLoggerMessages as $index => $expected) {
            if (isset($testCase::$actualLoggerMessages[$index])) {
                /** @var array{0:string,1:string,2:array} $actual level, logLine, context */
                $actual = $testCase::$actualLoggerMessages[$index];
                $testCase::assertStringContainsString(
                    $expected[0],
                    $actual[1],
                    empty($expected[1]) ? 'AssertingLogger: message not logged: "' . $expected[0] . '"' : $expected[1]
                );
                $testCase::$expectedLoggerMessages[$index] = null;
            }
        }

        $testCase::$expectedLoggerMessages = array_filter($testCase::$expectedLoggerMessages);

        $testCase::assertEmpty(
            $testCase::$expectedLoggerMessages,
            implode(PHP_EOL . '  => ', array_merge(
                ['AssertingLogger: expected messages were not logged:'],
                array_map(fn (array $value) => $value[0], $testCase::$expectedLoggerMessages)
            ))
        );
    }
}

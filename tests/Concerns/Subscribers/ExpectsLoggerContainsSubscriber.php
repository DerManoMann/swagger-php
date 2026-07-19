<?php declare(strict_types=1);

namespace OpenApi\Tests\Concerns\Subscribers;

use OpenApi\Tests\Concerns\ExpectsLoggerContains;
use PHPUnit\Event\Test\AfterTestMethodCalled;
use PHPUnit\Event\Test\AfterTestMethodCalledSubscriber;
use PHPUnit\Event\Test\BeforeTestMethodCalled;
use PHPUnit\Event\Test\BeforeTestMethodCalledSubscriber;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;
use Psr\Log\LoggerInterface;

class ExpectsLoggerContainsSubscriber
{
    public static function subscribers(): array
    {
        $subscriber =new ExpectsLoggerContainsSubscriber();
        return [
            new class($subscriber) implements BeforeTestMethodCalledSubscriber
            {
                public function __construct(protected ExpectsLoggerContainsSubscriber $subscriber)
                {
                }

                public function notify(BeforeTestMethodCalled $event): void
                {
                    $this->subscriber->beforeTestMethodCalled($event);
                }
            },
            new class($subscriber) implements AfterTestMethodCalledSubscriber
            {
                public function __construct(protected ExpectsLoggerContainsSubscriber $subscriber)
                {
                }

                public function notify(AfterTestMethodCalled $event): void
                {
                    $this->subscriber->afterTestMethodCalled($event);
                }
            },
        ];
    }

    public function beforeTestMethodCalled(BeforeTestMethodCalled $event): void
    {
        $testClass = $event->test()->className();
        if (in_array(ExpectsLoggerContains::class,  class_uses($testClass))) {
            $testClass::$expectedLoggerMessages = [];
        }
    }

    public function afterTestMethodCalled(AfterTestMethodCalled $event): void
    {
        $testClass = $event->test()->className();
        $testClass::assertEmpty(
            $testClass::$expectedLoggerMessages,
            implode(PHP_EOL . '  => ', array_merge(
                ['AssertingLogger messages were not triggered:'],
                array_map(fn (array $value) => $value[1], $testClass::$expectedLoggerMessages)
            ))
        );
    }
}

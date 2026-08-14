<?php

namespace Cube\Tests\Units\Routine;

use Cube\Env\Logger\Logger;
use Cube\Env\Logger\NullLogger;
use Cube\Routine\CronExpression;
use Cube\Routine\Scheduler;
use Cube\Tests\Units\Env\Classes\SpyLogger;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class SchedulerTest extends TestCase
{
    public function testAddIsFluent()
    {
        $scheduler = new Scheduler();

        $this->assertSame($scheduler, $scheduler->add(CronExpression::everyMinute(), fn () => null));
    }

    public function testOnlyTheMatchingHandlersAreLaunched()
    {
        $launched = [];
        $scheduler = new Scheduler();

        $scheduler->add(new CronExpression('0 12 * * *'), function () use (&$launched) { $launched[] = 'noon'; });
        $scheduler->add(new CronExpression('0 0 * * *'), function () use (&$launched) { $launched[] = 'midnight'; });

        $scheduler->launch('2026-08-14 12:00:00');

        $this->assertEquals(['noon'], $launched);
    }

    public function testLaunchingWithNothingScheduledDoesNothing()
    {
        (new Scheduler())->launch('2026-08-14 12:00:00');

        $this->expectNotToPerformAssertions();
    }

    /**
     * The routine command runs once a minute : a task that throws used to cancel every task
     * declared after it.
     */
    public function testAFailingTaskDoesNotCancelTheOthers()
    {
        $launched = [];
        $scheduler = new Scheduler();

        $scheduler->add(CronExpression::everyMinute(), fn () => throw new \RuntimeException('boom'));
        $scheduler->add(CronExpression::everyMinute(), function () use (&$launched) { $launched[] = 'second'; });
        $scheduler->add(CronExpression::everyMinute(), function () use (&$launched) { $launched[] = 'third'; });

        Logger::withInstance(new NullLogger(), fn () => $scheduler->launch('2026-08-14 12:00:00'));

        $this->assertEquals(['second', 'third'], $launched);
    }

    public function testAFailingTaskIsReported()
    {
        $spy = new SpyLogger();
        $scheduler = new Scheduler();

        $scheduler->add(CronExpression::everyMinute(), fn () => throw new \RuntimeException('boom'));

        Logger::withInstance($spy, fn () => $scheduler->launch('2026-08-14 12:00:00'));

        $this->assertNotEmpty($spy->records);
        $this->assertEquals(['error'], array_unique($spy->levels()));
    }
}

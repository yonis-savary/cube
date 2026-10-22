<?php

namespace Cube\Tests\Units\Routine;

use Cube\Env\Logger\Logger;
use Cube\Routine\CronExpression;
use Cube\Routine\Scheduler;
use Cube\Tests\Units\Env\Classes\SpyLogger;
use Cube\Tests\Units\Routine\Classes\InvokableTask;
use Cube\Tests\Units\Routine\Classes\RecordedTasks;
use Cube\Tests\Units\Routine\Classes\StartRecordingScheduler;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class SchedulerTest extends TestCase
{
    protected function setUp(): void
    {
        RecordedTasks::$calls = [];
        RecordedTasks::$nestedRunResult = null;
    }

    public function testAddIsFluent()
    {
        $scheduler = new Scheduler();

        $this->assertSame($scheduler, $scheduler->add(CronExpression::everyMinute(), InvokableTask::class));
    }

    public static function getInvalidTasks(): array
    {
        return [
            'unknown class' => ['App\\Nowhere'],
            'not invokable' => [RecordedTasks::class],
            'unknown method' => [[RecordedTasks::class, 'unknown']],
            'private method' => [[RecordedTasks::class, 'hiddenTask']],
            'not a pair' => [[RecordedTasks::class]],
        ];
    }

    /** A typo in a schedule file only showed when the task was due, inside a background process. */
    #[DataProvider('getInvalidTasks')]
    public function testAnInvalidTaskIsRefusedWhenScheduled(array|string $task)
    {
        $this->expectException(InvalidArgumentException::class);
        (new Scheduler())->add(CronExpression::everyMinute(), $task);
    }

    #[DataProvider('getInvalidTasks')]
    public function testRunRefusesAnInvalidTask(array|string $task)
    {
        $this->expectException(InvalidArgumentException::class);
        (new Scheduler())->run($task);
    }

    public function testOnlyTheMatchingTasksAreStarted()
    {
        $scheduler = new StartRecordingScheduler();
        $scheduler->add(new CronExpression('0 12 * * *'), [RecordedTasks::class, 'staticTask']);
        $scheduler->add(new CronExpression('0 0 * * *'), InvokableTask::class, lock: false);

        $scheduler->launch('2026-08-14 12:00:00');
        $this->assertEquals([RecordedTasks::class.'::staticTask' => true], $scheduler->started);

        $scheduler->started = [];
        $scheduler->launch('2026-08-14 00:00:00');
        $this->assertEquals([InvokableTask::class => false], $scheduler->started);
    }

    public function testLaunchingWithNothingScheduledDoesNothing()
    {
        $scheduler = new StartRecordingScheduler();
        $scheduler->launch('2026-08-14 12:00:00');

        $this->assertEmpty($scheduler->started);
    }

    public function testATaskExitingWithAnErrorIsReported()
    {
        $spy = new SpyLogger();
        $scheduler = new StartRecordingScheduler('false');
        $scheduler->add(CronExpression::everyMinute(), InvokableTask::class);

        Logger::withInstance($spy, fn () => $scheduler->launch('2026-08-14 12:00:00'));

        $this->assertEquals(['error'], array_unique($spy->levels()));
    }

    public function testRunCallsEveryKindOfTask()
    {
        $scheduler = new Scheduler();

        $scheduler->run([RecordedTasks::class, 'staticTask']);
        $scheduler->run([RecordedTasks::class, 'instanceTask']);
        $scheduler->run(InvokableTask::class);

        $this->assertEquals(['static', Logger::getInstance()::class, 'invokable'], RecordedTasks::$calls);
    }

    public function testATaskStillRunningIsSkipped()
    {
        $spy = new SpyLogger();

        $ran = Logger::withInstance($spy, fn () => Scheduler::getInstance()->run([RecordedTasks::class, 'runsItselfAgain']));

        $this->assertTrue($ran);
        $this->assertFalse(RecordedTasks::$nestedRunResult);
        $this->assertEquals(['runsItselfAgain'], RecordedTasks::$calls);
        $this->assertEquals(['warning'], $spy->levels());
    }

    public function testATaskWithoutLockRunsAlongsideItself()
    {
        Scheduler::getInstance()->run([RecordedTasks::class, 'runsItselfAgainWithoutLock'], false);

        $this->assertTrue(RecordedTasks::$nestedRunResult);
        $this->assertEquals(['runsItselfAgainWithoutLock', 'runsItselfAgainWithoutLock'], RecordedTasks::$calls);
    }

    public function testAFailingTaskReleasesItsLock()
    {
        $task = [RecordedTasks::class, 'failingTask'];

        try {
            Scheduler::getInstance()->run($task);
        } catch (\RuntimeException) {
        }

        $this->expectExceptionMessage('boom');
        Scheduler::getInstance()->run($task);
    }
}

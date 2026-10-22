<?php

namespace Cube\Tests\Units\Routine\Classes;

use Cube\Env\Logger\Logger;
use Cube\Routine\Scheduler;

class RecordedTasks
{
    public static array $calls = [];

    public static ?bool $nestedRunResult = null;

    public static function staticTask(): void
    {
        self::$calls[] = 'static';
    }

    public function instanceTask(Logger $logger): void
    {
        self::$calls[] = $logger::class;
    }

    public static function failingTask(): void
    {
        throw new \RuntimeException('boom');
    }

    public static function runsItselfAgain(): void
    {
        self::$calls[] = 'runsItselfAgain';
        if (count(self::$calls) > 1)
            return;

        self::$nestedRunResult = Scheduler::getInstance()->run([self::class, 'runsItselfAgain']);
    }

    public static function runsItselfAgainWithoutLock(): void
    {
        self::$calls[] = 'runsItselfAgainWithoutLock';
        if (count(self::$calls) > 1)
            return;

        self::$nestedRunResult = Scheduler::getInstance()->run([self::class, 'runsItselfAgainWithoutLock'], false);
    }

    private function hiddenTask(): void {}
}

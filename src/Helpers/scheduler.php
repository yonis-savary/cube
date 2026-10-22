<?php

namespace Cube;

use Cube\Routine\CronExpression;
use Cube\Routine\Scheduler;

/**
 * @param array{class-string,string}|class-string $task `[Class::class, 'method']` or an invokable class
 */
function schedule(string $cronExpression, array|string $task, bool $lock = true)
{
    Scheduler::getInstance()->add(new CronExpression($cronExpression), $task, $lock);
}

/**
 * @param array{class-string,string}|class-string $task `[Class::class, 'method']` or an invokable class
 */
function everyMinute(array|string $task, int $step = 1, bool $lock = true)
{
    $step = 1 == $step ? '*' : "*/{$step}";
    Scheduler::getInstance()->add(new CronExpression("{$step} * * * *"), $task, $lock);
}

/**
 * @param array{class-string,string}|class-string $task `[Class::class, 'method']` or an invokable class
 */
function everyHour(array|string $task, int $step = 1, bool $lock = true)
{
    $step = 1 == $step ? '*' : "*/{$step}";
    Scheduler::getInstance()->add(new CronExpression("0 {$step} * * *"), $task, $lock);
}

/**
 * @param array{class-string,string}|class-string $task `[Class::class, 'method']` or an invokable class
 */
function daily(array|string $task, int $step = 1, bool $lock = true)
{
    $step = 1 == $step ? '*' : "*/{$step}";
    Scheduler::getInstance()->add(new CronExpression("0 0 {$step} * *"), $task, $lock);
}

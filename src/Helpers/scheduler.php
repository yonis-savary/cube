<?php

namespace Cube;

use Cube\Routine\CronExpression;
use Cube\Routine\Scheduler;

function schedule(string $cronExpression, callable $callback)
{
    Scheduler::getInstance()->add(new CronExpression($cronExpression), $callback);
}

function everyMinute(callable $callback, int $step = 1)
{
    $step = 1 == $step ? '*' : "*/{$step}";
    Scheduler::getInstance()->add(new CronExpression("{$step} * * * *"), $callback);
}

function everyHour(callable $callback, int $step = 1)
{
    $step = 1 == $step ? '*' : "*/{$step}";
    Scheduler::getInstance()->add(new CronExpression("0 {$step} * * *"), $callback);
}

function daily(callable $callback, int $step = 1)
{
    $step = 1 == $step ? '*' : "*/{$step}";
    Scheduler::getInstance()->add(new CronExpression("0 0 {$step} * *"), $callback);
}

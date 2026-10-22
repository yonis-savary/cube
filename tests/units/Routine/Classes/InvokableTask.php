<?php

namespace Cube\Tests\Units\Routine\Classes;

class InvokableTask
{
    public function __invoke(): void
    {
        RecordedTasks::$calls[] = 'invokable';
    }
}

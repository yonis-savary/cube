<?php

namespace Cube\Tests\Units\Routine\Classes;

use Cube\Routine\Scheduler;
use Symfony\Component\Process\Process;

class StartRecordingScheduler extends Scheduler
{
    /** @var array<string,bool> Identifiers of the started tasks, with their lock */
    public array $started = [];

    public function __construct(
        private string $childCommand = 'true'
    ) {}

    protected function start(array|string $task, bool $lock): Process
    {
        $this->started[$this->getTaskName($task)] = $lock;

        $process = new Process([$this->childCommand]);
        $process->start();

        return $process;
    }
}

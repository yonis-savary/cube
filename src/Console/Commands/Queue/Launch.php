<?php

namespace Cube\Console\Commands\Queue;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Core\Autoloader;
use Cube\Queue\Queue;
use Exception;

class Launch extends Command
{
    public function getScope(): string
    {
        return "queue";
    }

    public function execute(Args $args): int
    {
        /** @var class-string<Queue> $queueClass */
        $queueClass = $args->getValue('q', 'queue');
        if (!class_exists($queueClass))
            throw new Exception("$queueClass is not a class");

        if (!Autoloader::extends($queueClass, Queue::class))
            throw new Exception("$queueClass is not a queue class");

        $instance = new $queueClass();
        $instance->loop();
        return 0;
    }
}
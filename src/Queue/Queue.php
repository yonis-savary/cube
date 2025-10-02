<?php

namespace Cube\Queue;

use Cube\Core\Component;
use Cube\Env\Logger\HasLogger;
use Cube\Env\Logger\NullLogger;

abstract class Queue
{
    use Component;
    use HasLogger;

    protected QueueDriver $driver;

    public function getDriver(): ?QueueDriver
    {
        return null;
    }

    public function __construct()
    {
        $this->driver = $this->getDriver() ?? new LocalDiskQueue(md5(static::class));
        $this->logger = $this->getLogger() ?? new NullLogger();
    }

    public function flush() {
        return $this->driver->flush();
    }

    public function push(callable $function, mixed $args) {
        return $this->driver->push($function, $args);
    }

    public function process(): void
    {
        $this->driver->next(function(QueueCallback $callback){
            try {
                ($callback)();
                return true;
            } catch (\Throwable $thrown) {
                $this->warning("Caught an exception while processing an item");
                $this->error($thrown->getMessage() . " " . $thrown->getFile() . "@". $thrown->getLine());

                return false;
            }
        });
    }

    public function loop() {
        while (true) {
            if (!$this->process())
                sleep(5);
        }
    }
}

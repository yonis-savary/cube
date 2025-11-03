<?php

namespace Cube\Queue;

use Cube\Core\Component;
use Cube\Env\Logger\HasLogger;
use Cube\Env\Logger\Logger;
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
        $this->driver = $this->getDriver() ?? new RedisQueue(static::class);
        $this->logger = $this->getLogger() ?? new NullLogger();
    }

    public function flush() {
        return $this->driver->flush();
    }

    public function push(callable|string $function, mixed $args) {
        if (is_string($function))
            $function = [static::class, $function];

        return $this->driver->push(new QueueCallback($function, $args));
    }

    public function processNext(): void
    {
        $callback = $this->driver->next();
        try {
            ($callback)() ?? true;
        } catch (\Throwable $thrown) {
            $this->warning("Caught an exception while processing an item");
            $this->error($thrown->getMessage() . " " . $thrown->getFile() . "@". $thrown->getLine());

            $this->driver->push($callback);
        }
    }

    public function loop(?Logger $attachedLogger=null) {
        if ($attachedLogger)
            $this->logger->attach($attachedLogger);

        $this->logger->info('Starting queue ' . static::class . ' ('.date('Y-m-d h:i:s').')');

        $this->logger->asGlobalInstance(function(){
            while (true) {
                if (!$this->processNext())
                    usleep(1000 * 50);
            }
        });
    }
}

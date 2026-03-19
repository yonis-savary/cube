<?php

namespace Cube\Queue;

use Cube\Core\Component;
use Cube\Core\Injector;
use Cube\Env\Logger\HasLogger;
use Cube\Env\Logger\Logger;
use Cube\Env\Logger\NullLogger;
use Cube\Queue\Drivers\LocalDiskQueueDriver;
use Cube\Queue\Drivers\QueueDriver;
use RuntimeException;
use Throwable;

/**
 * To implement your queue, you should implement
 *
 * `public function __invoke($customArgs, $customArgs...)` to process singular items
 *
 * `protected function getDriver(): QueueDriver` to specify how the queue store items (localdisk driver by default)
 *
 * `protected function onError(Throwable $thrown, array $args): bool` to specify how the queue handle errors
 *
 * To interact with your queue, you can call
 *
 * Launch `php do cube:queue --queue=App\Queues\YourQueueClass` to launch it
 * Launch `php do cube:queue --queue=App\Queues\YourQueueClass --flush` to flush it
 *
 * `YourQueueClass::queue($customArgs, $customArgs)` to push items
 * `$yourQueue->push($customArgs, $customArgs)` to push items (alternative to static call)
 * `$yourQueue->flush()` to clear the queue
 * `$yourQueue->processNext()` if you need to process exactly one element
 * `loop(?Logger $attachedLogger=null)` if you wish to manually launch the queue
 */
abstract class Queue
{
    use HasLogger;

    protected QueueDriver $driver;
    protected bool $initialized = false;

    final public static function getIdentifier() {
        return md5(static::class);
    }

    public static function queue(mixed ...$args) {
        $instance = Injector::instanciate(static::class);
        $instance->push($args);
    }

    protected function initialize() {
        if ($this->initialized)
            return;

        $this->initialized = true;
        $this->driver = $this->getDriver();
        $this->driver->setIdentifier(static::getIdentifier());
        $this->logger = $this->getLogger() ?? new NullLogger();
    }

    protected function assertIsCallable() {
        if (!method_exists($this, '__invoke'))
            throw new RuntimeException("__invoke method must be instanciated on class");
    }

    protected function getDriver(): QueueDriver
    {
        return new LocalDiskQueueDriver(static::class);
    }

    /**
     * This method shall be called when a exception is raised
     * when processing a queue item
     *
     * @return bool On `true`, the system will repush the failed job on queue, otherwise, the job is cancelled
     */
    protected function onError(Throwable $thrown, array $args): bool {
        return false;
    }

    public function flush() {
        $this->initialize();
        return $this->driver->flush();
    }

    /**
     * @return mixed $args Arguments that shall be passed to `__invoke` when processing the item
     */
    public function push(mixed ...$args) {
        $this->initialize();
        return $this->driver->push($args);
    }

    public function processNext(): void
    {
        $this->initialize();
        $args = $this->driver->next();
        try {
            ($this)(...$args) ?? true;
        } catch (\Throwable $thrown) {
            $this->warning("Caught an exception while processing an item");
            $this->error($thrown->getMessage() . " " . $thrown->getFile() . "@". $thrown->getLine());

            if ($this->onError($thrown, $args))
                $this->driver->push($args);
        }
    }

    public function loop(?Logger $attachedLogger=null) {
        $this->initialize();
        $this->assertIsCallable();

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

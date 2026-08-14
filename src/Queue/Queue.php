<?php

namespace Cube\Queue;

use Cube\Core\Component;
use Cube\Core\Injector;
use Cube\Env\Logger\HasLogger;
use Cube\Env\Logger\Logger;
use Cube\Queue\Drivers\LocalDiskQueueDriver;
use Cube\Queue\Drivers\QueueDriver;
use RuntimeException;
use Throwable;

/**
 * Abstract Class to implement Queueing system
 *
 * How to implements:
 * - Item processor : `public function __invoke($customArgs, $customArgs...)`
 * - Set storage type : `protected function getDriver(): QueueDriver` (local disk by default)
 *
 * Basic interactions
 * - Push items (static) : `YourQueueClass::queue($customArgs, $customArgs)`
 * - Push items : `$yourQueue->push($customArgs, $customArgs)`
 * - Launch `php do cube:queue --queue=App\Queues\YourQueueClass`
 *
 * Advanced interactions
 * - Define error behavior: `protected function onError(Throwable $thrown, array $args): bool`
 * - Flush `php do cube:queue --queue=App\Queues\YourQueueClass --flush`
 * - Clear the queue : `$yourQueue->flush()`
 * - Process one element : `$yourQueue->processNext()` (`false` when there was nothing to do)
 * - Manually launch the queue : `loop(?Logger $attachedLogger=null)`
 * - Ask a running loop to leave : `$yourQueue->stop()`, or send it SIGTERM/SIGINT
 */
abstract class Queue
{
    use HasLogger;

    protected QueueDriver $driver;
    protected bool $initialized = false;
    protected bool $shouldStop = false;

    final public static function getIdentifier(): string {
        return md5(static::class);
    }

    public static function queue(mixed ...$args): void {
        $instance = Injector::instanciate(static::class);
        $instance->push(...$args);
    }

    protected function initialize(): void {
        if ($this->initialized)
            return;

        $this->initialized = true;
        $this->driver = $this->getDriver();
        $this->driver->setIdentifier(static::getIdentifier());
        $this->logger = $this->getLogger();
    }

    protected function assertIsCallable(): void
    {
        if (!method_exists($this, '__invoke'))
            throw new RuntimeException("__invoke method must be instanciated on class");
    }

    protected function getDriver(): QueueDriver
    {
        return new LocalDiskQueueDriver();
    }

    /**
     * This method shall be called when a exception is raised
     * when processing a queue item
     *
     * @return bool On `true`, the system will repush the failed job on queue, otherwise, the job is cancelled
     */
    protected function onError(Throwable $thrown, array $args): bool
    {
        return false;
    }

    public function flush(): void
    {
        $this->initialize();
        $this->driver->flush();
    }

    /**
     * @param mixed ...$args Arguments that shall be passed to `__invoke` when processing the item
     */
    public function push(mixed ...$args): void
    {
        $this->initialize();
        $this->driver->push($args);
    }

    /**
     * @return bool `true` when an item was processed, `false` otherwise
     */
    public function processNext(): bool
    {
        $this->initialize();

        if (null === $args = $this->driver->next()) {
            return false;
        }

        try {
            return ($this)(...$args) ?? true;
        } catch (Throwable $thrown) {
            $this->warning("Caught an exception while processing an item");
            $this->error($thrown->getMessage() . " " . $thrown->getFile() . "@". $thrown->getLine());

            if ($this->onError($thrown, $args))
                $this->driver->push($args);

            return false;
        }
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }

    public function loop(?Logger $attachedLogger=null): void
    {
        $this->initialize();
        $this->assertIsCallable();

        if ($attachedLogger)
            $this->logger->attach($attachedLogger);

        $this->shouldStop = false;
        $this->listenToStopSignals();

        $this->logger->info('Starting queue ' . static::class . ' ('.date('Y-m-d H:i:s').')');

        try {
            $this->logger->asGlobalInstance(function () {
                while (!$this->shouldStop) {
                    if (!$this->processNext())
                        usleep(1000 * 50);
                }
            });
        } finally {
            $this->releaseStopSignals();
        }

        $this->logger->info('Stopped queue ' . static::class . ' ('.date('Y-m-d H:i:s').')');
    }

    /**
     * @return int[] Signals to handle
     */
    protected function stopSignals(): array
    {
        return [SIGTERM, SIGINT];
    }

    protected function listenToStopSignals(): void
    {
        if (!function_exists('pcntl_async_signals'))
            return;

        pcntl_async_signals(true);

        foreach ($this->stopSignals() as $signal)
            pcntl_signal($signal, fn () => $this->stop());
    }

    protected function releaseStopSignals(): void
    {
        if (!function_exists('pcntl_signal'))
            return;

        foreach ($this->stopSignals() as $signal)
            pcntl_signal($signal, SIG_DFL);
    }
}

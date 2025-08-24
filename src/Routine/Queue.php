<?php

namespace Cube\Routine;

use Cube\Data\Bunch;
use Cube\Env\Storage;
use Cube\Env\Logger\HasLogger;
use Cube\Env\Logger\Logger;
use Cube\Utils\Console;

abstract class Queue extends Routine
{
    use HasLogger;

    abstract public static function batchSize(): int;

    public static function when(): CronExpression
    {
        return CronExpression::everyMinute();
    }

    public static function countToProcess(): int
    {
        $storage = static::getStorage();

        return Bunch::of($storage->files())
            ->filter(fn ($x) => !str_starts_with(basename($x), '#'))
            ->count()
        ;
    }

    public static function flush(bool $deleteLockedToo = false): void
    {
        $storage = static::getStorage();

        $files = Bunch::of($storage->files());

        if (!$deleteLockedToo) {
            $files->filter(fn ($x) => !str_starts_with(basename($x), '#'));
        }

        $files->forEach(fn ($x) => unlink($x));
    }

    public static function processNext(): void
    {
        $storage = static::getStorage();
        $files = $storage->files();

        if (!count($files)) {
            Console::log('No item to process');

            return;
        }

        $toProcess = Bunch::of($files)->first(fn ($x) => !str_starts_with(basename($x), '#'));

        if (!$toProcess) {
            return;
        }

        $logger = Logger::getInstance();
        if (!$locked = static::lockFile($toProcess)) {
            $logger->warning(static::class.": could not lock file {$toProcess}");

            return;
        }

        $object = unserialize(file_get_contents($locked));

        try {
            $res = static::process($object);
        } catch (\Throwable $thrown) {
            $logger->warning("Caught an exception while processing {$locked}");
            $logger->logThrowable($thrown);

            throw $thrown;
        }

        unlink($locked);

        if (!$res) {
            static::processNext();
        }
    }

    protected static function getIdentifier(): string
    {
        return preg_replace('/[^a-z]/', '.', strtolower(static::class));
    }

    protected static function getStorage(): Storage
    {
        $identifier = static::getIdentifier();

        return Storage::getInstance()->child('Queues')->child($identifier);
    }

    protected static function lockFile(string $file): ?string
    {
        $dir = dirname($file);
        $basename = basename($file);
        $newPath = "{$dir}/#{$basename}";

        if (!rename($file, $newPath)) {
            return null;
        }

        return $newPath;
    }

    /**
     * Push an object to the queue.
     *
     * @param mixed $object
     */
    protected static function pushToQueue($object): void
    {
        $storage = static::getStorage();
        $identifier = uniqid(time().'-');

        $storage->write($identifier, serialize($object));
    }

    /**
     * Process one item from the queue.
     *
     * @param mixed $object
     *
     * @return bool `true` if the item was proceed, `false` otherwise
     */
    abstract protected static function process($object): bool;
}

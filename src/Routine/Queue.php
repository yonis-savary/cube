<?php

namespace Cube\Routine;

use Cube\Data\Bunch;
use Cube\Env\Storage;
use Cube\Logger\HasLogger;
use Cube\Logger\Logger;

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
        /** @var self $self */
        $self = get_called_class();

        $storage = $self::getStorage();

        return Bunch::of($storage->files())
            ->filter(fn ($x) => !str_starts_with(basename($x), '#'))
            ->count()
        ;
    }

    public static function flush(bool $deleteLockedToo = false): void
    {
        /** @var self $self */
        $self = get_called_class();

        $storage = $self::getStorage();

        $files = Bunch::of($storage->files());

        if (!$deleteLockedToo) {
            $files->filter(fn ($x) => !str_starts_with(basename($x), '#'));
        }

        $files->forEach(fn ($x) => unlink($x));
    }

    public static function processNext(): void
    {
        /** @var self $self */
        $self = get_called_class();

        $storage = $self::getStorage();
        $files = $storage->files();

        if (!count($files)) {
            return;
        }

        $toProcess = Bunch::of($files)->first(fn ($x) => !str_starts_with(basename($x), '#'));

        if (!$toProcess) {
            return;
        }

        $logger = Logger::getInstance();
        if (!$locked = $self::lockFile($toProcess)) {
            $logger->warning($self.": could not lock file {$toProcess}");

            return;
        }

        $object = unserialize(file_get_contents($locked));

        try {
            $res = $self::process($object);
        } catch (\Throwable $thrown) {
            $logger->warning("Caught an exception while processing {$locked}");
            $logger->logThrowable($thrown);

            throw $thrown;
        }

        unlink($locked);

        if (!$res) {
            $self::processNext();
        }
    }

    protected static function getIdentifier(): string
    {
        return preg_replace('/[^a-z]/', '.', strtolower(get_called_class()));
    }

    protected static function getStorage(): Storage
    {
        /** @var self $self */
        $self = get_called_class();

        $identifier = $self::getIdentifier();

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
        /** @var self $self */
        $self = get_called_class();

        $storage = $self::getStorage();
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

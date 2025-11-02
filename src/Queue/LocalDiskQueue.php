<?php 

namespace Cube\Queue;

use Cube\Data\Bunch;
use Cube\Env\Logger\Logger;
use Cube\Env\Storage;
use Exception;

class LocalDiskQueue implements QueueDriver
{
    protected string $identifier;

    public function __construct(string $identifier)
    {
        $this->identifier = preg_replace('/[^a-z0-9]/i', '.', strtolower($identifier));
    }

    protected function getStorage(): Storage
    {
        return Storage::getInstance()->child('Queues')->child($this->identifier);
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


    protected static function unlockFile(string $file): ?string
    {
        $dir = dirname($file);
        $basename = basename($file);
        $newBasename = ltrim($basename, "#");
        $newPath = "{$dir}/{$newBasename}";

        if (!rename($file, $newPath)) {
            return null;
        }

        return $newPath;
    }

    public function next(): QueueCallback
    {
        $storage = $this->getStorage();
        $files = $storage->files();

        $storage = $this->getStorage();
        do {
            $files = $storage->files();
            $toProcess = Bunch::of($files)->first(fn ($x) => !str_starts_with(basename($x), '#'));
            if (!$toProcess)
                sleep(1);
        } while (!$toProcess);

        if (!$locked = $this->lockFile($toProcess)) {
            throw new Exception(static::class.": could not lock file {$toProcess}");
        }

        $element = unserialize(file_get_contents($locked));
        unlink($locked);

        return $element;
    }

    public function flush()
    {
        $storage = $this->getStorage();

        Bunch::of($storage->files())->forEach(fn ($x) => unlink($x));
    }

    public function push(QueueCallback $callback)
    {
        $storage = $this->getStorage();
        $identifier = uniqid(time().'-');
        $storage->write($identifier, serialize($callback));
    }
}
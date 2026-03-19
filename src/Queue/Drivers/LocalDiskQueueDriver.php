<?php 

namespace Cube\Queue\Drivers;

use Cube\Data\Bunch;
use Cube\Env\Storage;
use Exception;

class LocalDiskQueueDriver extends BasicQueueDriver
{
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

    public function next(): array
    {
        $storage = $this->getStorage($this->identifier);
        $files = $storage->files();

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
        $storage = $this->getStorage($this->identifier);

        Bunch::of($storage->files())->forEach(fn ($x) => unlink($x));
    }

    public function push(array $args)
    {
        $storage = $this->getStorage();
        $uniqueName = uniqid(time().'-');
        $storage->write($uniqueName, serialize($args));
    }
}
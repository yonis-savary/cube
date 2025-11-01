<?php 

namespace Cube\Queue;

use Cube\Data\Bunch;
use Cube\Env\Logger\Logger;
use Cube\Env\Storage;

class LocalDiskQueue implements QueueDriver
{
    protected string $identifier;

    public function __construct(string $identifier)
    {
        $this->identifier = $identifier;
    }

    protected function getIdentifier(): string
    {
        return preg_replace('/[^a-z0-9]/i', '.', strtolower($this->identifier));
    }

    protected function getStorage(): Storage
    {
        $identifier = $this->getIdentifier();

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

    public function next(callable $function)
    {
        $storage = $this->getStorage();
        $files = $storage->files();

        if (!count($files))
            return false;

        $toProcess = Bunch::of($files)->first(fn ($x) => !str_starts_with(basename($x), '#'));

        if (!$toProcess)
            return false;

        $logger = Logger::getInstance();
        if (!$locked = $this->lockFile($toProcess)) {
            $logger->warning(static::class.": could not lock file {$toProcess}");
            return false;
        }

        $element = unserialize(file_get_contents($locked));

        if ($callbackReturn = (($function)($element) ?? true)) {
            unlink($locked);
        } else {
            $this->unlockFile($toProcess);
        }
        return $callbackReturn;
    }

    public function flush()
    {
        $storage = $this->getStorage();

        Bunch::of($storage->files())->forEach(fn ($x) => unlink($x));
    }

    public function push(callable $function, mixed $args)
    {
        $storage = $this->getStorage();
        $identifier = uniqid(time().'-');
        $storage->write($identifier, serialize(new QueueCallback($function, $args)));
    }
}
<?php

namespace Cube\Queue\Drivers;

use Cube\Data\Bunch;
use Cube\Env\Storage;

class LocalDiskQueueDriver extends BasicQueueDriver
{
    protected function getStorage(): Storage
    {
        return Storage::getInstance()->child('Queues')->child($this->identifier);
    }

    /**
     * @return ?string Path of the locked file, `null` when another worker won the race
     */
    protected static function lockFile(string $file): ?string
    {
        $dir = dirname($file);
        $basename = basename($file);
        $newPath = "{$dir}/#{$basename}";

        return @rename($file, $newPath)
            ? $newPath
            : null;
    }

    public function next(): ?array
    {
        $storage = $this->getStorage();

        $toProcess = Bunch::wrap($storage->files())
            ->first(fn ($file) => !str_starts_with(basename($file), '#'))
        ;

        if (!$toProcess) {
            return null;
        }

        if (!$locked = self::lockFile($toProcess)) {
            return null;
        }

        $element = unserialize(file_get_contents($locked));
        unlink($locked);

        return is_array($element) ? $element : null;
    }

    public function flush(): void
    {
        Bunch::wrap($this->getStorage()->files())
        ->forEach(fn ($file) => unlink($file));
    }

    public function push(array $args): void
    {
        $storage = $this->getStorage();
        $name = sprintf(
            '%020d-%s',
            (int) (microtime(true) * 1_000_000),
            bin2hex(random_bytes(8))
        );

        // Written under a locked name first : a worker must never read a file still being written
        $storage->write("#{$name}", serialize($args));
        rename($storage->path("#{$name}"), $storage->path($name));
    }
}

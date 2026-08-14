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

        $toProcess = Bunch::of($storage->files())
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

        return $element;
    }

    public function flush(): void
    {
        Bunch::of($this->getStorage()->files())
        ->forEach(fn ($file) => unlink($file));
    }

    public function push(array $args): void
    {
        $this->getStorage()
        ->write(uniqid(time().'-'), serialize($args));
    }
}

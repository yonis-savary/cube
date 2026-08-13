<?php

namespace Cube\Tests\Units\Env\Classes;

use Cube\Env\Storage;
use Cube\Utils\Path;

/**
 * Give a test its own directory under the project storage, removed once it is done.
 */
trait HasTemporaryStorage
{
    protected Storage $storage;

    protected function setUpTemporaryStorage(string $prefix): void
    {
        $this->storage = Storage::getInstance()->child(uniqid($prefix));
    }

    protected function tearDownTemporaryStorage(): void
    {
        $this->removeDirectory($this->storage->getRoot());
    }

    protected function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) as $entry) {
            if (in_array($entry, ['.', '..'])) {
                continue;
            }

            $child = Path::join($path, $entry);

            if (is_dir($child)) {
                $this->removeDirectory($child);

                continue;
            }

            unlink($child);
        }

        rmdir($path);
    }
}

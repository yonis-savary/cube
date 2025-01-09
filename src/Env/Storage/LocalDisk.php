<?php

namespace YonisSavary\Cube\Env\Storage;

use Throwable;
use YonisSavary\Cube\Data\Bunch;
use YonisSavary\Cube\Logger\Logger;
use YonisSavary\Cube\Utils\Path;

class LocalDisk extends DiskDriver
{
    public function write(string $path, string $content, int $flags=0): bool
    {
        $initialSize = $this->isFile($path) ? filesize($path) : 0;

        try
        {
            file_put_contents($path, $content, $flags);
            return (filesize($path)-$initialSize) == strlen($content);
        }
        catch (Throwable $err)
        {
            Logger::getInstance()->warning("Could not write file [$path]");
            Logger::getInstance()->warning($err);
            return false;
        }
    }

    public function read(string $path): string
    {
        return file_get_contents($path);
    }

    public function makeDirectory(string $path): bool
    {
        if (!is_dir($path))
            mkdir($path);
        return $this->isDirectory($path);
    }

    /**
     * @return bool `true` weither `$path` is a directory or file, `false` otherwise
     */
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * @return bool `true` if the target does not exists anymore, `false` on failure
     */
    public function unlink(string $path): bool
    {
        unlink($path);

        return !$this->isFile($path);
    }

    public function isWritable(string $path): bool
    {
        return is_writable($path);
    }

    public function isReadable(string $path): bool
    {
        return is_readable($path);
    }

    public function scanDirectory(string $path): array
    {
        return Bunch::of(scandir($path))
            ->filter(fn($e) => !in_array($e, [".", ".."]))
            ->get();
    }

    public function getParentPath(string $path): string
    {
        return realpath(Path::join($path, ".."));
    }
}
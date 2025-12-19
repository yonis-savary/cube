<?php

namespace Cube\Env\Storage;

use Cube\Data\Bunch;
use Cube\Utils\Path;

abstract class StorageDriver
{
    protected const EXPLORE_ALL = 0;
    protected const EXPLORE_FILES = 1;
    protected const EXPLORE_DIRECTORIES = 2;

    /**
     * Write a file.
     *
     * @return bool `true` if the write was successful, `false` otherwise
     */
    abstract public function write(string $path, string $content, int $flags = 0): bool;

    abstract public function read(string $path): string;

    /**
     * Make a new directory.
     *
     * @return bool `true` if the directory now exists, `false` otherwise
     */
    abstract public function makeDirectory(string $path, bool $recursive = true): bool;

    /**
     * @return bool `true` weither `$path` is a directory or file, `false` otherwise
     */
    abstract public function exists(string $path): bool;

    abstract public function isFile(string $path): bool;

    abstract public function isDirectory(string $path): bool;

    /**
     * @return bool `true` if the target does not exists anymore, `false` on failure
     */
    abstract public function unlink(string $path): bool;

    abstract public function isWritable(string $path): bool;

    abstract public function isReadable(string $path): bool;

    /**
     * @return string[] Array of absolute directories paths inside `$path`
     */
    abstract public function scanDirectory(string $path): array;

    /**
     * @return string[] Array of absolute file paths inside `$path`
     */
    public function files(string $path): array
    {
        return Bunch::of($this->scanDirectory($path))
            ->map(fn ($el) => Path::join($path, $el))
            ->filter(fn ($el) => $this->isFile($el))
            ->get()
        ;
    }

    /**
     * @return string[] Array of absolute directories paths inside `$path`
     */
    public function directories(string $path): array
    {
        return Bunch::of($this->scanDirectory($path))
            ->map(fn ($el) => Path::join($path, $el))
            ->filter(fn ($el) => $this->isDirectory($el))
            ->get()
        ;
    }

    /**
     * Recursively explore a part of the disk.
     *
     * @return string[] List of files/directories
     */
    public function explore(string $path): array
    {
        return $this->exploreDirectory($path, self::EXPLORE_ALL);
    }

    /**
     * Recursively explore a part of the disk.
     *
     * @return string[] List of sub-files
     */
    public function exploreFiles(string $path): array
    {
        return $this->exploreDirectory($path, self::EXPLORE_FILES);
    }

    /**
     * Recursively explore a part of the disk.
     *
     * @return string[] List of sub-directories
     */
    public function exploreDirectories(string $path): array
    {
        return $this->exploreDirectory($path, self::EXPLORE_DIRECTORIES);
    }

    public function getParentPath(string $path): string
    {
        return Path::join($path, '..');
    }

    protected function exploreDirectory(string $path, int $mode = self::EXPLORE_ALL): array
    {
        $results = [];

        $partitions = Bunch::of($this->scanDirectory($path))
            ->map(fn ($e) => Path::relative($e, $path))
            ->partitionFilter(fn ($e) => $this->isDirectory($e))
        ;

        $files = $partitions[0] ?? [];
        $directories = $partitions[1] ?? [];

        if ($directories && (self::EXPLORE_FILES !== $mode)) {
            array_push($results, ...$directories);
        }

        if ($files && (self::EXPLORE_DIRECTORIES !== $mode)) {
            array_push($results, ...$files);
        }

        if ($directories) {
            foreach ($directories as $directory) {
                array_push($results, ...$this->exploreDirectory($directory, $mode));
            }
        }

        return $results;
    }
}

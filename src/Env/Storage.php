<?php

namespace YonisSavary\Cube\Env;

use YonisSavary\Cube\Core\Component;
use YonisSavary\Cube\Env\Storage\DiskDriver;
use YonisSavary\Cube\Env\Storage\LocalDisk;
use YonisSavary\Cube\Env\Storage\StorageConfiguration;
use YonisSavary\Cube\Utils\Path;

/**
 * Extending DiskDriver ensure we implement every method
 */
class Storage extends DiskDriver
{
    use Component;

    protected DiskDriver $driver;
    protected string $rootPath;

    public static function getDefaultInstance(): static
    {
        return new self(Path::relative("Storage"), new LocalDisk);
    }


    public function __construct(string $rootPath='/', DiskDriver $driver=new LocalDisk)
    {
        $this->driver = $driver;
        $this->rootPath = $rootPath;

        $this->makeDirectory('/');
    }

    public function getRoot(): string
    {
        return $this->path("/");
    }

    public function path(string $path): string
    {
        return Path::relative($path, $this->rootPath);
    }

    public function write(string $path, string $content, int $flags=0): bool
    {
        return $this->driver->write($this->path($path), $content, $flags);
    }

    public function makeDirectory(string $path): bool
    {
        return $this->driver->makeDirectory($this->path($path));
    }

    public function read(string $path): string
    {
        return $this->driver->read($this->path($path));
    }

    public function exists(string $path): bool
    {
        return $this->driver->exists($this->path($path));
    }

    public function isFile(string $path): bool
    {
        return $this->driver->isFile($this->path($path));
    }

    public function isDirectory(string $path): bool
    {
        return $this->driver->isDirectory($this->path($path));
    }

    public function unlink(string $path): bool
    {
        return $this->driver->unlink($this->path($path));
    }

    public function isWritable(string $path="/"): bool
    {
        return $this->driver->isWritable($this->path($path));
    }

    public function isReadable(string $path="/"): bool
    {
        return $this->driver->isReadable($this->path($path));
    }

    public function scanDirectory(string $path="/"): array
    {
        return $this->driver->scanDirectory($this->path($path));
    }

    public function listFiles(string $path="/"): array
    {
        return $this->driver->listFiles($this->path($path));
    }

    public function listDirectory(string $path="/"): array
    {
        return $this->driver->listDirectory($this->path($path));
    }

    public function explore(string $path="/"): array
    {
        return $this->driver->explore($this->path($path));
    }

    public function exploreFiles(string $path="/"): array
    {
        return $this->driver->exploreFiles($this->path($path));
    }

    public function exploreDirectories(string $path="/"): array
    {
        return $this->driver->exploreDirectories($this->path($path));
    }

    public function child(string $path): self
    {
        return new self($this->path($path), $this->driver);
    }

    public function parent(): self
    {
        $parentPath = $this->driver->getParentPath($this->getRoot());
        return new self($parentPath, $this->driver);
    }

}
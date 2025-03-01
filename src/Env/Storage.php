<?php

namespace Cube\Env;

use Cube\Core\Component;
use Cube\Env\Storage\DiskDriver;
use Cube\Env\Storage\LocalDisk;
use Cube\Utils\Path;
use Stringable;

/**
 * Extending DiskDriver ensure we implement every method.
 */
class Storage extends DiskDriver implements Stringable
{
    use Component;

    protected DiskDriver $driver;
    protected string $rootPath;

    public function __construct(string $rootPath = '/', DiskDriver $driver = new LocalDisk())
    {
        $this->driver = $driver;
        $this->rootPath = $rootPath;

        $this->makeDirectory('/', true);
    }

    public static function getDefaultInstance(): static
    {
        return new self(Path::relative('Storage'), new LocalDisk());
    }

    public function getRoot(): string
    {
        return $this->path('/');
    }

    public function path(string $path): string
    {
        return Path::relative($path, $this->rootPath);
    }

    public function write(string $path, string $content, int $flags = 0): bool
    {
        return $this->driver->write($this->path($path), $content, $flags);
    }

    public function makeDirectory(string $path, bool $recursive = true): bool
    {
        return $this->driver->makeDirectory($this->path($path), $recursive);
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

    public function isWritable(string $path = '/'): bool
    {
        return $this->driver->isWritable($this->path($path));
    }

    public function isReadable(string $path = '/'): bool
    {
        return $this->driver->isReadable($this->path($path));
    }

    public function scanDirectory(string $path = '/'): array
    {
        return $this->driver->scanDirectory($this->path($path));
    }

    /** @return array<int,string> */
    public function files(string $path = '/'): array
    {
        return $this->driver->files($this->path($path));
    }

    /** @return array<int,string> */
    public function directories(string $path = '/'): array
    {
        return $this->driver->directories($this->path($path));
    }

    /** @return array<int,string> */
    public function explore(string $path = '/'): array
    {
        return $this->driver->explore($this->path($path));
    }

    /** @return array<int,string> */
    public function exploreFiles(string $path = '/'): array
    {
        return $this->driver->exploreFiles($this->path($path));
    }

    public function exploreDirectories(string $path = '/'): array
    {
        return $this->driver->exploreDirectories($this->path($path));
    }

    public function child(string $path): self
    {
        return new self($this->path($path), $this->driver);
    }

    public function toCache(): Cache
    {
        return new Cache($this);
    }

    public function parent(): self
    {
        $parentPath = $this->driver->getParentPath($this->getRoot());

        return new self($parentPath, $this->driver);
    }


    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getRoot();
    }
}

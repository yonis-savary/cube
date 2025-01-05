<?php

namespace YonisSavary\Cube\Env\Storage;

use YonisSavary\Cube\Configuration\ConfigurationElement;
use YonisSavary\Cube\Utils\Path;

class StorageConfiguration extends ConfigurationElement
{
    public readonly DiskDriver $driver;
    public readonly string $rootPath;

    public function __construct(
        DiskDriver $driver=new LocalDisk,
        string $rootPath="Storage"
    )
    {
        $this->driver = $driver;
        $this->rootPath = Path::relative($rootPath);
    }
}
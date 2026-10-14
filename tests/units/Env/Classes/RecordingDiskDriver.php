<?php

namespace Cube\Tests\Units\Env\Classes;

use Cube\Env\Storage\LocalDiskDriver;

class RecordingDiskDriver extends LocalDiskDriver
{
    /** @var string[] */
    public array $madeDirectories = [];

    public function makeDirectory(string $path, bool $recursive = true): bool
    {
        $this->madeDirectories[] = $path;

        return parent::makeDirectory($path, $recursive);
    }
}

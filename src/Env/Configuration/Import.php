<?php

namespace Cube\Env\Configuration;

use Cube\Env\Logger\Logger;
use Cube\Utils\Path;

class Import
{
    public function __construct(
        public readonly string $fileToImport
    ) {}

    public function getElements(): array
    {
        $fileToImport = $this->fileToImport;
        $path = is_file($fileToImport)
            ? $fileToImport
            : Path::relative($fileToImport);

        if (!is_file($path)) {
            Logger::getInstance()->warning('Cannot import configuration, {path} is not a file', ['path' => Path::toRelative($path)]);

            return [];
        }

        return include $path;
    }
}

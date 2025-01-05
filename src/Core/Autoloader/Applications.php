<?php

namespace YonisSavary\Cube\Core\Autoloader;

use YonisSavary\Cube\Configuration\ConfigurationElement;
use YonisSavary\Cube\Utils\Path;

class Applications extends ConfigurationElement
{
    public readonly array $paths;

    public function __construct(
        string ...$paths
    ){
        foreach ($paths as $path)
        {
            if (!is_dir($path))
                $path = Path::relative($path);
        }
    }
}
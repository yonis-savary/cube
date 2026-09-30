<?php

namespace Cube\Core\Autoloader;

use Cube\Data\Bunch;
use Cube\Env\Configuration\ConfigurationElement;
use Cube\Utils\Path;

class Applications extends ConfigurationElement
{
    /**
     * @var array<int,string>
     */
    public readonly array $paths;

    public function __construct(
        string ...$paths
    ) {
        if (!count($paths)) {
            $paths = ['App'];
        }

        $this->paths = Bunch::of($paths)
            ->map(fn($path) => is_dir($path) ? $path: Path::relative($path))
            ->get();
    }
}

<?php

namespace Cube\Data\DataToObject\Generator;

use Cube\Data\DataToObject\Generator\Descriptors\Types\ClassType;
use Cube\Env\Storage;
use Cube\Utils\Path;

readonly class GenerationTarget
{
    public function __construct(
        public string $namespace,
        public Storage $directory,
    ) {}

    /**
     * `null` when `$namespace` is outside every PSR-4 prefix, as its classes could not be autoloaded
     */
    public static function fromNamespace(string $namespace): ?self
    {
        if (!$directory = Path::namespaceToPath($namespace))
            return null;

        return new self(trim($namespace, '\\'), new Storage($directory));
    }

    public static function fromDirectory(string $directory): self
    {
        return new self(Path::pathToNamespace($directory), new Storage($directory));
    }

    public function fullNamespaceOf(string $relativeNamespace): string
    {
        return trim("{$this->namespace}\\{$relativeNamespace}", '\\');
    }

    public function fullNameOf(ClassType $type): string
    {
        return $this->fullNamespaceOf($type->namespace).'\\'.$type->name;
    }

    public function write(string $relativeNamespace, string $name, string $content): string
    {
        $directory = $this->directory->child(str_replace('\\', '/', $relativeNamespace));
        $directory->write("{$name}.php", $content);

        return $directory->path("{$name}.php");
    }
}

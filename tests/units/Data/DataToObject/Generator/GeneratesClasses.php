<?php

namespace Cube\Tests\Units\Data\DataToObject\Generator;

use Cube\Data\DataToObject\Generator\ClientWriter;
use Cube\Data\DataToObject\Generator\Descriptors\DtoClass;
use Cube\Data\DataToObject\Generator\DtoWriter;
use Cube\Data\DataToObject\Generator\GenerationTarget;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;

/**
 * Writes generated classes into a temporary directory, under a namespace of their own so that two tests
 * of the same process never declare the same class.
 */
trait GeneratesClasses
{
    use HasTemporaryStorage;
    use LintsGeneratedFiles;

    protected string $generatedNamespace;
    protected ?\Closure $generatedClassesLoader = null;

    protected function setUp(): void
    {
        $this->setUpTemporaryStorage('dto-generator-');
        $this->generatedNamespace = 'Generated'.bin2hex(random_bytes(6)).'\Api';
    }

    protected function tearDown(): void
    {
        if ($this->generatedClassesLoader)
            spl_autoload_unregister($this->generatedClassesLoader);

        $this->tearDownTemporaryStorage();
    }

    protected function target(): GenerationTarget
    {
        return new GenerationTarget($this->generatedNamespace, $this->storage);
    }

    protected function writer(bool $lenient = false): DtoWriter
    {
        return new DtoWriter($this->target(), 'test', $lenient);
    }

    protected function clientWriter(): ClientWriter
    {
        return new ClientWriter($this->target(), 'test');
    }

    /**
     * @param DtoClass[] $classes
     *
     * @return array<string,string> relative class name => source
     */
    protected function render(array $classes, bool $lenient = false): array
    {
        $sources = [];
        foreach ($classes as $class)
            $sources[ltrim("{$class->namespace}\\{$class->name}", '\\')] = $this->writer($lenient)->render($class);

        return $sources;
    }

    /**
     * @param DtoClass[] $classes
     */
    protected function writeAndAutoload(array $classes, bool $lenient = false): void
    {
        $this->writer($lenient)->write($classes);
        $this->autoloadGeneratedClasses();
    }

    protected function autoloadGeneratedClasses(): void
    {
        if ($this->generatedClassesLoader)
            return;
        $prefix = $this->generatedNamespace.'\\';
        $this->generatedClassesLoader = function (string $class) use ($prefix) {
            if (!str_starts_with($class, $prefix))
                return;
            $file = $this->storage->path(str_replace('\\', '/', substr($class, strlen($prefix))).'.php');
            if (is_file($file))
                require $file;
        };
        spl_autoload_register($this->generatedClassesLoader);
    }

    protected function generatedClass(string $relativeName): string
    {
        return $this->generatedNamespace.'\\'.$relativeName;
    }
}

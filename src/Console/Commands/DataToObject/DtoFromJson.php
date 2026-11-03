<?php

namespace Cube\Console\Commands\DataToObject;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Data\DataToObject\Generator\DtoWriter;
use Cube\Data\DataToObject\Generator\GenerationTarget;
use Cube\Data\DataToObject\Generator\Sources\SampleSource;
use Cube\Utils\Console;
use Cube\Utils\Path;

class DtoFromJson extends Command
{
    public function getScope(): string
    {
        return 'make';
    }

    public function getHelp(): string
    {
        return "Generate a DTO Class from JSON body";
    }

    public function getManual(): array
    {
        return [
            'make:dto-from-json [--namespace=<namespace>]',
            '',
            'Generate DataToObject classes from a JSON sample, asked interactively',
            '',
            '--namespace  Namespace to generate into, inside a composer PSR-4 prefix',
            '             (default : <Application>/Integration/DataToObject)',
        ];
    }

    public function execute(Args $args): int
    {
        $namespace = $args->getValue(long: 'namespace');
        $target = $namespace
            ? GenerationTarget::fromNamespace($namespace)
            : GenerationTarget::fromDirectory(Path::join(Console::chooseApplication(), 'Integration', 'DataToObject'));

        if (!$target)
            return $this->abort("Namespace [{$namespace}] is outside every PSR-4 prefix of composer.json, its classes could not be autoloaded");

        $sampleData = readline('Sample data (JSON) ?');
        $sampleData = json_decode($sampleData, true, flags: JSON_THROW_ON_ERROR);

        do {
            $rootName = readline('Root class name ? ');
        } while (!preg_match('/^[A-Z][a-zA-Z0-9]+$/', $rootName));

        foreach ((new DtoWriter($target, $this->getFullIdentifier()))->write((new SampleSource($rootName, $sampleData))->toClasses()) as $file)
            Console::print(Path::toRelative($file));

        return 0;
    }
}

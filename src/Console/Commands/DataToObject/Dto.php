<?php

namespace Cube\Console\Commands\DataToObject;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Data\DataToObject\Generator\DtoWriter;
use Cube\Data\DataToObject\Generator\GenerationTarget;
use Cube\Data\DataToObject\Generator\Sources\SampleSource;
use Cube\Utils\Console;
use Cube\Utils\Path;

class Dto extends Command
{
    use ReadsJsonDocuments;

    protected const CLASS_NAME_FORMAT = '/^[A-Z][a-zA-Z0-9]+$/';

    public function getScope(): string
    {
        return 'make';
    }

    public function getHelp(): string
    {
        return 'Generate DTO classes from a JSON sample (file, URL or JSON text)';
    }

    public function getManual(): array
    {
        return [
            'make:dto [<file|url|json>] [--name=<class>] [--namespace=<namespace>] [--header=<header>...]',
            '',
            'Generate DataToObject classes from a JSON sample : the root class, and one class per nested object',
            'in a sub-namespace. Without an argument, the sample is asked interactively',
            '',
            '--name       Name of the root class, starting with an uppercase letter (asked when missing)',
            '--namespace  Namespace to generate into, inside a composer PSR-4 prefix',
            '             (default : <Application>/Integration/DataToObject)',
            '--header     Header sent when fetching the sample from a URL, as "Name: value" (repeat it to send several)',
        ];
    }

    public function execute(Args $args): int
    {
        $location = $args->getValues()[0] ?? readline('Sample data (JSON) ? ');

        if (null === $sample = $this->readJsonDocument($location, $args, acceptsInlineJson: true))
            return 1;

        if (array_is_list($sample))
            return $this->abort('The sample is a JSON list : give one of its objects');

        $rootName = $args->getValue(long: 'name');
        if ($rootName && !preg_match(self::CLASS_NAME_FORMAT, $rootName))
            return $this->abort("--name [{$rootName}] is not a class name starting with an uppercase letter");

        while (!$rootName || !preg_match(self::CLASS_NAME_FORMAT, $rootName))
            $rootName = readline('Root class name ? ');

        $namespace = $args->getValue(long: 'namespace');
        $target = $namespace
            ? GenerationTarget::fromNamespace($namespace)
            : GenerationTarget::fromDirectory(Path::join(Console::chooseApplication(), 'Integration', 'DataToObject'));

        if (!$target)
            return $this->abort("Namespace [{$namespace}] is outside every PSR-4 prefix of composer.json, its classes could not be autoloaded");

        foreach ((new DtoWriter($target, $this->getFullIdentifier()))->write((new SampleSource($rootName, $sample))->toClasses()) as $file)
            Console::print(Path::toRelative($file));

        return 0;
    }
}

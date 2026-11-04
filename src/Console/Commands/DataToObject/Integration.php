<?php

namespace Cube\Console\Commands\DataToObject;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Data\DataToObject\Generator\ClientWriter;
use Cube\Data\DataToObject\Generator\DtoNames;
use Cube\Data\DataToObject\Generator\DtoWriter;
use Cube\Data\DataToObject\Generator\GenerationTarget;
use Cube\Data\DataToObject\Generator\Sources\OpenApiSource;
use Cube\Utils\Console;
use Cube\Utils\Path;

class Integration extends Command
{
    use ReadsJsonDocuments;

    public function getScope(): string
    {
        return 'make';
    }

    public function getHelp(): string
    {
        return 'Generate an HttpClient and its DTO classes from an OpenAPI document (JSON file or URL)';
    }

    public function getManual(): array
    {
        return [
            'make:integration <openapi.json|url> [--namespace=<namespace>] [--client-name=<name>] [--endpoint=<endpoint>...] [--lenient] [--header=<header>...]',
            '',
            'Generate a DataToObject class per object schema in <Namespace>\\Refs\\Output to read responses, an',
            'AutoDataToObject class in <Namespace>\\Refs\\Input for each schema a request sends, an <Operation>Request',
            'in <Namespace>\\Http\\Requests and an <Operation>Response in <Namespace>\\Http\\Responses per operation,',
            'and <Name>Client, an HttpClient with one method per operation,',
            'reading its URL and credentials from <Name>Configuration',
            '',
            '--namespace  Namespace to generate into, inside a composer PSR-4 prefix',
            '             (default : <Application>/Integration/<document title>)',
            '--client-name <Name> of <Name>Client and <Name>Configuration, starting with an uppercase letter',
            '             (default : the last segment of the namespace)',
            '--endpoint   Only generate this operation and the schemas it uses, as an operationId or "METHOD /path"',
            '             (repeat it to select several)',
            '--lenient    Make every property optional and nullable, whatever the document declares as required',
            '--header     Header sent when fetching the document from a URL, as "Name: value" (repeat it to send several)',
        ];
    }

    public function execute(Args $args): int
    {
        $location = $args->getValues()[0] ?? null;
        if (!$location)
            return $this->abort('Usage : '.$this->getManual()[0]);

        if (null === $document = $this->readJsonDocument($location, $args))
            return 1;

        if (!str_starts_with((string) ($document['openapi'] ?? ''), '3.'))
            return $this->abort("{$location} is not an OpenAPI 3 document : expected an \"openapi\" field starting with 3.");

        $source = new OpenApiSource($document, $args->getValues(long: 'endpoint'), $this->isUrl($location) ? $location : null);
        if ($unmatched = $source->unmatchedEndpoints())
            return $this->abort('No operation matches ['.implode('], [', $unmatched).'] : give an operationId or "METHOD /path"');

        $clientName = $args->getValue(long: 'client-name');
        if (null !== $clientName && !preg_match(DtoNames::CLASS_NAME_FORMAT, $clientName))
            return $this->abort("--client-name [{$clientName}] is not a class name starting with an uppercase letter");

        $namespace = $args->getValue(long: 'namespace');
        $target = $namespace
            ? GenerationTarget::fromNamespace($namespace)
            : GenerationTarget::fromDirectory(Path::join(Console::chooseApplication(), 'Integration', DtoNames::className($document['info']['title'] ?? 'Api')));

        if (!$target)
            return $this->abort("Namespace [{$namespace}] is outside every PSR-4 prefix of composer.json, its classes could not be autoloaded");

        $written = [
            ...(new DtoWriter($target, $this->getFullIdentifier(), $args->has(long: 'lenient')))->write($source->toClasses()),
            ...(new ClientWriter($target, $this->getFullIdentifier(), $clientName))->write($source->toClient()),
        ];

        foreach ($written as $file)
            Console::print(Path::toRelative($file));

        return 0;
    }
}

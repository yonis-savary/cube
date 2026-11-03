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
use Cube\Web\Http\Request;

class Dto extends Command
{
    public function getScope(): string
    {
        return 'make';
    }

    public function getHelp(): string
    {
        return 'Generate DTO classes from the schemas of an OpenAPI document (JSON file or URL)';
    }

    public function getManual(): array
    {
        return [
            'make:dto <openapi.json|url> [--namespace=<namespace>] [--endpoint=<endpoint>...] [--lenient] [--header=<header>...]',
            '',
            'Generate a DataToObject class per object schema in <Namespace>\\Refs\\Output to read responses, an',
            'AutoDataToObject class in <Namespace>\\Refs\\Input for each schema a request sends, an <Operation>Request',
            'in <Namespace>\\Http\\Requests and an <Operation>Response in <Namespace>\\Http\\Responses per operation,',
            'and <Name>Client, an HttpClient with one method per operation,',
            'reading its URL and credentials from <Name>Configuration (<Name> : the last segment of the namespace)',
            '',
            '--namespace  Namespace to generate into, inside a composer PSR-4 prefix',
            '             (default : <Application>/Integration/<document title>)',
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

        $isUrl = (bool) preg_match('~^https?://~i', $location);
        if (!$isUrl && !is_file($location))
            return $this->abort("No file at [{$location}] : give the path of an OpenAPI document, or its http(s) URL");

        $headers = [];
        foreach ($args->getValues(long: 'header') as $header) {
            if (!$isUrl)
                return $this->abort('--header only applies when the document is fetched from a URL');

            if (!str_contains($header, ':'))
                return $this->abort("Header [{$header}] is not of the form \"Name: value\"");

            [$name, $value] = explode(':', $header, 2);
            $headers[trim($name)] = trim($value);
        }

        $response = $isUrl
            ? (new Request('GET', $location, headers: $headers))->fetch()
            : null;

        if ($response && !$response->isOk())
            return $this->abort("Could not fetch {$location} : the server answered {$response->getStatusCode()}");

        $content = $response
            ? $response->getBody()
            : file_get_contents($location);
        if (!json_validate($content))
            return $this->abort("{$location} is not a valid JSON document : ".json_last_error_msg());

        $document = json_decode($content, true);
        if (!str_starts_with((string) ($document['openapi'] ?? ''), '3.'))
            return $this->abort("{$location} is not an OpenAPI 3 document : expected an \"openapi\" field starting with 3.");

        $source = new OpenApiSource($document, $args->getValues(long: 'endpoint'), $isUrl ? $location : null);
        if ($unmatched = $source->unmatchedEndpoints())
            return $this->abort('No operation matches ['.implode('], [', $unmatched).'] : give an operationId or "METHOD /path"');

        $namespace = $args->getValue(long: 'namespace');
        $target = $namespace
            ? GenerationTarget::fromNamespace($namespace)
            : GenerationTarget::fromDirectory(Path::join(Console::chooseApplication(), 'Integration', DtoNames::className($document['info']['title'] ?? 'Api')));

        if (!$target)
            return $this->abort("Namespace [{$namespace}] is outside every PSR-4 prefix of composer.json, its classes could not be autoloaded");

        $written = [
            ...(new DtoWriter($target, $this->getFullIdentifier(), $args->has(long: 'lenient')))->write($source->toClasses()),
            ...(new ClientWriter($target, $this->getFullIdentifier()))->write($source->toClient()),
        ];

        foreach ($written as $file)
            Console::print(Path::toRelative($file));

        return 0;
    }
}

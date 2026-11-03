<?php

namespace Cube\Console\Commands\DataToObject;

use Cube\Console\Args;
use Cube\Web\Http\Request;

trait ReadsJsonDocuments
{
    protected function isUrl(string $location): bool
    {
        return (bool) preg_match('~^https?://~i', $location);
    }

    /**
     * Reads a JSON document from a file, an http(s) URL fetched with the `--header` values, or, when
     * `$acceptsInlineJson` is set, the JSON text itself. Null means the reason was already printed
     *
     * @return ?array<mixed>
     */
    protected function readJsonDocument(string $location, Args $args, bool $acceptsInlineJson = false): ?array
    {
        $isUrl = $this->isUrl($location);
        $isFile = !$isUrl && is_file($location);
        $isInline = !$isUrl && !$isFile && $acceptsInlineJson && json_validate($location);

        if (!$isUrl && !$isFile && !$isInline) {
            $this->abort($acceptsInlineJson
                ? "[{$location}] is neither a file, an http(s) URL nor a valid JSON text"
                : "No file at [{$location}] : give the path of a JSON document, or its http(s) URL");

            return null;
        }

        $headers = [];
        foreach ($args->getValues(long: 'header') as $header) {
            if (!$isUrl) {
                $this->abort('--header only applies when the document is fetched from a URL');

                return null;
            }

            if (!str_contains($header, ':')) {
                $this->abort("Header [{$header}] is not of the form \"Name: value\"");

                return null;
            }

            [$name, $value] = explode(':', $header, 2);
            $headers[trim($name)] = trim($value);
        }

        $response = $isUrl
            ? (new Request('GET', $location, headers: $headers))->fetch()
            : null;

        if ($response && !$response->isOk()) {
            $this->abort("Could not fetch {$location} : the server answered {$response->getStatusCode()}");

            return null;
        }

        $content = match (true) {
            $isInline => $location,
            $isUrl => $response->getBody(),
            default => file_get_contents($location),
        };

        if (!json_validate($content)) {
            $this->abort("{$location} is not a valid JSON document : ".json_last_error_msg());

            return null;
        }

        $document = json_decode($content, true);
        if (!is_array($document)) {
            $this->abort("{$location} holds a JSON scalar : expected an object or a list");

            return null;
        }

        return $document;
    }
}

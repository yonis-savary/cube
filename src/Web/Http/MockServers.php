<?php

namespace Cube\Web\Http;

use Cube\Core\Autoloader;
use Cube\Core\Component;
use InvalidArgumentException;

class MockServers
{
    use Component;

    protected array $register = [];

    public function get(string $httpClientClass): ?HttpMockServer
    {
        return $this->register[$httpClientClass] ?? null;
    }

    /**
     * @param class-string<HttpClient>|HttpClient $httpClient
     * @param class-string<HttpMockServer>|HttpMockServer $server
     */
    public function set(HttpClient|string $httpClient, HttpMockServer|string $server): void {
        if (!Autoloader::extends($httpClient, HttpClient::class))
            throw new InvalidArgumentException('$httpClientClass should extends from HttpClient class');

        if (is_string($server) && !Autoloader::extends($server, HttpMockServer::class))
            throw new InvalidArgumentException('$server should extends from HttpMockServer class');

        $httpClientClass = is_string($httpClient) ? $httpClient: $httpClient::class;
        $this->register[$httpClientClass] = is_string($server) ? new $server : $server;
    }
}
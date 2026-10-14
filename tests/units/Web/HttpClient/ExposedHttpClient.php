<?php

namespace Cube\Tests\Units\Web\HttpClient;

use Cube\Env\Logger\Logger;
use Cube\Env\Logger\NullLogger;
use Cube\Web\Http\HttpClient;
use Cube\Web\Http\Request;

/**
 * Opens the pieces a fetch() is made of, so they can be checked without a network round trip.
 */
class ExposedHttpClient extends HttpClient
{
    public int $baseLoggerCalls = 0;
    public int $baseUserAgentCalls = 0;

    public function __construct(
        protected ?string $base = null,
        protected ?string $socket = null
    ) {}

    public function baseURL(): ?string
    {
        return $this->base;
    }

    public function baseUnixSocket(): ?string
    {
        return $this->socket;
    }

    public function baseLogger(): Logger
    {
        ++$this->baseLoggerCalls;

        return new NullLogger();
    }

    public function baseUserAgent(): string
    {
        ++$this->baseUserAgentCalls;

        return 'CubeTestAgent/1.0';
    }

    /** @var array<string,mixed> What the last handle build was given */
    public array $handleArguments = [];

    public function toCurlHandle(
        Request $request,
        ?int $timeout = null,
        ?string $userAgent = null,
        ?Logger $logger = null,
        ?callable $curlMutator = null
    ): \CurlHandle {
        $this->handleArguments = ['userAgent' => $userAgent, 'logger' => $logger];

        return parent::toCurlHandle($request, $timeout, $userAgent, $logger, $curlMutator);
    }

    public function publicPath(Request $request): string
    {
        return $this->path($request);
    }

    public static function publicOriginOf(string $url): string
    {
        return self::originOf($url);
    }

    public function publicParseHeaders(string $headers, bool $lowercaseNames = false): array
    {
        return $this->parseHeaders($headers, $lowercaseNames);
    }
}

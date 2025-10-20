<?php

namespace Cube\Web\Http;

use Cube\Data\Bunch;
use Cube\Env\Logger\Logger;
use Cube\Env\Logger\NullLogger;
use Cube\Utils\Path;
use Cube\Utils\Utils;

class HttpClient
{
    /** Debug the CURL Request build process */
    public const DEBUG_REQUEST_CURL = 0b0000_0001;

    /** Debug the sent headers */
    public const DEBUG_REQUEST_HEADERS = 0b0000_0010;

    /** Debug the sent body */
    public const DEBUG_REQUEST_BODY = 0b0000_0100;

    /** Debug the sent response data */
    public const DEBUG_REQUEST = 0b0000_1111;

    /** Debug the returned headers */
    public const DEBUG_RESPONSE_HEADERS = 0b0001_0000;

    /** Debug the returned body */
    public const DEBUG_RESPONSE_BODY = 0b0010_0000;

    /** Debug the returned response data */
    public const DEBUG_RESPONSE = 0b1111_0000;

    /** Debug both sent & received headers */
    public const DEBUG_ESSENTIALS = self::DEBUG_REQUEST_HEADERS | self::DEBUG_RESPONSE_HEADERS;

    /** Debug every sent/received informations */
    public const DEBUG_ALL = 0b1111_1111;

    protected Request $request;
    protected Logger $logger;

    protected float $lastFetchDurationMicro;

    public function baseLogger(): Logger
    {
        return new NullLogger();
    }

    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    public function baseURL(): ?string
    {
        return null;
    }

    public function baseHeaders(): array
    {
        return [];
    }

    public function baseUserAgent(): string
    {
        return 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/112.0';
    }

    public function baseURLParameters(): array
    {
        return [];
    }

    public function basePostParameters(): array
    {
        return [];
    }

    public function get(string $path, array $getParams = [], array $headers = []): Response
    {
        return (new Request('GET', $path, $getParams, [], $headers))->fetch(httpClient: $this);
    }

    public function post(string $path, array $postParams = [], array $getParams = [], array $uploads = [], array $headers = []): Response
    {
        return (new Request('POST', $path, $getParams, $postParams, $headers, $uploads))->fetch(httpClient: $this);
    }

    public function put(string $path, array $getParams = [], array $postParams = [], array $headers = []): Response
    {
        return (new Request('PUT', $path, $getParams, $postParams, $headers))->fetch(httpClient: $this);
    }

    public function patch(string $path, array $getParams = [], array $postParams = [], array $headers = []): Response
    {
        return (new Request('PATCH', $path, $getParams, $postParams, $headers))->fetch(httpClient: $this);
    }

    public function delete(string $path, array $getParams = [], array $postParams = [], array $headers = []): Response
    {
        return (new Request('DELETE', $path, $getParams, $postParams, $headers))->fetch(httpClient: $this);
    }

    public function getJson(string $path, mixed $body = [], array $headers = []): Response
    {
        $headers['content-type'] = 'application/json';

        return (new Request('GET', $path, [], [], $headers, body: json_encode($body)))->fetch(httpClient: $this);
    }

    public function postJson(string $path, mixed $body = [], array $headers = []): Response
    {
        $headers['content-type'] = 'application/json';

        return (new Request('POST', $path, [], [], $headers, body: json_encode($body)))->fetch(httpClient: $this);
    }

    public function putJson(string $path, mixed $body = [], array $headers = []): Response
    {
        $headers['content-type'] = 'application/json';

        return (new Request('PUT', $path, [], [], $headers, body: json_encode($body)))->fetch(httpClient: $this);
    }

    public function patchJson(string $path, mixed $body = [], array $headers = []): Response
    {
        $headers['content-type'] = 'application/json';

        return (new Request('PATCH', $path, [], [], $headers, body: json_encode($body)))->fetch(httpClient: $this);
    }

    public function deleteJson(string $path, mixed $body = [], array $headers = []): Response
    {
        $headers['content-type'] = 'application/json';

        return (new Request('DELETE', $path, [], [], $headers, body: json_encode($body)))->fetch(httpClient: $this);
    }

    /**
     * Build a cURL handle for the Request object.
     *
     * @param ?int    $timeout   Optional cURL timeout limit (seconds)
     * @param ?string $userAgent Optional cURL user-agent header to use
     *
     * @return \CurlHandle Instance containing every request information
     */
    public function toCurlHandle(
        ?int $timeout = null,
        ?string $userAgent = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/112.0',
        ?Logger $logger = null,
        ?callable $curlMutator = null
    ): \CurlHandle {
        $request = $this->request;
        $logger ??= new NullLogger();

        $logger->info('Building CURL handle');

        $path = $request->getPath();
        if ($base = $this->baseURL()) {
            $path = Path::join($base, $path);
        }

        $getParams = array_merge($this->baseURLParameters(), $request->get() ?? []);
        $urlParams = count($getParams)
            ? '?'.http_build_query($getParams, '', '&')
            : '';

        $url = trim($path.$urlParams);

        $logger->info("CURL URL [{$url}]");
        $handle = curl_init($url);
        $options = [];

        $method = $request->getMethod();
        switch (strtoupper($method)) {
            case 'GET':
                /* GET by default */ ;
                $logger->info('GET Params string = {params}', ['params' => $urlParams]);
                break;

            case 'POST':
                $logger->info('Using CURLOPT_POST');
                $options[CURLOPT_POST] = true;
                break;

            case 'HEAD':
                $logger->info('Using CURLOPT_NOBODY');
                $options[CURLOPT_NOBODY] = true;
                break;

            case 'PUT':
            case 'PATCH':
                $logger->info('Using CURLOPT_PUT');
                $options[CURLOPT_PUT] = true;
                break;

            default:
                $logger->info('Setting CURLOPT_CUSTOMREQUEST to {method}', ['method' => $method]);
                $options[CURLOPT_CUSTOMREQUEST] = $method;
                break;
        }

        $options[CURLOPT_RETURNTRANSFER] = true;
        $options[CURLOPT_HEADER] = true;

        $postParams = array_merge($this->basePostParameters(), $request->post() ?? []);
        $uploads = $request->getUploads();
        $body = $request->getBody();

        if ($request->isJSON() && $body) {
            $logger->info('Setting JSON CURLOPT_POSTFIELDS to {fields}', ['fields' => $body]);
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        elseif (count($postParams) || count($uploads)) {
            $arrayDetails = [];
            foreach ($postParams as $key => &$values) {
                if (!is_array($values))
                    continue;

                for ($i = 0; $i < count($values); ++$i)
                    $arrayDetails[$key."[{$i}]"] = $values[$i];

                unset($postParams[$key]);
            }
            $postParams = array_merge($postParams, $arrayDetails);

            foreach ($uploads as $upload) {
                $file = $upload->tempName;

                $postParams[$upload->inputName] = function_exists('curl_file_create')
                    ? curl_file_create($file)
                    : '@'.realpath($file);
            }

            $logger->info('Setting CURLOPT_POSTFIELDS to');
            $logger->info('{fields}', ['fields' => $postParams]);

            $options[CURLOPT_POSTFIELDS] = $request->isFormEncoded()
                ? Bunch::unzip($postParams)->map(fn ($pair) => $pair[0].'='.urlencode($pair[1]))->join('&')
                : $postParams
            ;
        }

        if ($timeout) {
            $logger->info('Setting CURLOPT_CONNECTTIMEOUT, CURLOPT_TIMEOUT to {timeout}', ['timeout' => $timeout]);
            $options[CURLOPT_CONNECTTIMEOUT] = $timeout;
            $options[CURLOPT_TIMEOUT] = $timeout;
        }

        $headers = array_merge($this->baseHeaders(), $request->getHeaders());

        if ($userAgent) {
            $logger->info("Using 'user-agent' : {useragent}", ['useragent' => $userAgent]);
            $headers['user-agent'] = $userAgent;
        }

        $headersStrings = Bunch::unzip($headers)
            ->map(fn($pair) => sprintf("%s: %s", ...$pair) )
            ->toArray();

        $logger->info('Setting CURLOPT_HTTPHEADER to');
        $logger->info('{headers}', ['headers' => $headersStrings]);
        $options[CURLOPT_HTTPHEADER] = $headersStrings;

        curl_setopt_array($handle, $options);

        if ($curlMutator)
            $curlMutator($handle);

        return $handle;
    }

    /**
     * Fetch a Request target with Curl !
     *
     * @param Logger $logger             Optional Logger that can be used to log info about the request/response
     * @param int    $timeout            Optional request timeout (seconds)
     * @param string $userAgent          User-agent to use with curl
     * @param bool   $supportRedirection If `true`, `fetch()` will follow redirect responses
     *
     * @throws \JsonException Possibly when parsing the response body if fetched JSON is incorrect
     */
    public function fetch(
        ?Logger $logger = null,
        ?int $timeout = null,
        ?string $userAgent = null,
        bool $supportRedirection = true,
        int $logFlags = self::DEBUG_ESSENTIALS,
        ?callable $curlMutator = null
    ): Response {
        $request = $this->request;
        $handle = $this->toCurlHandle($timeout, $userAgent, $logger, $curlMutator);
        $userAgent ??= $this->baseUserAgent();

        $logger ??= $this->baseLogger();

        if (Utils::valueHasFlag($logFlags, self::DEBUG_REQUEST_HEADERS)) {
            $logger->info('{method} {path}', ['method' => $request->getMethod(), 'path' => $request->getPath()]);
            $logger->info('{headers}', ['headers' => $request->getHeaders()]);
        }

        if (Utils::valueHasFlag($logFlags, self::DEBUG_REQUEST_BODY)) {
            $logger->info("GET\n{get}", ['get' => $request->get()]);
            $logger->info("POST\n{post}", ['post' => $request->post()]);
            $logger->info("BODY\n{body}", ['body' => $request->getBody()]);
        }

        $startTime = hrtime(true);
        if (!($result = curl_exec($handle))) {
            throw new \RuntimeException(sprintf('Curl error %s: %s', curl_errno($handle), curl_error($handle)));
        }

        $this->lastFetchDurationMicro = (hrtime(true) - $startTime) / 1000000; // ns => ms

        $headerSize = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        $resStatus = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if (Utils::valueHasFlag($logFlags, self::DEBUG_RESPONSE_HEADERS)) {
            $logger->info('Got [{status}] with [{size}] bytes of data', ['status' => $resStatus, 'size' => strlen($result)]);
        }

        $resHeaders = $this->parseHeaders(
            substr($result, 0, $headerSize),
            true
        );

        if (Utils::valueHasFlag($logFlags, self::DEBUG_RESPONSE_HEADERS)) {
            $logger->info('Got Headers');
            $logger->info('{headers}', ['headers' => $resHeaders]);
        }

        if ($supportRedirection && $nextURL = ($resHeaders['location'] ?? null)) {
            $logger->info('Got redirected to [{url}]', ['url' => $nextURL]);
            $request = new Request('GET', $nextURL);

            return $request->fetch(
                $logger,
                $timeout,
                $userAgent,
                $supportRedirection
            );
        }

        $resBody = substr($result, $headerSize);

        if (Utils::valueHasFlag($logFlags, self::DEBUG_RESPONSE_BODY)) {
            $logger->info('Got Body');
            $logger->info($resBody);
        }

        return new Response($resStatus, $resBody, $resHeaders);
    }

    public function lastDuration(): int
    {
        return $this->lastFetchDurationMicro;
    }

    /**
     * Parse raw HTTP Headers (string)
     * to an associative array of data as `HeaderName => HeaderValue`.
     */
    protected function parseHeaders(string $headers, bool $lowercaseNames=false): array
    {
        $result = Bunch::fromExplode("\n", $headers)
            ->filter(fn ($line) => str_contains($line, ':'))
            ->map(fn($line) => explode(':', trim($line), 2))
            ->zip()
        ;

        if ($lowercaseNames)
            $result = array_change_key_case($result, CASE_LOWER);

        return $result;
    }
}

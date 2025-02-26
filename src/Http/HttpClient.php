<?php

namespace Cube\Http;

use CurlHandle;
use RuntimeException;
use Cube\Data\Bunch;
use Cube\Logger\Logger;
use Cube\Logger\NullLogger;
use Cube\Utils\Path;
use Cube\Utils\Utils;

class HttpClient
{

    /** Debug the CURL Request build process */
    const DEBUG_REQUEST_CURL     = 0b0000_0001;

    /** Debug the sent headers */
    const DEBUG_REQUEST_HEADERS  = 0b0000_0010;

    /** Debug the sent body */
    const DEBUG_REQUEST_BODY     = 0b0000_0100;

    /** Debug the sent response data */
    const DEBUG_REQUEST          = 0b0000_1111;

    /** Debug the returned headers */
    const DEBUG_RESPONSE_HEADERS = 0b0001_0000;

    /** Debug the returned body */
    const DEBUG_RESPONSE_BODY    = 0b0010_0000;

    /** Debug the returned response data */
    const DEBUG_RESPONSE         = 0b1111_0000;

    /** Debug both sent & received headers */
    const DEBUG_ESSENTIALS       = self::DEBUG_REQUEST_HEADERS | self::DEBUG_RESPONSE_HEADERS;

    /** Debug every sent/received informations */
    const DEBUG_ALL              = 0b1111_1111;

    protected Request $request;

    protected float $lastFetchDurationMicro;

    public function __construct(Request $request)
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

    public function get(string $path, array $getParams=[], array $headers=[]): Response
    {
        return (new Request("GET", $path, $getParams, [], $headers))->fetch(httpClient: $this);
    }

    public function post(string $path, array $postParams=[], array $getParams=[], array $uploads=[], array $headers=[]): Response
    {
        return (new Request("POST", $path, $getParams, $postParams, $headers, $uploads))->fetch(httpClient: $this);
    }

    public function put(string $path, array $getParams=[], array $postParams=[], array $headers=[]): Response
    {
        return (new Request("PUT", $path, $getParams, $postParams, $headers))->fetch(httpClient: $this);
    }

    public function patch(string $path, array $getParams=[], array $postParams=[], array $headers=[]): Response
    {
        return (new Request("PATCH", $path, $getParams, $postParams, $headers))->fetch(httpClient: $this);
    }

    public function delete(string $path, array $getParams=[], array $postParams=[], array $headers=[]): Response
    {
        return (new Request("DELETE", $path, $getParams, $postParams, $headers))->fetch(httpClient: $this);
    }

    public function getJson(string $path, mixed $body=[], array $headers=[]): Response
    {
        $headers['content-type'] = 'application/json';
        return (new Request("GET", $path, [], [], $headers, body: json_encode($body) ))->fetch(httpClient: $this);
    }

    public function postJson(string $path, mixed $body=[], array $headers=[]): Response
    {
        $headers['content-type'] = 'application/json';
        return (new Request("POST", $path, [], [], $headers, body: json_encode($body) ))->fetch(httpClient: $this);
    }

    public function putJson(string $path, mixed $body=[], array $headers=[]): Response
    {
        $headers['content-type'] = 'application/json';
        return (new Request("PUT", $path, [], [], $headers, body: json_encode($body) ))->fetch(httpClient: $this);
    }

    public function patchJson(string $path, mixed $body=[], array $headers=[]): Response
    {
        $headers['content-type'] = 'application/json';
        return (new Request("PATCH", $path, [], [], $headers, body: json_encode($body) ))->fetch(httpClient: $this);
    }

    public function deleteJson(string $path, mixed $body=[], array $headers=[]): Response
    {
        $headers['content-type'] = 'application/json';
        return (new Request("DELETE", $path, [], [], $headers, body: json_encode($body) ))->fetch(httpClient: $this);
    }



    /**
     * Build a cURL handle for the Request object
     *
     * @param ?int $timeout Optional cURL timeout limit (seconds)
     * @param ?string $userAgent Optional cURL user-agent header to use
     * @return CurlHandle Instance containing every request information
     */
    public function toCurlHandle(
        ?int $timeout=null,
        ?string $userAgent='Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/112.0',
        ?Logger $logger=null
    ): CurlHandle
    {
        $request = $this->request;
        $logger ??= new NullLogger;

        $logger->info('');
        $logger->info('');
        $logger->info('Building CURL handle');

        $thisGET = array_merge($this->baseURLParameters(), $request->get() ?? []);
        $thisPOST = array_merge($this->basePostParameters(), $request->post() ?? []);
        $thisMethod = $request->getMethod();
        $thisUploads = $request->getUploads();
        $headers = array_merge($this->baseHeaders(), $request->getHeaders());
        $isJSONRequest = $request->isJSON();
        $thisBody = $request->getBody();

        $getParams = count($thisGET) ? '?' . http_build_query($request->get(), '', '&') : '';

        $path = $request->getPath();
        if ($base = $this->baseURL())
            $path = Path::join($base, $path);

        $url = trim($path . $getParams);

        $logger->info("CURL URL [$url]");
        $handle = curl_init($url);

        switch (strtoupper($thisMethod))
        {
            case 'GET':
                /* GET by default*/ ;
                $logger->info('GET Params string = {params}', ['params' => $getParams]);
                break;
            case 'POST':
                $logger->info('Using CURLOPT_POST');
                curl_setopt($handle, CURLOPT_POST, true);
                break;
            case 'HEAD':
                $logger->info('Using CURLOPT_NOBODY');
                curl_setopt($handle, CURLOPT_NOBODY, true);
                break;
            case 'PUT':
            case 'PATCH':
                $logger->info('Using CURLOPT_PUT');
                curl_setopt($handle, CURLOPT_PUT, true);
                break;
            default:
                $logger->info('Setting CURLOPT_CUSTOMREQUEST to {method}', ['method' => $thisMethod]);
                curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $thisMethod);
                break;
        }

        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_HEADER, true);

        if ($isJSONRequest && $thisBody)
        {
            $logger->info('Setting JSON CURLOPT_POSTFIELDS to');
            $logger->info('{fields}', ['fields' => $thisBody]);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $thisBody);
        }
        else if (count($thisPOST) || count($thisUploads))
        {
            $arrayDetails = [];
            foreach ($thisPOST as $key => &$values)
            {
                if (!is_array($values))
                    continue;

                for ($i=0; $i<count($values); $i++)
                    $arrayDetails[$key."[$i]"] = $values[$i];

                unset($thisPOST[$key]);
            }
            $thisPOST = array_merge($thisPOST, $arrayDetails);

            $postClone = $thisPOST;

            if (count($thisUploads))
            {
                foreach ($thisUploads as $upload)
                {
                    $file = $upload->tempName;
                    $inputName = $upload->inputName;

                    if (function_exists('curl_file_create'))
                        $postClone[$inputName] = curl_file_create($file);
                    else
                        $postClone[$inputName] = '@' . realpath($file);
                }
            }

            $logger->info('Setting CURLOPT_POSTFIELDS to');
            $logger->info('{fields}', ['fields' => $postClone]);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $postClone);
        }

        if ($timeout)
        {
            $logger->info('Setting CURLOPT_CONNECTTIMEOUT, CURLOPT_TIMEOUT to {timeout}', ['timeout' => $timeout]);
            curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($handle, CURLOPT_TIMEOUT, $timeout);
        }

        if ($userAgent)
        {
            $logger->info("Using 'user-agent' : {useragent}", ['useragent' => $userAgent]);
            $headers['user-agent'] = $userAgent;
        }

        $headersStrings = [];
        foreach ($headers as $key => &$value)
            $headersStrings[] = "$key: $value";

        $logger->info('Setting CURLOPT_HTTPHEADER to');
        $logger->info('{headers}', ['headers' => $headersStrings]);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headersStrings);

        return $handle;
    }

    /**
     * Parse raw HTTP Headers (string)
     * to an associative array of data with `HeaderName => HeaderValue`
     */
    protected function parseHeaders(string $headers): array
    {
        return Bunch::fromExplode("\n", $headers)
        ->filter(fn($line) => str_contains($line, ':'))
        ->map(function($line){
            $line = preg_replace("/\r$/", '', $line);
            list($headerName, $headerValue) = explode(':', $line, 2);
            return [trim($headerName), trim($headerValue)];
        })
        ->zip();
    }

    /**
     * Fetch a Request target with Curl !
     * @param Logger $logger Optional Logger that can be used to log info about the request/response
     * @param int $timeout Optional request timeout (seconds)
     * @param string $userAgent User-agent to use with curl
     * @param bool $supportRedirection If `true`, `fetch()` will follow redirect responses
     * @throws \JsonException Possibly when parsing the response body if fetched JSON is incorrect
     */
    function fetch(
        ?Logger $logger=null,
        ?int $timeout=null,
        ?string $userAgent=null,
        bool $supportRedirection=true,
        int $logFlags = self::DEBUG_ESSENTIALS
    ): Response
    {
        $request = $this->request;
        $handle = $this->toCurlHandle($timeout, $userAgent, $logger, $logFlags);
        $userAgent ??= $this->baseUserAgent();

        $logger ??= new NullLogger;

        if (Utils::valueHasFlag($logFlags, self::DEBUG_REQUEST_HEADERS))
        {
            $logger->info("{method} {path}", ["method" => $request->getMethod(), 'path' => $request->getPath()]);
            $logger->info('{headers}', ['headers' => $request->getHeaders()]);
        }

        if (Utils::valueHasFlag($logFlags, self::DEBUG_REQUEST_BODY))
        {
            $logger->info("GET\n{get}",  ['get'  => $request->get()]);
            $logger->info("POST\n{post}",['post' => $request->post()]);
            $logger->info("BODY\n{body}",['body' => $request->getBody()]);
        }

        $startTime = hrtime(true);
        if (!($result = curl_exec($handle)))
            throw new RuntimeException(sprintf('Curl error %s: %s', curl_errno($handle), curl_error($handle)));

        $this->lastFetchDurationMicro = (hrtime(true) - $startTime) / 1000000; // ns => ms

        $headerSize = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        $resStatus = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if (Utils::valueHasFlag($logFlags, self::DEBUG_RESPONSE_HEADERS))
            $logger->info('Got [{status}] with [{size}] bytes of data', ['status' => $resStatus, 'size' => strlen($result)]);

        $resHeaders = substr($result, 0, $headerSize);
        $resHeaders = $this->parseHeaders($resHeaders);
        $resHeaders = array_change_key_case($resHeaders, CASE_LOWER);

        if (Utils::valueHasFlag($logFlags, self::DEBUG_RESPONSE_HEADERS))
        {
            $logger->info('Got Headers');
            $logger->info('{headers}', ['headers' => $resHeaders]);
        }

        if ($supportRedirection && $nextURL = ($resHeaders['location'] ?? null))
        {
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

        if (Utils::valueHasFlag($logFlags, self::DEBUG_RESPONSE_BODY))
        {
            $logger->info('Got Body');
            $logger->info($resBody);
        }

        return new Response($resStatus, $resBody, $resHeaders);
    }

    public function lastDuration(): int
    {
        return $this->lastFetchDurationMicro;
    }
}
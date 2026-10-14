<?php

namespace Cube\Web\Http;

use Cube\Data\Bunch;
use Cube\Env\Logger\Logger;
use Cube\Utils\Text;
use Cube\Web\Http\Rules\AnyParam;
use Cube\Web\Http\Rules\ArrayParam;
use Cube\Web\Http\Rules\ObjectParam;
use Cube\Web\Http\Rules\Rule;
use Cube\Web\Http\Rules\ValidationReturn;
use Cube\Web\Router\Route;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;

class Request extends HttpMessage
{
    protected string $method;
    protected string $path;
    protected array $get = [];
    protected array $post = [];
    /**
     * @var Upload[]
     */
    protected array $uploads = [];
    protected ?string $ip;
    protected array $cookies = [];

    protected ?Route $route = null;

    protected array $slugValues = [];
    protected array $slugObjects = [];

    /**
     * @param Upload[] $uploads
     */
    public function __construct(
        string $method = 'GET',
        string $path = '/',
        array $get = [],
        array $post = [],
        array $headers = [],
        array $uploads = [],
        string $body = '',
        ?string $ip = null,
        array $cookies = []
    ) {
        [$path, $query] = explode('?', $path, 2) + [1 => ''];
        parse_str($query, $queryParameters);

        $this->method = $method;
        $this->path = self::normalizePath($path);
        $this->get = self::parseDictionaryValues($get + $queryParameters);
        $this->post = self::parseDictionaryValues($post);
        $this->setHeaders($headers);
        $this->uploads = $uploads;
        $this->setBody($body);
        $this->ip = $ip;
        $this->cookies = $cookies;

        if ($this->isJSON() && $body && !count($post)) {
            $decodedBody = json_decode($body, true);
            if (is_array($decodedBody)) {
                $this->post = $decodedBody;
            }
        }
    }

    public static function fromRequest(Request $source): static
    {
        $newReq = new static();

        $newReq->method = $source->method;
        $newReq->path = $source->path;
        $newReq->get = $source->get;
        $newReq->post = $source->post;
        $newReq->uploads = $source->uploads;
        $newReq->ip = $source->ip;
        $newReq->cookies = $source->cookies;
        $newReq->route = $source->route;
        $newReq->slugValues = $source->slugValues;
        $newReq->headers = $source->headers;
        $newReq->body = $source->body;

        return $newReq;
    }

    public function logSelf(?LoggerInterface $logger = null): static
    {
        $logger ??= Logger::getInstance();
        $logger->log('info', '{method} {path}', [
            'method' => $this->getMethod(),
            'path' => $this->getPath(),
        ]);

        return $this;
    }

    public static function fromGlobals(): static
    {
        $headers = function_exists('getallheaders')
            ? getallheaders()
            : [];

        return new static(
            $_SERVER['REQUEST_METHOD'] ?? php_sapi_name(),
            $_SERVER['REQUEST_URI'] ?? '/',
            $_GET,
            $_POST,
            $headers,
            self::getUploadsArray($_FILES),
            file_get_contents('php://input'),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_COOKIE
        );
    }

    public static function fromPsrRequest(ServerRequestInterface $request): static
    {
        $headers = $request->getHeaders();
        foreach ($headers as &$header) {
            $header = join(', ', $header);
        }

        $parsedBody = $request->getParsedBody();

        return new static(
            $request->getMethod(),
            $request->getUri()->getPath(),
            $request->getQueryParams(),
            is_array($parsedBody) ? $parsedBody : [],
            $headers,
            self::getUploadsFromPsrFiles($request->getUploadedFiles()),
            (string) $request->getBody(),
            $request->getServerParams()['REMOTE_ADDR'] ?? null,
            $request->getCookieParams()
        );
    }

    public function param(string $name, mixed $default = null): mixed
    {
        return $this->get[$name] ?? $this->post[$name] ?? $default;
    }

    public function params(array $keys, array $default = []): array
    {
        $data = [];
        foreach ($keys as $key) {
            $data[$key] = $this->param($key, $default[$key] ?? null);
        }

        return $data;
    }

    public function collect(string $key): Bunch
    {
        return Bunch::wrap($this->param($key));
    }

    public function list(array $keys, array $default = []): array
    {
        return array_values($this->params($keys, $default));
    }

    public function upload(string $inputName): ?Upload
    {
        return Bunch::wrap($this->uploads)
            ->first(fn (Upload $upload) => $upload->inputName === $inputName)
        ;
    }

    /**
     * @return Upload[]
     */
    public function uploads(string $inputName): array
    {
        return Bunch::wrap($this->uploads)
            ->filter(fn (Upload $upload) => $upload->inputName === $inputName, true)
            ->get()
        ;
    }

    /** @return Upload[] */
    public function getUploads(): array
    {
        return $this->uploads;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function get(): array
    {
        return $this->get;
    }

    public function post(): array
    {
        return $this->post;
    }

    public function all(bool $getParamsGotPriority = true): array
    {
        return $getParamsGotPriority
            ? array_merge($this->post, $this->get)
            : array_merge($this->get, $this->post);
    }

    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function setRoute(Route $route)
    {
        $this->route = $route;
    }

    public function getRoute(): ?Route
    {
        return $this->route;
    }

    public function setSlugValues(array $values): void
    {
        $this->slugValues = $values;
    }

    public function getSlugValues(): array
    {
        return $this->slugValues;
    }

    public function setSlugObjects(array $values): void
    {
        $this->slugObjects = $values;
    }

    public function getSlugObject(string $name, mixed $default=null): mixed
    {
        return $this->slugObjects[$name] ?? $default;
    }

    public function getSlugObjects(): array
    {
        return $this->slugObjects;
    }

    /**
     * @return Rule|Rule[]
     */
    public function getRules(): array|Rule
    {
        return [];
    }

    final public function getObjectParam(): Rule
    {
        $rules = $this->getRules();
        if ($rules instanceof Rule)
            return $rules;

        return count($rules)
            ? new ObjectParam($rules, false)
            : new AnyParam();
    }

    protected function getBodyMergedWithUpload(Rule $validator): array
    {
        $rules = $validator instanceof ObjectParam ? $validator->getRules() : [];

        $uploads = [];
        foreach ($this->uploads as $upload)
            $uploads[$upload->inputName][] = $upload;

        foreach ($uploads as $inputName => &$inputUploads)
            $inputUploads = self::shapeUploadsFor($inputUploads, $rules[$inputName] ?? null);

        return array_merge($this->all(), $uploads);
    }

    /**
     * @param Upload[] $uploads
     *
     * @return Upload|Upload[]
     */
    protected static function shapeUploadsFor(array $uploads, Rule|array|null $rule): Upload|array
    {
        if ($rule instanceof ArrayParam || count($uploads) > 1)
            return $uploads;

        return $uploads[0];
    }

    public function validate(): ValidationReturn
    {
        $validator = $this->getObjectParam();

        return $validator->validate($this->getBodyMergedWithUpload($validator), 'request');
    }

    public function isValid(): bool
    {
        return $this->validate()->isValid();
    }

    public function validated(?string $key=null, ?Rule $validator = null): mixed
    {
        $validator ??= $this->getObjectParam();
        $result = $validator->validate($this->getBodyMergedWithUpload($validator), 'request')->getResult();

        if (!$key)
            return $result;

        if (array_key_exists($key, $result))
            return $result[$key];

        if ($validator instanceof ObjectParam && array_key_exists($key, $validator->getRules()))
            return null;

        throw new InvalidArgumentException("$key key does not exists in validated values");
    }

    /**
     * @param bool $validated If `true`, only data from validated() is returned, otherwise, data from all()
     * @return array Request data filtered by keys
     */
    public function only(array $keys, bool $validated = true): array {
        return array_intersect_key(
            $validated
                ? $this->validated()
                : $this->all(),
            array_flip($keys)
        );
    }

    public function fetch(
        ?Logger $logger = null,
        ?int $timeout = null,
        ?string $userAgent = null,
        bool $supportRedirection = true,
        int $logFlags = HttpClient::DEBUG_ESSENTIALS,
        ?HttpClient $httpClient = null,
        ?callable $curlMutator = null
    ): Response {
        $httpClient ??= new HttpClient();

        return $httpClient->fetch(
            $this,
            $logger,
            $timeout,
            $userAgent,
            $supportRedirection,
            $logFlags,
            $curlMutator
        );
    }

    public function fetchAsync(
        ?Logger $logger = null,
        ?string $userAgent = null,
        ?HttpClient $httpClient = null,
    ): bool {
        $httpClient ??= new HttpClient();

        return $httpClient->fetchAsync(
            $this,
            $logger,
            $userAgent
        );
    }

    protected static function normalizePath(string $path): string
    {
        return '/' === $path
            ? $path
            : Text::dontEndsWith($path, '/');
    }

    protected static function parseDictionaryValues(array $data): array
    {
        foreach ($data as &$value) {
            if (!($value instanceof \Stringable || is_string($value))) {
                continue;
            }

            $lower = strtolower("{$value}");

            if ('null' === $lower) {
                $value = null;
            } elseif ('false' === $lower || 'off' === $lower) {
                $value = false;
            } elseif ('true' === $lower || 'on' === $lower) {
                $value = true;
            }
        }

        return $data;
    }

    protected static function getUploadsArray(array $data): array
    {
        $cleanedUploads = [];

        foreach ($data as $inputName => $fileData) {
            foreach (self::splitUploads($fileData) as $upload)
                $cleanedUploads[] = new Upload($upload, $inputName);
        }

        return self::withoutEmptyInputs($cleanedUploads);
    }

    /**
     * @param Upload[] $uploads
     *
     * @return Upload[]
     */
    protected static function withoutEmptyInputs(array $uploads): array
    {
        // A form sends its empty file inputs too, each reporting UPLOAD_ERR_NO_FILE : none of them is an upload
        return (new Bunch($uploads))
            ->filter(fn (Upload $upload) => UPLOAD_ERR_NO_FILE !== $upload->error, true)
            ->get();
    }

    /**
     * @return array<int,array> One `$_FILES`-like entry per uploaded file of an input
     */
    protected static function splitUploads(array $fileData): array
    {
        if (!is_array($fileData['name']))
            return [$fileData];

        $uploads = [];
        foreach (array_keys($fileData['name']) as $index) {
            $upload = array_map(fn ($values) => $values[$index], $fileData);

            // Deeper levels (files[a][b]) are not supported : left out rather than read as a broken upload
            if (is_array($upload['name']))
                continue;

            $uploads[] = $upload;
        }

        return $uploads;
    }

    /**
     * @param array<string,UploadedFileInterface|UploadedFileInterface[]> $files
     *
     * @return Upload[]
     */
    protected static function getUploadsFromPsrFiles(array $files): array
    {
        $uploads = [];

        foreach ($files as $inputName => $fileOrFiles) {
            if (!is_array($fileOrFiles))
                $fileOrFiles = [$fileOrFiles];

            foreach ($fileOrFiles as $file)
                $uploads[] = Upload::fromPsrUploadedFile($file, $inputName);
        }

        return self::withoutEmptyInputs($uploads);
    }
}

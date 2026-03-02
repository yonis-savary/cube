<?php

namespace Cube\Web\Http;

use Cube\Data\Bunch;
use Cube\Web\Http\Rules\Validator;
use Cube\Env\Logger\Logger;
use Cube\Utils\Text;
use Cube\Web\Http\Rules\AnyParam;
use Cube\Web\Http\Rules\ObjectParam;
use Cube\Web\Http\Rules\Rule;
use Cube\Web\Http\Rules\ValidationReturn;
use Cube\Web\Router\Route;
use InvalidArgumentException;
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
     * @var string $method
     * @var string $path
     * @var array $get
     * @var array $post
     * @var array $headers
     * @var Upload[] $uploads
     * @var string $body
     * @var ?string $ip
     * @var array $cookies
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
        $this->method = $method;
        $this->path = preg_replace('/\?.+/', '', $path);
        $this->get = $get;
        $this->post = $post;
        $this->setHeaders($headers);
        $this->uploads = $uploads;
        $this->setBody($body);
        $this->ip = $ip;
        $this->cookies = $cookies;

        if ($this->isJSON() && $body && !count($post)) {
            $decodedBody = json_decode($body, JSON_THROW_ON_ERROR);
            if (is_array($decodedBody)) {
                $this->post = $decodedBody;
            }
        }
    }

    public static function fromRequest(Request $source): self
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

    public function logSelf(?LoggerInterface $logger = null)
    {
        $logger ??= Logger::getInstance();
        $logger->log('info', '{method} {path}', [
            'method' => $this->getMethod(),
            'path' => $this->getPath(),
        ]);
    }

    public static function fromGlobals(): self
    {
        $headers = function_exists('getallheaders')
            ? getallheaders()
            : [];

        $get = self::parseDictionaryValues($_GET);
        $post = self::parseDictionaryValues($_POST);

        $uploads = self::getUploadsArray($_FILES);

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if ('/' != $uri) {
            $uri = Text::dontEndsWith($uri, '/');
        }

        $request = new self(
            $_SERVER['REQUEST_METHOD'] ?? php_sapi_name(),
            $uri,
            $get,
            $post,
            $headers,
            $uploads,
            file_get_contents('php://input'),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_COOKIE
        );

        return $request;
    }

    public function param(string $name, mixed $default = null): mixed
    {
        return $this->body[$name] ?? $this->get[$name] ?? $this->post[$name] ?? $default;
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
        return Bunch::of($this->param($key));
    }

    public function list(array $keys, array $default = []): array
    {
        return array_values($this->params($keys, $default));
    }

    public function upload(string $inputName): ?Upload
    {
        return Bunch::of($this->uploads)
            ->first(fn (Upload $upload) => $upload->inputName === $inputName)
        ;
    }

    /**
     * @return Upload[]
     */
    public function uploads(string $inputName): array
    {
        return Bunch::of($this->uploads)
            ->filter(fn (Upload $upload) => $upload->inputName === $inputName)
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

    protected function getBodyMergedWithUpload(): array 
    {
        $uploads = [];
        foreach ($this->uploads as $upload) {
            $uploads[$upload->inputName] ??= [];
            $uploads[$upload->inputName][] = $upload;
        }

        foreach ($uploads as &$array) {
            $array = count($array) === 1 ? $array[0]: $array;
        }

        return array_merge($this->all(), $uploads);
    }

    public function validate(): ValidationReturn
    {
        return $this->getObjectParam()->validate($this->getBodyMergedWithUpload(), 'request');
    }

    public function isValid(): bool
    {
        return $this->validate()->isValid();
    }

    public function validated(?string $key=null, ?Rule $validator = null): mixed
    {
        $rule ??= $this->getObjectParam();
        $result = $rule->validate($this->getBodyMergedWithUpload(), 'request')->getResult();

        if (!$key)
            return $result;

        if (!array_key_exists($key, $result))
            throw new InvalidArgumentException("$key key does not exists in validated values");

        return $result[$key];
    }

    public function fetch(
        ?Logger $logger = null,
        ?int $timeout = null,
        ?string $userAgent = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/112.0',
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
            $isMultiple = is_array($fileData['name']);
            $uploadCount = $isMultiple ? count($fileData['name']) : null;
            $toAdd = [];

            if ($isMultiple) {
                $keys = array_keys($fileData);
                for ($i = 0; $i < $uploadCount; ++$i) {
                    $toAdd[] = array_combine($keys, array_map(fn ($arr) => $arr[$i], $fileData));
                }
            } else {
                $toAdd[] = $fileData;
            }

            foreach ($toAdd as &$upload) {
                $upload = new Upload($upload, $inputName);
            }

            array_push($cleanedUploads, ...$toAdd);
        }

        return $cleanedUploads;
    }
}

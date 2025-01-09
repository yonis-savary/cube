<?php

namespace YonisSavary\Cube\Http;

use Stringable;
use YonisSavary\Cube\Data\Bunch;
use YonisSavary\Cube\Utils\Text;
use YonisSavary\Cube\Web\Route;

class Request extends HttpMessage
{
    protected string $method;
    protected string $path;
    protected array $get = [];
    protected array $post = [];
    protected array $uploads = [];
    protected ?string $ip;
    protected array $cookies = [];

    protected ?Route $route = null;

    protected array $slugValues = [];

    /**
     * @var string $method
     * @var string $path
     * @var array $get
     * @var array $post
     * @var array $headers
     * @var array<Upload> $uploads
     * @var string $body
     * @var ?string $ip
     * @var array $cookies
     */
    public function __construct(
        string $method="GET",
        string $path="/",
        array $get=[],
        array $post=[],
        array $headers=[],

        array $uploads=[],
        string $body="",
        ?string $ip=null,
        array $cookies=[]
    )
    {
        $this->method = $method;
        $this->path = $path;
        $this->get = $get;
        $this->post = $post;
        $this->setHeaders($headers);
        $this->uploads = $uploads;
        $this->setBody($body);
        $this->ip = $ip;
        $this->cookies = $cookies;
    }

    protected static function parseDictionaryValues(array $data): array
    {
        foreach ($data as $key => &$value)
        {
            if (!($value instanceof Stringable || is_string($value)))
                continue;

            $lower = strtolower("$value");

            if ($lower === 'null')
                $value = null ;
            else if ($lower === 'false' || $lower === 'off')
                $value = false;
            else if ($lower === 'true' || $lower === 'on')
                $value = true;
        }
        return $data;
    }


    protected static function getUploadsArray(array $data): array
    {
        $cleanedUploads = [];

        foreach($data as $inputName => $fileData)
        {
            $isMultiple = is_array($fileData['name']);
            $uploadCount = $isMultiple ? count($fileData['name']): null;
            $toAdd = [];

            if ($isMultiple)
            {
                $keys = array_keys($fileData);
                for ($i=0; $i<$uploadCount; $i++)
                    $toAdd[] = array_combine($keys, array_map(fn($arr) => $arr[$i], $fileData));
            }
            else
            {
                $toAdd[] = $fileData;
            }

            foreach ($toAdd as &$upload)
                $upload = new Upload($upload, $inputName);

            array_push($cleanedUploads, ...$toAdd);
        }

        return $cleanedUploads;
    }

    public static function fromGlobals(): self
    {
        $headers = function_exists('getallheaders') ?
            getallheaders() :
            [];

        $get = self::parseDictionaryValues($_GET);
        $post = self::parseDictionaryValues($_POST);

        $uploads = self::getUploadsArray($_FILES);

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if ($uri != "/")
            $uri = Text::dontEndsWith($uri, "/");

        $request = new self (
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

    public function param(string $name, mixed $default=null): mixed
    {
        return $this->get[$name] ?? $this->post[$name] ?? $default;
    }

    public function params(array $keys, array $default=[]): array
    {
        $data = [];
        foreach ($keys as $key)
            $data[$key] = $this->param($key, $default[$key] ?? null);

        return $data;
    }

    public function list(array $keys, array $default=[]): array
    {
        return array_values($this->params($keys, $default));
    }

    public function upload(string $inputName): ?Upload
    {
        return Bunch::of($this->uploads)
            ->first(fn(Upload $upload) => $upload->inputName === $inputName);
    }

    /**
     * @return array<Upload>
     */
    public function uploads(string $inputName): array
    {
        return Bunch::of($this->uploads)
            ->filter(fn(Upload $upload) => $upload->inputName === $inputName)
            ->get();
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function get(): array
    {
        return $this->get;
    }

    public function post(): array
    {
        return $this->post;
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
}
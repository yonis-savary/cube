<?php

namespace Cube\Test;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Cube\Core\Autoloader;
use Cube\Database\Database;
use Cube\Http\Request;
use Cube\Http\Upload;
use Cube\Logger\Logger;
use Cube\Models\Model;
use Cube\Web\CubeServer;

abstract class CubeTestCase extends TestCase
{
    protected Database $database;
    protected ?CubeServer $server = null;

    abstract public function getDatabase(): Database;

    private function refreshDatabase(): void
    {
        $this->database = $this->getDatabase();
    }

    /**
     * @param class-string<Model> $model
     */
    public function assertModelExists(string $model, array $data): void
    {
        if (!Autoloader::extends($model, Model::class))
            throw new InvalidArgumentException("Given \$model must be a Model");

        self::assertTrue(
            $model::existsWhere($data, $this->database),
            "$model model does not contains row with specified values : " . print_r($data, true)
        );
    }


    protected function setUp(): void
    {
        $this->refreshDatabase();
    }

    protected function assertResponseOf(Request $request): ResponseAssert
    {
        return new ResponseAssert($request->fetch(
            Logger::getInstance()
        ));
    }

    public function getServer(): CubeServer
    {
        return new CubeServer();
    }

    private function safeGetServer(): CubeServer
    {
        if (!$this->server)
            $this->server = $this->getServer();

        return $this->server;
    }

    protected function path(string $path): string
    {
        return $this->safeGetServer()->path($path);
    }

    public function get(string $path, array $getParams=[], array $headers=[]): ResponseAssert
    {
        return $this->assertResponseOf(new Request("GET", $this->path($path), $getParams, [], $headers));
    }

    public function post(string $path, array $postParams=[], array $getParams=[], array $uploads=[], array $headers=[]): ResponseAssert
    {
        return $this->assertResponseOf(new Request("POST", $this->path($path), $getParams, $postParams, $headers, $uploads));
    }

    public function put(string $path, array $getParams=[], array $postParams=[], array $headers=[]): ResponseAssert
    {
        return $this->assertResponseOf(new Request("PUT", $this->path($path), $getParams, $postParams, $headers));
    }

    public function patch(string $path, array $getParams=[], array $postParams=[], array $headers=[]): ResponseAssert
    {
        return $this->assertResponseOf(new Request("PATCH", $this->path($path), $getParams, $postParams, $headers));
    }

    public function delete(string $path, array $getParams=[], array $postParams=[], array $headers=[]): ResponseAssert
    {
        return $this->assertResponseOf(new Request("DELETE", $this->path($path), $getParams, $postParams, $headers));
    }

    public function getJson(string $path, mixed $body=[], array $headers=[]): ResponseAssert
    {
        $headers['content-type'] = 'application/json';
        return $this->assertResponseOf(new Request("GET", $this->path($path), [], [], $headers, body: json_encode($body) ));
    }

    public function postJson(string $path, mixed $body=[], array $headers=[]): ResponseAssert
    {
        $headers['content-type'] = 'application/json';
        return $this->assertResponseOf(new Request("POST", $this->path($path), [], [], $headers, body: json_encode($body) ));
    }

    public function putJson(string $path, mixed $body=[], array $headers=[]): ResponseAssert
    {
        $headers['content-type'] = 'application/json';
        return $this->assertResponseOf(new Request("PUT", $this->path($path), [], [], $headers, body: json_encode($body) ));
    }

    public function patchJson(string $path, mixed $body=[], array $headers=[]): ResponseAssert
    {
        $headers['content-type'] = 'application/json';
        return $this->assertResponseOf(new Request("PATCH", $this->path($path), [], [], $headers, body: json_encode($body) ));
    }

    public function deleteJson(string $path, mixed $body=[], array $headers=[]): ResponseAssert
    {
        $headers['content-type'] = 'application/json';
        return $this->assertResponseOf(new Request("DELETE", $this->path($path), [], [], $headers, body: json_encode($body) ));
    }


    public function makeFakeUpload(
        string $inputName,
        int $size,
        ?string $basename=null,
        string $mime="text/plain",
        int $error=UPLOAD_ERR_OK
        ): Upload
    {
        $tmpFile = tempnam(sys_get_temp_dir(), "fake-upload-");
        file_put_contents($tmpFile, str_repeat("0", $size));

        $data = [
            "name" => $basename ?? basename($tmpFile),
            "type" => $mime,
            "tmp_name" => $tmpFile,
            "error" => $error,
            "size" => $size
        ];

        return new Upload($data, $inputName);
    }

    public function makeFakeUploadFromContent(
        string $inputName,
        string $content,
        ?string $basename=null,
        string $mime="text/plain",
        int $error=UPLOAD_ERR_OK
        ): Upload
    {
        $tmpFile = tempnam(sys_get_temp_dir(), "fake-upload-");
        file_put_contents($tmpFile, $content);
        $size = filesize($tmpFile);

        $data = [
            "name" => $basename ?? basename($tmpFile),
            "type" => $mime,
            "tmp_name" => $tmpFile,
            "error" => $error,
            "size" => $size
        ];

        return new Upload($data, $inputName);
    }
}
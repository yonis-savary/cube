<?php

namespace Cube\Test;

use Cube\Core\Autoloader;
use Cube\Data\Database\Database;
use Cube\Web\Http\Request;
use Cube\Web\Http\Upload;
use Cube\Data\Models\Model;
use Cube\Web\Helpers\CubeServer;
use PHPUnit\Framework\TestCase;

/**
 * This test class allows you to interact with your application
 * through methods such as :
 * - `this->makeFakeUpload`
 * - `this->makeFakeUploadFromContent`
 * - `this->get`
 * - `this->getJson`
 * - `this->post`
 * - ...
 *
 * Using this test class will reset the global database
 * for each test method, making every test independent
 */
abstract class CubeTestCase extends TestCase
{
    protected Database $database;
    protected ?CubeServer $server = null;

    protected function setUp(): void
    {
        TestContext::getInstance()->useNewEmptyApplicationDatabase();
    }

    /**
     * @param class-string<Model> $model
     */
    public function assertModelExists(string $model, array $data): void
    {
        if (!Autoloader::extends($model, Model::class)) {
            throw new \InvalidArgumentException('Given $model must be a Model');
        }

        self::assertTrue(
            $model::existsWhere($data, $this->database),
            "{$model} model does not contains row with specified values : ".print_r($data, true)
        );
    }

    public function get(string $path, array $getParams = [], array $headers = []): ResponseAssert
    {
        return $this->assertResponseOf(new Request('GET', $path, $getParams, [], $headers));
    }

    public function post(string $path, array $postParams = [], array $getParams = [], array $uploads = [], array $headers = []): ResponseAssert
    {
        return $this->assertResponseOf(new Request('POST', $path, $getParams, $postParams, $headers, $uploads));
    }

    public function put(string $path, array $getParams = [], array $postParams = [], array $headers = []): ResponseAssert
    {
        return $this->assertResponseOf(new Request('PUT', $path, $getParams, $postParams, $headers));
    }

    public function patch(string $path, array $getParams = [], array $postParams = [], array $headers = []): ResponseAssert
    {
        return $this->assertResponseOf(new Request('PATCH', $path, $getParams, $postParams, $headers));
    }

    public function delete(string $path, array $getParams = [], array $postParams = [], array $headers = []): ResponseAssert
    {
        return $this->assertResponseOf(new Request('DELETE', $path, $getParams, $postParams, $headers));
    }

    public function getJson(string $path, mixed $body = [], array $headers = []): ResponseAssert
    {
        $headers['content-type'] = 'application/json';

        return $this->assertResponseOf(new Request('GET', $path, [], [], $headers, body: json_encode($body)));
    }

    public function postJson(string $path, mixed $body = [], array $headers = []): ResponseAssert
    {
        $headers['content-type'] = 'application/json';

        return $this->assertResponseOf(new Request('POST', $path, [], [], $headers, body: json_encode($body)));
    }

    public function putJson(string $path, mixed $body = [], array $headers = []): ResponseAssert
    {
        $headers['content-type'] = 'application/json';

        return $this->assertResponseOf(new Request('PUT', $path, [], [], $headers, body: json_encode($body)));
    }

    public function patchJson(string $path, mixed $body = [], array $headers = []): ResponseAssert
    {
        $headers['content-type'] = 'application/json';

        return $this->assertResponseOf(new Request('PATCH', $path, [], [], $headers, body: json_encode($body)));
    }

    public function deleteJson(string $path, mixed $body = [], array $headers = []): ResponseAssert
    {
        $headers['content-type'] = 'application/json';

        return $this->assertResponseOf(new Request('DELETE', $path, [], [], $headers, body: json_encode($body)));
    }

    public function makeFakeUpload(
        string $inputName,
        int $size,
        ?string $basename = null,
        string $mime = 'text/plain',
        int $error = UPLOAD_ERR_OK
    ): Upload {
        $tmpFile = tempnam(sys_get_temp_dir(), 'fake-upload-');
        file_put_contents($tmpFile, str_repeat('0', $size));

        $data = [
            'name' => $basename ?? basename($tmpFile),
            'type' => $mime,
            'tmp_name' => $tmpFile,
            'error' => $error,
            'size' => $size,
        ];

        return new Upload($data, $inputName);
    }

    public function makeFakeUploadFromContent(
        string $inputName,
        string $content,
        ?string $basename = null,
        string $mime = 'text/plain',
        int $error = UPLOAD_ERR_OK
    ): Upload {
        $tmpFile = tempnam(sys_get_temp_dir(), 'fake-upload-');
        file_put_contents($tmpFile, $content);
        $size = filesize($tmpFile);

        $data = [
            'name' => $basename ?? basename($tmpFile),
            'type' => $mime,
            'tmp_name' => $tmpFile,
            'error' => $error,
            'size' => $size,
        ];

        return new Upload($data, $inputName);
    }

    protected function assertResponseOf(Request $request): ResponseAssert
    {
        $router = TestContext::getInstance()->getRouter();
        $response = $router->route($request);

        return new ResponseAssert($response);
    }
}

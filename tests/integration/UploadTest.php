<?php

namespace YonisSavary\Cube\Tests\Integration;

use YonisSavary\Cube\Database\Database;
use YonisSavary\Cube\Logger\Logger;
use YonisSavary\Cube\Test\CubeTestCase;
use YonisSavary\Cube\Utils\File;
use YonisSavary\Cube\Utils\Shell;
use YonisSavary\Cube\Web\CubeServer;

class UploadTest extends CubeTestCase
{
    public function getServer(): CubeServer
    {
        $installation = Utils::getDummyApplicationStorage();
        $server = new CubeServer(null, $installation->path("Public"), Logger::getInstance());

        Shell::executeInDirectory("php do clear-database", $server->getPublicStorage()->parent()->getRoot());
        return $server;
    }

    public function getDatabase(): Database
    {
        return Utils::getIntegrationDatabase();
    }

    public function test_upload()
    {
        $this->post('/documents')
            ->assertUnprocessableContent();

        $tooBigUpload = $this->makeFakeUploadFromContent('to-upload', json_encode(['key' => str_repeat('0', File::MEGABYTES)]));
        $this->post('/documents', uploads: [$tooBigUpload])
            ->assertUnprocessableContent();

        $correctUpload = $this->makeFakeUploadFromContent("to-upload", json_encode(["Hello" => "Goodbye"]));
        $documentName =
            $this->post('/documents', uploads:[$correctUpload])
            ->assertCreated()
            ->assertIsJson()
            ->json();

        $this->assertIsString($documentName);
        $this->assertStringStartsWith("file-", $documentName);
        $this->assertStringEndsWith(".json", $documentName);

        $document = $this->get("/documents/$documentName")
            ->assertOk()
            ->json();

        $this->assertEquals(["Hello" => "Goodbye"], $document);
    }
}
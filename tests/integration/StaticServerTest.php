<?php

namespace YonisSavary\Cube\Tests\Integration;

use YonisSavary\Cube\Database\Database;
use YonisSavary\Cube\Logger\Logger;
use YonisSavary\Cube\Test\CubeTestCase;
use YonisSavary\Cube\Utils\Shell;
use YonisSavary\Cube\Web\CubeServer;

class StaticServerTest extends CubeTestCase
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

    public function test_servesFiles()
    {
        $body = $this->get("/")
            ->assertOk()
            ->body();
        $this->assertStringContainsString("I'm a document", $body);

        $file = $this->get("/my-file.txt")
            ->assertOk()
            ->body();
        $this->assertEquals('Hello!', $file);
    }
}
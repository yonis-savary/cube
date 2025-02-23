<?php

namespace Cube\Tests\Integration;

use Cube\Database\Database;
use Cube\Logger\Logger;
use Cube\Test\CubeTestCase;
use Cube\Utils\Shell;
use Cube\Web\CubeServer;

class StaticServerTest extends CubeTestCase
{
    public function getServer(): CubeServer
    {
        $server = Utils::getDummyServer();

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
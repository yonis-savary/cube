<?php

namespace Cube\Tests\Integration;

use Cube\Data\Database\Database;
use Cube\Test\CubeTestCase;
use Cube\Utils\Shell;
use Cube\Web\Helpers\CubeServer;

/**
 * @internal
 */
class StaticServerTest extends CubeTestCase
{
    public function getServer(): CubeServer
    {
        $server = Utils::getDummyServer();

        Shell::executeInDirectory('php do clear-database', $server->getPublicStorage()->parent()->getRoot());

        return $server;
    }

    public function getDatabase(): Database
    {
        return Utils::getIntegrationDatabase();
    }

    public function testServesFiles()
    {
        $body = $this->get('/')
            ->assertOk()
            ->body()
        ;
        $this->assertStringContainsString("I'm a document", $body);

        $file = $this->get('/my-file.txt')
            ->assertOk()
            ->body()
        ;
        $this->assertEquals('Hello!', $file);
    }
}

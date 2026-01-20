<?php

namespace Cube\Tests\Integration;

use Cube\Data\Database\Database;
use Cube\Test\CubeTestCase;
use Cube\Utils\Shell;
use Cube\Web\Helpers\CubeServer;

/**
 * @internal
 */
class PolicyTest extends CubeTestCase
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

    public function testUserPolicy()
    {
        $this->get('/user/1')->assertUnauthorized();
        $this->get('/user/2')->assertOk();
        $this->get('/user/999')->assertNotFound();
    }

}

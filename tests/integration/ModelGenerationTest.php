<?php

namespace Cube\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ModelGenerationTest extends TestCase
{
    public function testModelGeneration()
    {
        $storage = Utils::getDummyApplicationStorage();

        $this->assertFileExists($storage->path('App/Models/User.php'));
        $this->assertFileExists($storage->path('App/Models/Module.php'));
        $this->assertFileExists($storage->path('App/Models/ModuleUser.php'));
    }
}

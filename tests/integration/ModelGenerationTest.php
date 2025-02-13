<?php

namespace YonisSavary\Cube\Tests\Integration;

use PHPUnit\Framework\TestCase;
use YonisSavary\Cube\Utils\Shell;

class ModelGenerationTest extends TestCase
{
    public function test_modelGeneration()
    {
        $storage = Utils::getDummyApplicationStorage();

        $this->assertFileExists($storage->path("App/Models/User.php"));
        $this->assertFileExists($storage->path("App/Models/Module.php"));
        $this->assertFileExists($storage->path("App/Models/ModuleUser.php"));
    }
}
<?php

namespace Cube\Tests\Units\Configuration;

use PHPUnit\Framework\TestCase;
use Cube\Configuration\Configuration;
use Cube\Configuration\GenericElement;
use Cube\Env\Storage\StorageConfiguration;
use Cube\Utils\Path;

class ConfigurationTest extends TestCase
{
    public function test_construct_and_resolve()
    {
        $config = new Configuration(
            new GenericElement("generic-1", ["mike" => "bob"]),
            new GenericElement("generic-2", ["bob" => "mike"]),
        );
        $generic = $config->resolveGeneric("generic-1", false);
        $this->assertEquals(["mike" => "bob"], $generic);

        $generic = $config->resolveGeneric("generic-2", false);
        $this->assertEquals(["bob" => "mike"], $generic);
    }
}
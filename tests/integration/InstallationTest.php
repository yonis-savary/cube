<?php

namespace YonisSavary\Cube\Tests\Integration;

use PHPUnit\Framework\TestCase;
use YonisSavary\Cube\Utils\Path;
use YonisSavary\Cube\Utils\Shell;

class InstallationTest extends TestCase
{
    public function test_installation()
    {
        $storage = Utils::getDummyApplicationStorage();

        $cubeRoot = Path::normalize(__DIR__ . "/../..");
        $storage->write("composer.json", json_encode([
            "autoload" => [
                "psr-4" => [
                    "App\\" => "App"
                ]
            ],
            "require" => [
                "yonis-savary/cube" => "dev-main"
            ],
            "repositories" => [
                [
                    "type" => "path",
                    "url" => $cubeRoot,
                    "options" => [
                        "symlink" => false
                    ]
                ]
                ],

                "scripts" => [
                    "post-update-cmd" => [
                        "cp -r vendor/yonis-savary/cube/server/* ."
                    ]
                ]
        ]));

        Shell::executeInDirectory("composer install", $storage->getRoot());

        $this->assertTrue($storage->isDirectory("vendor/yonis-savary/cube"));
        $this->assertTrue($storage->isFile("do"));

        $proc = Shell::executeInDirectory("php do hello-world", $storage->getRoot());
        $this->assertStringStartsWith("Hello World !", $proc->getOutput());


        //Utils::removeApplicationStorage($storage);
    }
}
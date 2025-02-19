<?php

namespace Cube\Tests\Integration;

use Cube\Database\Database;
use Cube\Logger\Logger;
use Cube\Test\CubeTestCase;
use Cube\Utils\Shell;
use Cube\Web\CubeServer;

class RouterTest extends CubeTestCase
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

    public function test_ping()
    {
        $this->get("/ping")->assertOk()->assertJsonContent("OK");
        $this->post("/ping")->assertMethodNotAllowed();
    }

    public function test_product()
    {
        $this->get("/product")->assertMethodNotAllowed();
        $this->post("/product")->assertUnprocessableContent();

        $response = $this->postJson("/product", [
            "name" => "my-product",
            "price_dollar" => 42,
            'managers' => [['manager'=>'Joe'], ['manager'=>'Randy'], ['manager'=>'Bill']]
        ]);
        $product = $response->json();
        $response->assertOk();

        $this->assertIsNumeric($product["id"]);
        $this->assertEquals("my-product", $product["name"]);
        $this->assertEquals(42, $product["price_dollar"]);
        $this->assertCount(3, $product['managers']);

        $productId = $product["id"];

        $this->get("/product/" . $productId)
            ->assertOk()
            ->assertJsonContent($product);
    }
}
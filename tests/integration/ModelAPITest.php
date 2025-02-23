<?php

namespace Cube\Tests\Integration;

use Cube\Database\Database;
use Cube\Logger\Logger;
use Cube\Test\CubeTestCase;
use Cube\Utils\Shell;
use Cube\Web\CubeServer;

use function Cube\debug;

class ModelAPITest extends CubeTestCase
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

    public function testCreate()
    {
        $product = $this->post("/auto-api/product", [
            "name" => "Painting",
            "price_dollar" => 120
        ])
        ->assertCreated()
        ->json();

        $this->assertIsArray($product[0]);
        $this->assertEquals('Painting', $product[0]['name']);
        $this->assertEquals(120, $product[0]['price_dollar']);

        $products = $this->postJson("/auto-api/product",[
            [
                "name" => "Table",
                "price_dollar" => 80
            ],
            [
                "name" => "Chair",
                "price_dollar" => 60
            ],
            [
                "name" => "Glass",
                "price_dollar" => 5
            ],
        ])
        ->assertCreated()
        ->json();

        $this->assertCount(3, $products);

        $this->assertEquals('Table', $products[0]['name']);
        $this->assertEquals('Chair', $products[1]['name']);
        $this->assertEquals('Glass', $products[2]['name']);
    }


    public function testRead()
    {
        $products = $this->postJson("/auto-api/product",[
            [
                "name" => "Table",
                "price_dollar" => 80
            ],
            [
                "name" => "Chair",
                "price_dollar" => 60
            ],
            [
                "name" => "Glass",
                "price_dollar" => 5
            ],
            [
                "name" => "Candles",
                "price_dollar" => 5
            ],
        ])
        ->assertCreated()
        ->json();

        $products = $this->get('/auto-api/product')->assertOk()->json();
        $this->assertCount(4, $products);

        $products = $this->get('/auto-api/product', ['name' => "Glass"])->assertOk()->json();
        $this->assertCount(1, $products);

        $products = $this->get('/auto-api/product', ['price_dollar' => 5])->assertOk()->json();
        $this->assertCount(2, $products);

    }




    public function testUpdate()
    {
        $products = $this->postJson("/auto-api/product",[
            [
                "name" => "Table",
                "price_dollar" => 80
            ],
            [
                "name" => "Chair",
                "price_dollar" => 60
            ],
            [
                "name" => "Glass",
                "price_dollar" => 5
            ],
            [
                "name" => "Candles",
                "price_dollar" => 5
            ],
        ])
        ->assertCreated()
        ->json();

        $products = $this->get('/auto-api/product', ["id" => 2])->assertOk()->json();
        $this->assertCount(1, $products);
        $this->assertEquals('Chair', $products[0]['name']);

        $this->put('/auto-api/product', ['id' => 2, 'name' => 'Frog Chair'])->assertOk()->json();

        $products = $this->get('/auto-api/product', ["id" => 2])->assertOk()->json();
        $this->assertCount(1, $products);
        $this->assertEquals('Frog Chair', $products[0]['name']);
    }






    public function testDelete()
    {
        $products = $this->postJson("/auto-api/product",[
            [
                "name" => "Table",
                "price_dollar" => 80
            ],
            [
                "name" => "Chair",
                "price_dollar" => 60
            ],
            [
                "name" => "Glass",
                "price_dollar" => 5
            ],
            [
                "name" => "Candles",
                "price_dollar" => 5
            ],
        ])
        ->assertCreated()
        ->json();

        $this->assertCount(4, $this->get('/auto-api/product')->json());

        $products = $this->delete('/auto-api/product')->assertUnprocessableContent();

        $this->assertCount(4, $this->get('/auto-api/product')->json());

        $this->delete('/auto-api/product', ['id' => 1]);
        $this->assertCount(3, $this->get('/auto-api/product')->json());

        $this->delete('/auto-api/product', ['id' => [2,3]]);
        $this->assertCount(1, $this->get('/auto-api/product')->json());
    }
}
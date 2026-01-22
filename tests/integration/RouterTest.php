<?php

namespace Cube\Tests\Integration;

use Cube\Test\CubeIntegrationTestCase;

/**
 * @internal
 */
class RouterTest extends CubeIntegrationTestCase
{
    public function testPing()
    {
        $this->get('/ping')->assertOk()->assertJsonContent('OK');
        $this->post('/ping')->assertMethodNotAllowed();
    }

    public function testProduct()
    {
        $this->get('/product')->assertMethodNotAllowed();
        $this->post('/product')->assertUnprocessableContent();

        $response = $this->postJson('/product', [
            'name' => 'my-product',
            'price_dollar' => 42,
            'managers' => [['manager' => 'Joe'], ['manager' => 'Randy'], ['manager' => 'Bill']],
        ]);
        $product = $response->json();
        $response->assertOk();

        $this->assertIsNumeric($product['id']);
        $this->assertEquals('my-product', $product['name']);
        $this->assertEquals(42, $product['price_dollar']);
        $this->assertCount(3, $product['managers']);

        $productId = $product['id'];

        $this->get('/product/'.$productId)
            ->assertOk()
            ->assertJsonContent($product)
        ;
    }
}

<?php

namespace Cube\Tests\Units\Web;

use Cube\Data\Database\Database;
use Cube\Tests\Units\Database\Providers\SQLiteProvider;
use Cube\Tests\Units\Database\TestMultipleDrivers;
use Cube\Tests\Units\Models\Product;
use Cube\Tests\Units\Models\User;
use Cube\Tests\Units\Web\Classes\ProductAPI;
use Cube\Web\Http\Request;
use Cube\Web\Http\StatusCode;
use Cube\Web\ModelAPI\ModelAPI;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * ModelAPI only ever talks to the model layer, one driver is enough to pin its behaviour
 * and its status codes : the multi-driver ground is covered by ModelTest.
 *
 * @internal
 */
class ModelAPITest extends TestCase
{
    use TestMultipleDrivers;

    protected Database $database;

    protected function setUp(): void
    {
        $this->database = (new SQLiteProvider())->getEmptyDatabase();
        Database::setInstance($this->database);
    }

    protected function tearDown(): void
    {
        Database::removeInstance();
    }

    public function testReadingAnswersEveryRow()
    {
        Product::insertArray(['name' => 'Screen']);
        Product::insertArray(['name' => 'Mouse']);

        $this->assertCount(2, (new ProductAPI())->readItems(new Request('GET', '/product')));
    }

    public function testReadingSearchesInsideAStringColumn()
    {
        Product::insertArray(['name' => 'Office Screen']);
        Product::insertArray(['name' => 'Mouse']);

        $products = (new ProductAPI())->readItems(new Request('GET', '/product', ['name' => 'Screen']));

        $this->assertCount(1, $products);
        $this->assertEquals('Office Screen', $products[0]->name);
    }

    /**
     * The search used to quote its column with backticks, which Postgres refuses
     */
    #[DataProvider('getDatabases')]
    public function testReadingSearchesOnEveryDriver(Database $database)
    {
        $database->asGlobalInstance(function () {
            Product::insertArray(['name' => 'Office Screen']);
            Product::insertArray(['name' => 'Mouse']);

            $products = (new ProductAPI())->readItems(new Request('GET', '/product', ['name' => 'Screen']));

            $this->assertEquals(['Office Screen'], array_map(fn (Product $product) => $product->name, $products));
        });
    }

    public function testCreatingAnswersCreated()
    {
        $response = (new ProductAPI())->createItems(new Request('POST', '/product', [], ['name' => 'Keyboard']));

        $this->assertEquals(StatusCode::CREATED, $response->getStatusCode());
        $this->assertEquals('Keyboard', Product::findWhere(['name' => 'Keyboard'])->name);
    }

    public function testCreatingSeveralRowsFromAJsonList()
    {
        $request = new Request(
            'POST',
            '/product',
            [],
            [],
            ['content-type' => 'application/json'],
            [],
            json_encode([['name' => 'Desk'], ['name' => 'Chair']])
        );

        $response = (new ProductAPI())->createItems($request);

        $this->assertEquals(StatusCode::CREATED, $response->getStatusCode());
        $this->assertCount(2, $response->getJSON());
        $this->assertCount(2, Product::select()->fetch());
    }

    public function testUpdatingAnswersTheNewRow()
    {
        $product = Product::insertArray(['name' => 'Screen']);

        $updated = (new ProductAPI())->updateItem($product, new Request('PUT', '/product', [], ['name' => 'Bigger screen']));

        $this->assertEquals('Bigger screen', $updated->name);
        $this->assertEquals('Bigger screen', Product::find($product->id())->name);
    }

    public function testUpdatingCannotChangeThePrimaryKey()
    {
        $product = Product::insertArray(['name' => 'Screen']);

        $updated = (new ProductAPI())->updateItem($product, new Request('PUT', '/product', [], ['id' => 404, 'name' => 'Moved']));

        $this->assertEquals($product->id(), $updated->id());
        $this->assertNull(Product::find(404));
    }

    public function testDeleting()
    {
        $product = Product::insertArray(['name' => 'Screen']);

        $response = (new ProductAPI())->deleteItem($product);

        $this->assertEquals(StatusCode::OK, $response->getStatusCode());
        $this->assertNull(Product::find($product->id()));
    }

    public function testAnItemOfAnotherModelIsRefused()
    {
        $this->expectException(InvalidArgumentException::class);

        (new ProductAPI())->deleteItem(new User(['id' => 1]));
    }

    public function testForModelBuildsAnApiWithoutASubclass()
    {
        Product::insertArray(['name' => 'Screen']);

        $api = ModelAPI::forModel(Product::class);

        $this->assertEquals(Product::class, $api->getModelClass());
        $this->assertCount(1, $api->readItems(new Request('GET', '/product')));

        $this->expectExceptionMessage('This ModelAPI handles '.Product::class.' items, got '.User::class);
        $api->deleteItem(new User(['id' => 1]));
    }
}

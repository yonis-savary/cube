<?php

namespace Cube\Tests\Units\Web;

use Cube\Core\Exceptions\ResponseException;
use Cube\Data\Database\Database;
use Cube\Tests\Units\Database\Providers\SQLiteProvider;
use Cube\Tests\Units\Database\TestMultipleDrivers;
use Cube\Tests\Units\Models\Product;
use Cube\Tests\Units\Models\User;
use Cube\Tests\Units\Web\Classes\PricelessProductAPI;
use Cube\Tests\Units\Web\Classes\ProductAPI;
use Cube\Tests\Units\Web\Classes\UserAPI;
use Cube\Tests\Units\Web\Examples\ProductNameRequest;
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

    public function testCreatingOnlyWritesWhatTheRequestValidates()
    {
        (new ProductAPI())->createItems(new ProductNameRequest('POST', '/product', [], ['name' => 'Keyboard', 'price_dollar' => 999]));

        $this->assertNull(Product::findWhere(['name' => 'Keyboard'])->price_dollar);
    }

    public function testUpdatingOnlyWritesWhatTheRequestValidates()
    {
        $product = Product::insertArray(['name' => 'Screen', 'price_dollar' => 10]);

        $updated = (new ProductAPI())->updateItem($product, new ProductNameRequest('PUT', '/product', [], ['name' => 'Renamed', 'price_dollar' => 0]));

        $this->assertEquals('Renamed', $updated->name);
        $this->assertEquals(10, Product::find($product->id())->price_dollar);
    }

    public function testReadingOnlyFiltersOnWhatTheRequestValidates()
    {
        Product::insertArray(['name' => 'Screen', 'price_dollar' => 10]);
        Product::insertArray(['name' => 'Mouse', 'price_dollar' => 20]);

        $products = (new ProductAPI())->readItems(new ProductNameRequest('GET', '/product', ['price_dollar' => 10]));

        $this->assertCount(2, $products);
    }

    /** readItems() used to answer every column, and to search inside any of them : an oracle on password hashes. */
    public function testHiddenFieldsAreNeitherAnsweredNorSearched()
    {
        User::insertArray(['login' => 'alice', 'password' => 'hash-of-alice', 'type' => 1]);
        $api = new UserAPI();

        $users = $api->readItems(new Request('GET', '/user', ['login' => 'alice']));
        $this->assertCount(1, $users);
        $this->assertArrayNotHasKey('password', $users[0]->toArray());

        $everyone = count($api->readItems(new Request('GET', '/user')));
        $this->assertCount($everyone, $api->readItems(new Request('GET', '/user', ['password' => 'no-match'])));

        $updated = $api->updateItem($users[0], new Request('PUT', '/user', [], ['login' => 'alicia']));
        $this->assertArrayNotHasKey('password', $updated->toArray());
        $this->assertEquals('hash-of-alice', User::find($users[0]->id())->password);
    }

    /** Hidden fields were only left out of what was answered : a client could still overwrite a password hash. */
    public function testHiddenFieldsCannotBeWritten()
    {
        $alice = User::insertArray(['login' => 'alice', 'password' => 'hash-of-alice', 'type' => 1]);

        (new UserAPI())->updateItem($alice, new Request('PUT', '/user', [], ['login' => 'alicia', 'password' => 'hash-of-the-attacker']));

        $this->assertEquals('alicia', User::find($alice->id())->login);
        $this->assertEquals('hash-of-alice', User::find($alice->id())->password);

        $created = (new PricelessProductAPI())->createItems(new Request('POST', '/product', [], ['name' => 'Keyboard', 'price_dollar' => 1]))->getJSON();
        $this->assertArrayNotHasKey('price_dollar', $created[0]);
        $this->assertNull(Product::findWhere(['name' => 'Keyboard'])->price_dollar);
    }

    public function testAPlainRequestCannotChooseThePrimaryKeyOfANewRow()
    {
        (new ProductAPI())->createItems(new Request('POST', '/product', [], ['id' => 404, 'name' => 'Keyboard']));

        $this->assertNull(Product::find(404));
        $this->assertNotNull(Product::findWhere(['name' => 'Keyboard']));
    }

    /** A plain Request validates nothing : the model fields are the rule, and an update may send only some of them. */
    public function testAPlainRequestIsValidatedAgainstTheModelFields()
    {
        $product = Product::insertArray(['name' => 'Screen', 'price_dollar' => 10]);

        try {
            (new ProductAPI())->updateItem($product, new Request('PUT', '/product', [], ['created_at' => 'not a date']));
            $this->fail('An invalid field should have been refused');
        } catch (ResponseException $exception) {
            $this->assertEquals(StatusCode::UNPROCESSABLE_CONTENT, $exception->response->getStatusCode());
        }

        $updated = (new ProductAPI())->updateItem($product, new Request('PUT', '/product', [], ['price_dollar' => 20]));
        $this->assertEquals('Screen', $updated->name);
        $this->assertEquals(20, $updated->price_dollar);
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

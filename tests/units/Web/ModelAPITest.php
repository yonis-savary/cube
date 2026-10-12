<?php

namespace Cube\Tests\Units\Web;

use Cube\Data\Database\Database;
use Cube\Data\Models\ModelResource;
use Cube\Tests\Units\Database\Providers\SQLiteProvider;
use Cube\Tests\Units\Database\TestMultipleDrivers;
use Cube\Tests\Units\Models\Product;
use Cube\Tests\Units\Models\User;
use Cube\Tests\Units\Web\Classes\ProductAPI;
use Cube\Tests\Units\Web\Classes\ProductNameResource;
use Cube\Tests\Units\Web\Classes\UserAPI;
use Cube\Tests\Units\Web\Examples\ProductListRequest;
use Cube\Tests\Units\Web\Examples\ProductNameRequest;
use Cube\Tests\Units\Web\Examples\ProductRequest;
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

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return string[]
     */
    protected function namesOf(array $rows): array
    {
        return array_column($rows, 'name');
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

        $products = (new ProductAPI())->readItems(new ProductNameRequest('GET', '/product', ['name' => 'Screen']));

        $this->assertEquals(['Office Screen'], $this->namesOf($products));
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

            $products = (new ProductAPI())->readItems(new ProductNameRequest('GET', '/product', ['name' => 'Screen']));

            $this->assertEquals(['Office Screen'], $this->namesOf($products));
        });
    }

    /** `%` and `_` reached the LIKE pattern as wildcards : `?name=%` matched every row. */
    #[DataProvider('getDatabases')]
    public function testSearchWildcardsAreSearchedLiterally(Database $database)
    {
        $database->asGlobalInstance(function () {
            Product::insertArray(['name' => '100% cotton']);
            Product::insertArray(['name' => '100 units']);
            Product::insertArray(['name' => 'snake_case']);
            Product::insertArray(['name' => 'back\\slash']);

            $names = fn (string $search) => $this->namesOf(
                (new ProductAPI())->readItems(new ProductNameRequest('GET', '/product', ['name' => $search]))
            );

            $this->assertEquals(['100% cotton'], $names('100%'));
            $this->assertEquals(['snake_case'], $names('e_c'));
            $this->assertEquals(['back\\slash'], $names('k\\s'));
        });
    }

    /** Every space-separated word added a LIKE clause : a long value built an unbounded query. */
    public function testASearchKeepsItsFirstWordsOnly()
    {
        Product::insertArray(['name' => 'a b c d e f g h']);

        $search = 'a b c d e f g h '.str_repeat('missing ', 50);
        $products = (new ProductAPI())->readItems(new ProductNameRequest('GET', '/product', ['name' => $search]));

        $this->assertCount(1, $products);
    }

    /** A plain Request declares no rule : it used to filter on any column, a password hash included. */
    public function testAPlainRequestFiltersOnNothing()
    {
        User::insertArray(['login' => 'alice', 'password' => 'hash-of-alice', 'type' => 1]);
        $everyone = count((new UserAPI())->readItems(new Request('GET', '/user')));

        $this->assertCount($everyone, (new UserAPI())->readItems(new Request('GET', '/user', ['password' => 'no-match'])));
    }

    public function testCreatingAnswersCreated()
    {
        $response = (new ProductAPI())->createItems(new ProductNameRequest('POST', '/product', [], ['name' => 'Keyboard']));

        $this->assertEquals(StatusCode::CREATED, $response->getStatusCode());
        $this->assertEquals('Keyboard', Product::findWhere(['name' => 'Keyboard'])->name);
    }

    public function testCreatingSeveralRowsFromAJsonList()
    {
        $request = new ProductListRequest(
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

        $updated = (new ProductAPI())->updateItem($product, new ProductRequest('PUT', '/product', [], ['name' => 'Bigger screen']));

        $this->assertEquals('Bigger screen', $updated['name']);
        $this->assertEquals('Bigger screen', Product::find($product->id())->name);
    }

    public function testUpdatingCannotChangeThePrimaryKey()
    {
        $product = Product::insertArray(['name' => 'Screen']);

        $updated = (new ProductAPI())->updateItem($product, new ProductRequest('PUT', '/product', [], ['id' => 404, 'name' => 'Moved']));

        $this->assertEquals($product->id(), $updated['id']);
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

        $this->assertEquals('Renamed', $updated['name']);
        $this->assertEquals(10, Product::find($product->id())->price_dollar);
    }

    public function testReadingOnlyFiltersOnWhatTheRequestValidates()
    {
        Product::insertArray(['name' => 'Screen', 'price_dollar' => 10]);
        Product::insertArray(['name' => 'Mouse', 'price_dollar' => 20]);

        $products = (new ProductAPI())->readItems(new ProductNameRequest('GET', '/product', ['price_dollar' => 10]));

        $this->assertCount(2, $products);
    }

    /** Writing what a plain Request carries let a client set any column : `PUT /user/5 {"password": ...}` took the account over. */
    public function testWritingNeedsARequestDeclaringItsFields()
    {
        $alice = User::insertArray(['login' => 'alice', 'password' => 'hash-of-alice', 'type' => 1]);

        foreach ([
            fn () => (new UserAPI())->updateItem($alice, new Request('PUT', '/user', [], ['password' => 'hash-of-the-attacker'])),
            fn () => (new UserAPI())->createItems(new Request('POST', '/user', [], ['login' => 'eve', 'password' => 'x', 'type' => 1])),
        ] as $write) {
            try {
                $write();
                $this->fail('A plain Request should not be written');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('needs a Request subclass', $exception->getMessage());
            }
        }

        $this->assertEquals('hash-of-alice', User::find($alice->id())->password);
    }

    /** The default resource of a model decides what every method answers : the password hash never leaves. */
    public function testAnswersGoThroughTheModelResource()
    {
        User::insertArray(['login' => 'alice', 'password' => 'hash-of-alice', 'type' => 1]);

        $users = (new UserAPI())->readItems(new Request('GET', '/user'));

        $this->assertArrayHasKey('login', $users[0]);
        $this->assertArrayNotHasKey('password', $users[0]);
    }

    public function testAnApiCanAnswerWithAnotherResource()
    {
        Product::insertArray(['name' => 'Screen', 'price_dollar' => 10]);

        $api = new class extends ProductAPI {
            protected function getModelResource(): ?ModelResource
            {
                return new ProductNameResource();
            }
        };

        $this->assertEquals([['name' => 'Screen']], $api->readItems(new Request('GET', '/product')));
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

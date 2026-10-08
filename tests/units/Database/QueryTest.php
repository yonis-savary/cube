<?php

namespace Cube\Tests\Units\Database;

use Cube\Data\Bunch;
use Cube\Data\Database\Builders\MySQL;
use Cube\Data\Database\Database;
use Cube\Data\Database\Query;
use Cube\Data\Database\Query\FieldComparaison;
use Cube\Tests\Units\Models\Product;
use Cube\Tests\Units\Models\ProductManager;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class QueryTest extends TestCase
{
    use TestMultipleDrivers;

    protected function insertProducts(int $count): void
    {
        for ($i = 1; $i <= $count; ++$i)
            Product::insertArray(['name' => "product-{$i}"]);
    }

    /**
     * @return array<array<string>>
     */
    protected function chunkNames(int $chunkSize, ?Database $database = null): array
    {
        $chunks = [];
        Product::select()
            ->order('id', 'ASC')
            ->chunk($chunkSize, function (array $products) use (&$chunks) {
                $chunks[] = array_map(fn (Product $product) => $product->name, $products);
            }, $database);

        return $chunks;
    }

    #[DataProvider('getDatabases')]
    public function testChunk(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(7);

            $this->assertEquals([
                ['product-1', 'product-2', 'product-3'],
                ['product-4', 'product-5', 'product-6'],
                ['product-7'],
            ], $this->chunkNames(3));
        });
    }

    #[DataProvider('getDatabases')]
    public function testChunkWithExactMultiple(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(4);

            $this->assertEquals([
                ['product-1', 'product-2'],
                ['product-3', 'product-4'],
            ], $this->chunkNames(2));
        });
    }

    #[DataProvider('getDatabases')]
    public function testChunkLargerThanTheResult(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(2);

            $this->assertEquals([['product-1', 'product-2']], $this->chunkNames(10));
        });
    }

    #[DataProvider('getDatabases')]
    public function testChunkOnEmptyResultNeverCallsBack(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->assertEquals([], $this->chunkNames(3));
        });
    }

    #[DataProvider('getDatabases')]
    public function testChunkRespectsConditions(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(5);

            $chunks = [];
            Product::select()
                ->where('name', 'product-3', '<>')
                ->order('id', 'ASC')
                ->chunk(2, function (array $products) use (&$chunks) {
                    $chunks[] = array_map(fn (Product $product) => $product->name, $products);
                });

            $this->assertEquals([
                ['product-1', 'product-2'],
                ['product-4', 'product-5'],
            ], $chunks);
        });
    }

    #[DataProvider('getDatabases')]
    public function testChunkIgnoresAPreviousLimit(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(3);

            $chunks = [];
            Product::select()
                ->order('id', 'ASC')
                ->limit(1)
                ->chunk(2, function (array $products) use (&$chunks) {
                    $chunks[] = count($products);
                });

            $this->assertEquals([2, 1], $chunks);
        });
    }

    /**
     * The database given to chunk() must be the one read, not the global instance.
     */
    #[DataProvider('getDatabases')]
    public function testChunkUsesTheGivenDatabase(Database $database)
    {
        $database->asGlobalInstance(fn () => $this->insertProducts(3));

        $this->assertEquals([
            ['product-1', 'product-2'],
            ['product-3'],
        ], $this->chunkNames(2, $database));
    }

    #[DataProvider('getDatabases')]
    public function testChunkAlsoGivesTheChunkAsABunch(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(3);

            $chunks = [];
            Product::select()
                ->order('id', 'ASC')
                ->chunk(2, function (array $products, Bunch $bunch) use (&$chunks) {
                    $this->assertEquals($products, $bunch->get());
                    $chunks[] = $bunch->map(fn (Product $product) => $product->name)->get();
                });

            $this->assertEquals([
                ['product-1', 'product-2'],
                ['product-3'],
            ], $chunks);
        });
    }

    protected function insertManagedProducts(): void
    {
        $this->insertProducts(4);

        ProductManager::insertArray(['product' => 1, 'manager' => 'alice']);
        ProductManager::insertArray(['product' => 3, 'manager' => 'alice']);
        ProductManager::insertArray(['product' => 4, 'manager' => 'bob']);
    }

    /**
     * @param Query<Product> $query
     * @return string[]
     */
    protected function fetchNames(Query $query): array
    {
        return Bunch::of($query->order('id', 'ASC')->fetch())
            ->map(fn (Product $product) => $product->name)
            ->get();
    }

    protected function productsManagedBy(string $manager): Query
    {
        return Query::select('product_manager')
            ->selectField('product', 'product_manager')
            ->where('manager', $manager, table: 'product_manager');
    }

    #[DataProvider('getDatabases')]
    public function testWhereInSubQuery(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertManagedProducts();

            $this->assertEquals(
                ['product-1', 'product-3'],
                $this->fetchNames(Product::select()->where('id', $this->productsManagedBy('alice'), 'IN'))
            );
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereNotInSubQuery(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertManagedProducts();

            $this->assertEquals(
                ['product-2', 'product-4'],
                $this->fetchNames(Product::select()->where('id', $this->productsManagedBy('alice'), 'NOT IN'))
            );
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereEqualsSingleRowSubQuery(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertManagedProducts();

            $this->assertEquals(
                ['product-4'],
                $this->fetchNames(Product::select()->where('id', $this->productsManagedBy('bob')))
            );
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereSubQueryWithoutMatchReturnsNothing(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertManagedProducts();

            $this->assertEquals(
                [],
                $this->fetchNames(Product::select()->where('id', $this->productsManagedBy('nobody'), 'IN'))
            );
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereSubQueryCombinesWithOtherConditions(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertManagedProducts();

            $this->assertEquals(
                ['product-3'],
                $this->fetchNames(
                    Product::select()
                        ->where('id', $this->productsManagedBy('alice'), 'IN')
                        ->where('name', 'product-1', '<>')
                )
            );
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereSubQueryEscapesItsOwnValues(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertManagedProducts();
            ProductManager::insertArray(['product' => 2, 'manager' => "o'hara"]);

            $this->assertEquals(
                ['product-2'],
                $this->fetchNames(Product::select()->where('id', $this->productsManagedBy("o'hara"), 'IN'))
            );
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereNestedSubQueries(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertManagedProducts();

            $aliceProductsNamedOne = Query::select('product')
                ->selectField('id', 'product')
                ->where('id', $this->productsManagedBy('alice'), 'IN', 'product')
                ->where('name', 'product-1', table: 'product');

            $this->assertEquals(
                ['product-1'],
                $this->fetchNames(Product::select()->where('id', $aliceProductsNamedOne, 'IN'))
            );
        });
    }

    #[DataProvider('getDatabases')]
    public function testDeleteWhereInSubQuery(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertManagedProducts();

            Query::delete('product_manager')
                ->where('product', Query::select('product')->selectField('id', 'product')->where('name', 'product-4', table: 'product'), 'IN', 'product_manager')
                ->fetch();

            $this->assertEquals(2, ProductManager::select()->count());
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereInArray(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(4);

            $this->assertEquals(['product-1', 'product-3'], $this->fetchNames(Product::select()->whereIn('id', [1, 3])));
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereNotInArray(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(4);

            $this->assertEquals(['product-2', 'product-4'], $this->fetchNames(Product::select()->whereNotIn('id', [1, 3])));
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereInBunch(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(4);

            $this->assertEquals(['product-2', 'product-4'], $this->fetchNames(Product::select()->whereIn('name', Bunch::of(['product-2', 'product-4']))));
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereNotInBunch(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(4);

            $this->assertEquals(['product-1', 'product-3'], $this->fetchNames(Product::select()->whereNotIn('name', Bunch::of(['product-2', 'product-4']))));
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereInEmptyArrayMatchesNothing(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(3);

            $this->assertEquals([], $this->fetchNames(Product::select()->whereIn('id', [])));
            $this->assertEquals([], $this->fetchNames(Product::select()->whereIn('id', Bunch::of([]))));
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereNotInEmptyArrayMatchesEverything(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(3);

            $this->assertEquals(['product-1', 'product-2', 'product-3'], $this->fetchNames(Product::select()->whereNotIn('id', [])));
            $this->assertEquals(['product-1', 'product-2', 'product-3'], $this->fetchNames(Product::select()->whereNotIn('id', Bunch::of([]))));
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereInSubQueryHelper(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertManagedProducts();

            $this->assertEquals(['product-1', 'product-3'], $this->fetchNames(Product::select()->whereIn('id', $this->productsManagedBy('alice'))));
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereNotInSubQueryHelper(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertManagedProducts();

            $this->assertEquals(['product-2', 'product-4'], $this->fetchNames(Product::select()->whereNotIn('id', $this->productsManagedBy('alice'))));
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereInOnAnExplicitTable(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertManagedProducts();

            $managers = ProductManager::select()
                ->whereIn('product', [3, 4], 'product_manager')
                ->order('product', 'ASC', 'product_manager')
                ->fetch();

            $this->assertEquals(['alice', 'bob'], Bunch::of($managers)->map(fn (ProductManager $row) => $row->manager)->get());
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereInCombinesWithWhereNotIn(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(5);

            $this->assertEquals(
                ['product-2', 'product-4'],
                $this->fetchNames(Product::select()->whereIn('id', [1, 2, 4])->whereNotIn('name', ['product-1']))
            );
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereInOnAQueryWithoutModel(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertManagedProducts();

            $rows = Query::select('product_manager')
                ->selectField('manager', 'product_manager')
                ->whereIn('product', [3, 4], 'product_manager')
                ->order('product', 'ASC', 'product_manager')
                ->fetch();

            $this->assertEquals(['alice', 'bob'], Bunch::of($rows)->map(fn ($row) => $row->manager)->get());
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereGroupGivesTheQueryToItsCallback(Database $database)
    {
        $database->asGlobalInstance(function () {
            $query = Product::select();
            $query->whereGroup(fn (Query $group) => $this->assertSame($query, $group));
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereGroupKeepsItsOrInsideParentheses(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(4);

            $this->assertEquals(
                ['product-4'],
                $this->fetchNames(
                    Product::select()
                        ->where('id', 3, '>')
                        ->whereGroup(fn (Query $query) => $query->where('name', 'product-4')->or()->where('name', 'product-2'))
                )
            );
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereGroupAsFirstCondition(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(4);

            $this->assertEquals(
                ['product-3'],
                $this->fetchNames(
                    Product::select()
                        ->whereGroup(fn (Query $query) => $query->where('name', 'product-1')->or()->where('name', 'product-3'))
                        ->where('id', 1, '>')
                )
            );
        });
    }

    #[DataProvider('getDatabases')]
    public function testConditionsAfterWhereGroupAreOutsideOfIt(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(4);

            $this->assertEquals(
                ['product-2'],
                $this->fetchNames(
                    Product::select()
                        ->whereGroup(fn (Query $query) => $query->where('name', 'product-1')->or()->where('name', 'product-2'))
                        ->where('id', 2)
                )
            );
        });
    }

    #[DataProvider('getDatabases')]
    public function testTwoWhereGroups(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(4);

            $this->assertEquals(
                ['product-2'],
                $this->fetchNames(
                    Product::select()
                        ->whereGroup(fn (Query $query) => $query->where('id', 1)->or()->where('id', 2))
                        ->whereGroup(fn (Query $query) => $query->where('id', 2)->or()->where('id', 3))
                )
            );
        });
    }

    #[DataProvider('getDatabases')]
    public function testNestedWhereGroups(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(5);

            $this->assertEquals(
                ['product-1', 'product-4'],
                $this->fetchNames(
                    Product::select()
                        ->whereGroup(fn (Query $query) => $query
                            ->where('name', 'product-1')
                            ->or()
                            ->whereGroup(fn (Query $query) => $query
                                ->where('id', 2, '>')
                                ->whereGroup(fn (Query $query) => $query->where('name', 'product-5')->or()->where('name', 'product-4'))
                                ->where('id', 5, '<')
                            )
                        )
                )
            );
        });
    }

    #[DataProvider('getDatabases')]
    public function testEmptyWhereGroupIsIgnored(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(3);

            $this->assertEquals(['product-1', 'product-2', 'product-3'], $this->fetchNames(Product::select()->whereGroup(fn () => null)));
            $this->assertEquals(
                ['product-2'],
                $this->fetchNames(Product::select()->whereGroup(fn () => null)->where('id', 2)->whereGroup(fn () => null))
            );
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereGroupAcceptsEveryKindOfCondition(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertManagedProducts();

            $this->assertEquals(
                ['product-2'],
                $this->fetchNames(
                    Product::select()
                        ->where('id', 3, '<')
                        ->whereGroup(fn (Query $query) => $query
                            ->whereIn('name', ['product-2'])
                            ->or()
                            ->whereRaw('1=0')
                            ->or()
                            ->whereIn('id', $this->productsManagedBy('alice'))
                            ->where('name', 'product-1', '<>')
                        )
                )
            );
        });
    }

    #[DataProvider('getDatabases')]
    public function testWhereGroupIsRespectedByCount(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(4);

            $this->assertEquals(
                1,
                Product::select()
                    ->where('id', 3, '>')
                    ->whereGroup(fn (Query $query) => $query->where('name', 'product-4')->or()->where('name', 'product-2'))
                    ->count()
            );
        });
    }

    #[DataProvider('getDatabases')]
    public function testExists(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(3);

            $this->assertTrue(Product::select()->where('name', 'product-2')->exists());
            $this->assertTrue(Product::select()->where('id', 1, '>=')->exists());
            $this->assertFalse(Product::select()->where('name', 'unknown')->exists());
        });
    }

    #[DataProvider('getDatabases')]
    public function testExistsLeavesTheQueryUntouched(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(3);

            $query = Product::select();
            $query->exists();

            $this->assertCount(3, $query->fetch());
        });
    }

    #[DataProvider('getDatabases')]
    public function testDeleteWithWhereGroup(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(4);

            Query::delete('product')
                ->where('id', 3, '>')
                ->whereGroup(fn (Query $query) => $query->where('name', 'product-4')->or()->where('name', 'product-2'))
                ->fetch();

            $this->assertEquals(['product-1', 'product-2', 'product-3'], $this->fetchNames(Product::select()));
        });
    }

    /**
     * fetch() splits every alias on a dot to find its table, an alias written by hand has none
     */
    #[DataProvider('getDatabases')]
    public function testFetchAnAliasedExpression(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(3);

            $row = Query::select('product')->selectExpression('COUNT(*)', 'total')->fetch()[0];

            $this->assertEquals(3, $row->total);
        });
    }

    #[DataProvider('getDatabases')]
    public function testFetchAnAliasedField(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(1);

            $row = Query::select('product')->selectField('name', alias: 'label')->fetch()[0];

            $this->assertEquals('product-1', $row->label);
        });
    }

    /**
     * MySQL and SQLite both refuse an OFFSET that does not follow a LIMIT
     */
    #[DataProvider('getDatabases')]
    public function testOffsetWithoutLimit(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(3);

            $this->assertEquals(
                ['product-2', 'product-3'],
                $this->fetchNames(Product::select()->limit(null, 1))
            );
        });
    }

    /**
     * Without any where(), the join conditions used to be written without their WHERE keyword
     */
    public function testUpdateWithAJoinAndNoConditionKeepsItsWhereKeyword()
    {
        $database = new Database('sqlite', queryBuilder: new MySQL());

        $sql = Query::update('product')
            ->join('LEFT', 'product_manager', null, new FieldComparaison('product', 'id', '=', 'product_manager', 'product'))
            ->set('name', 'renamed', 'product')
            ->build($database);

        $this->assertMatchesRegularExpression('/WHERE\s+\(`product`\.id = `product_manager`\.product\)/', $sql);
    }

    #[DataProvider('getDatabases')]
    public function testWhereAssocJoinsEveryConditionWithAnd(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(4);

            $this->assertEquals(['product-2'], $this->fetchNames(Product::select()->whereAssoc(['id' => [1, 2, 3], 'name' => 'product-2'])));
            $this->assertEquals([], $this->fetchNames(Product::select()->whereAssoc(['id' => 1, 'name' => 'product-2'])));
        });
    }

    #[DataProvider('getDatabases')]
    public function testSetAssocUpdatesEveryGivenField(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertProducts(2);

            Product::update()->where('id', 1)->setAssoc(['name' => 'renamed', 'price_dollar' => 5])->fetch();

            $renamed = Product::find(1);
            $this->assertEquals('renamed', $renamed->name);
            $this->assertEquals(5, $renamed->price_dollar);
            $this->assertEquals('product-2', Product::find(2)->name);
        });
    }

    public function testAssocMethodsRefuseAList()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('whereAssoc() needs an associative array');

        Product::select()->whereAssoc(['product-1']);
    }
}

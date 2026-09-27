<?php

namespace Cube\Tests\Units\Database;

use Cube\Data\Bunch;
use Cube\Data\Database\Database;
use Cube\Tests\Units\Models\Product;
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
}

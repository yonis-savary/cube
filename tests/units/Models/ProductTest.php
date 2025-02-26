<?php

namespace Cube\Tests\Units\Models;

use Cube\Data\Bunch;
use Cube\Database\Database;
use Cube\Database\Query;
use Cube\Http\Request;
use Cube\Http\Rules\Validator;
use Cube\Tests\Units\Database\TestMultipleDrivers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ProductTest extends TestCase
{
    use TestMultipleDrivers;

    public function testId()
    {
        $product = new Product();
        $this->assertFalse($product->id());

        $product->id = 52;
        $this->assertEquals(52, $product->id());
    }

    public function testHasField()
    {
        $this->assertTrue(Product::hasField('id'));
        $this->assertTrue(Product::hasField('name'));

        // Inexistant
        $this->assertFalse(Product::hasField('release_date'));
    }

    #[DataProvider('getDatabases')]
    public function testInsert(Database $database)
    {
        $database->asGlobalInstance(function () {
            $product = Product::insertArray(['name' => 'Keyboard']);

            $this->assertInstanceOf(Product::class, $product);
            $this->assertNotFalse($product->id());

            $this->assertEquals($product->data, Product::find($product->id())->data);
        });
    }

    #[DataProvider('getDatabases')]
    public function testSelect(Database $database)
    {
        $database->asGlobalInstance(function () {
            Product::insert()->insertField(['name'])->values(['keyboard'], ['mouse'], ['speakers'])->fetch();

            $this->assertInstanceOf(Query::class, Product::select());
            $this->assertCount(3, Product::select()->fetch());
        });
    }

    #[DataProvider('getDatabases')]
    public function testUpdate(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->assertInstanceOf(Query::class, Product::update());

            $productId = Product::insertArray(['name' => 'keyboard'])->id();

            Product::update()->where('id', $productId)->set('name', 'mouse')->fetch();

            $product = Product::find($productId);
            $this->assertEquals('mouse', $product->name);
        });
    }

    #[DataProvider('getDatabases')]
    public static function updateRow(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->assertInstanceOf(Query::class, Product::update());

            $productId = Product::insertArray(['name' => 'keyboard'])->id();

            Product::updateRow($productId, ['name' => 'mouse']);

            $product = Product::find($productId);
            $this->assertEquals('mouse', $product->name);
        });
    }

    #[DataProvider('getDatabases')]
    public function testInsertArray(Database $database)
    {
        $database->asGlobalInstance(function () {
            $product = Product::insertArray(['name' => 'mouse']);
            $this->assertIsNumeric($product->id());
            $this->assertEquals('mouse', $product->name);
        });
    }

    #[DataProvider('getDatabases')]
    public function testExistsWhere(Database $database)
    {
        $database->asGlobalInstance(function () {
            Product::insert()->insertField(['name'])->values(['keyboard'], ['mouse'], ['speakers'])->fetch();

            $this->assertTrue(Product::existsWhere(['name' => 'keyboard']));
            $this->assertFalse(Product::existsWhere(['name' => 'screen']));
        });
    }

    #[DataProvider('getDatabases')]
    public function testExists(Database $database)
    {
        $database->asGlobalInstance(function () {
            $productId = Product::insertArray(['name' => 'mouse'])->id();

            $this->assertTrue(Product::exists($productId));
            $this->assertFalse(Product::exists(999));
        });
    }

    #[DataProvider('getDatabases')]
    public function testFindWhere(Database $database)
    {
        $database->asGlobalInstance(function () {
            Product::insertArray(['name' => 'mouse']);

            $this->assertInstanceOf(Product::class, Product::findWhere(['name' => 'mouse']));
            $this->assertNull(Product::findWhere(['name' => 'gamepad']));
        });
    }

    #[DataProvider('getDatabases')]
    public function testToValidator(Database $database)
    {
        $database->asGlobalInstance(function () {
            $validator = Product::toValidator();

            $this->assertInstanceOf(Validator::class, $validator);

            $this->assertTrue($validator->validateArray(['name' => 'mousepad']));
            $this->assertNotTrue($validator->validateArray([]));
        });
    }

    #[DataProvider('getDatabases')]
    public function testFind(Database $database)
    {
        $database->asGlobalInstance(function () {
            $productId = Product::insertArray(['name' => 'usb-cable'])->id();

            $this->assertInstanceOf(Product::class, Product::find($productId));
            $this->assertNull(Product::find(999));
        });
    }

    #[DataProvider('getDatabases')]
    public function testDelete(Database $database)
    {
        $database->asGlobalInstance(function () {
            Product::insert()->insertField(['name'])->values(['keyboard'], ['mouse'], ['speakers'])->fetch();

            $this->assertInstanceOf(Query::class, Product::delete());

            Product::delete()->where('name', 'mouse')->fetch();

            $this->assertCount(2, Product::select()->fetch());
        });
    }

    #[DataProvider('getDatabases')]
    public function testDeleteId(Database $database)
    {
        $database->asGlobalInstance(function () {
            Product::insertArray(['name' => 'power supply'])->id();
            $productId = Product::insertArray(['name' => 'usb-cable'])->id();

            $this->assertEquals(2, Product::select()->count());

            Product::deleteId($productId);
            $this->assertEquals(1, Product::select()->count());
        });
    }

    #[DataProvider('getDatabases')]
    public function testDeleteWhere(Database $database)
    {
        $database->asGlobalInstance(function () {
            Product::insertArray(['name' => 'power supply'])->id();
            Product::insertArray(['name' => 'usb-cable'])->id();

            $this->assertEquals(2, Product::select()->count());

            Product::deleteWhere(['name' => 'usb-cable']);
            $this->assertEquals(1, Product::select()->count());
        });
    }

    #[DataProvider('getDatabases')]
    public function testFromArray(Database $database)
    {
        $database->asGlobalInstance(function () {
            $product = Product::fromArray([
                'name' => 'usb-cable',
                'managers' => [
                    ['manager' => 'Luigi'],
                    ['manager' => 'Mario'],
                ],
            ]);

            $this->assertInstanceOf(Product::class, $product);
            $this->assertEquals('usb-cable', $product->name);
            $this->assertEquals(['Luigi', 'Mario'], Bunch::of($product->managers)->key('manager')->toArray());
        });
    }

    #[DataProvider('getDatabases')]
    public function testFromRequest(Database $database)
    {
        $database->asGlobalInstance(function () {
            $request = new Request(
                'GET',
                '/',
                body: json_encode(
                    [
                        'name' => 'usb-cable',
                        'managers' => [
                            ['manager' => 'Luigi'],
                            ['manager' => 'Mario'],
                        ],
                    ]
                ),
                headers: ['content-type' => 'application/json']
            );

            $product = Product::fromRequest($request);

            $this->assertInstanceOf(Product::class, $product);
            $this->assertEquals('usb-cable', $product->name);
            $this->assertEquals(['Luigi', 'Mario'], Bunch::of($product->managers)->key('manager')->toArray());
        });
    }

    #[DataProvider('getDatabases')]
    public function testOnSaved(Database $database)
    {
        $database->asGlobalInstance(function () {
            $product = Product::fromArray(['name' => 'mouse']);

            $saved = false;
            $product->onSaved(function () use (&$saved) { $saved = true; });

            $product->save();
            $this->assertTrue($saved);

            $product->name = 'gamepad';
            $saved = false;
            $product->save();
            $this->assertTrue($saved);
        });
    }

    #[DataProvider('getDatabases')]
    public function testReplicate(Database $database)
    {
        $database->asGlobalInstance(function () {
            $product = new Product([
                'name' => 'Laptop',
                'managers' => [['manager' => 'Dale']],
            ]);

            $product->save();

            $this->assertIsNumeric($product->id());
            $this->assertIsNumeric($product->managers[0]->product);

            $clone = $product->replicate();
            $clone->name = 'PC Case';
            $clone->save();

            $this->assertIsNumeric($clone->id());
            $this->assertIsNumeric($clone->managers[0]->product);

            $this->assertNotEquals($product->id(), $clone->id());
            $this->assertNotEquals($product->managers[0]->product, $clone->managers[0]->product);
        });
    }
}

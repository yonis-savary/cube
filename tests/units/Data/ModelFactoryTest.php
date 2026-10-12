<?php

namespace Cube\Tests\Units\Data;

use Cube\Data\Database\Database;
use Cube\Data\Models\GenericModelFactory;
use Cube\Tests\Units\Data\Resources\Gadget;
use Cube\Tests\Units\Data\Resources\ProductFactory;
use Cube\Tests\Units\Database\TestMultipleDrivers;
use Cube\Tests\Units\Models\Module;
use Cube\Tests\Units\Models\Product;
use Cube\Tests\Units\Models\User;
use Cube\Tests\Units\Models\UserType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ModelFactoryTest extends TestCase
{
    use TestMultipleDrivers;

    public function testAModelWithoutFactoryGetsTheGenericOne()
    {
        $this->assertInstanceOf(GenericModelFactory::class, Module::factory());
        $this->assertInstanceOf(ProductFactory::class, Product::factory());
    }

    public function testEveryFieldIsFakedFromItsTypeAndSize()
    {
        $gadget = (new GenericModelFactory(Gadget::class))->make();

        $this->assertIsString($gadget->name);
        $this->assertNotEmpty($gadget->name);
        $this->assertLessThanOrEqual(4, mb_strlen($gadget->code));
    }

    public function testAFactoryOnlyGivesTheFieldsAskingForAMeaningfulValue()
    {
        $product = Product::factory()->make();

        $this->assertEquals('9.99000', $product->price_dollar);
        $this->assertNotEmpty($product->name);
        $this->assertIsInt($product->id, 'An example needs a key, even an auto-incremented one');
    }

    public function testOverridesWin()
    {
        $this->assertEquals('Screen', Product::factory()->make(['name' => 'Screen'])->name);
        $this->assertCount(3, Product::factory()->makeMany(3));
    }

    /** A referenced row is created first, so a saved model holds a key its foreign key constraint accepts. */
    #[DataProvider('getDatabases')]
    public function testCreatingSavesTheModelAndWhatItReferences(Database $database)
    {
        $user = User::factory()->create(database: $database);
        $this->assertEquals($user->id(), User::select()->order('id')->first($database)->id(), 'The key of a saved model is left to the database');

        $this->assertNotNull(User::find($user->id(), database: $database));
        $this->assertNotNull(UserType::find($user->type, database: $database));
        $this->assertCount(2, User::factory()->createMany(2, ['type' => $user->type], $database));
    }
}

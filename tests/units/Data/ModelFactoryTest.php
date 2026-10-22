<?php

namespace Cube\Tests\Units\Data;

use Cube\Data\Database\Database;
use Cube\Data\Models\GenericModelFactory;
use Cube\Tests\Units\Data\Resources\Gadget;
use Cube\Tests\Units\Data\Resources\ProductFactory;
use Cube\Tests\Units\Database\TestMultipleDrivers;
use Cube\Tests\Units\Models\Module;
use Cube\Tests\Units\Models\ModuleUser;
use Cube\Tests\Units\Models\Product;
use Cube\Tests\Units\Models\User;
use Cube\Tests\Units\Models\UserType;
use InvalidArgumentException;
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

    public function testARelationTheModelReferencesIsMadeFirstAndItsKeyCopied()
    {
        $user = User::factory()->with('_type')->make();

        $this->assertInstanceOf(UserType::class, $user->_type);
        $this->assertEquals($user->_type->id, $user->type);
    }

    public function testARelationReferencingTheModelGetsItsKey()
    {
        $userType = UserType::factory()->with('users')->make();

        $this->assertCount(1, $userType->users);
        $this->assertEquals($userType->id, $userType->users[0]->type);
    }

    public function testNestedRelationsAreMadeThroughTheirOwnFactory()
    {
        $user = User::factory()->with('modules._module')->make();

        $moduleUser = $user->modules[0];
        $this->assertInstanceOf(ModuleUser::class, $moduleUser);
        $this->assertEquals($user->id, $moduleUser->user);
        $this->assertInstanceOf(Module::class, $moduleUser->_module);
        $this->assertEquals($moduleUser->_module->id, $moduleUser->module);
    }

    /** `Model::factory()` is shared : configuring relations on it would give them to every following call. */
    public function testRelationsOnlyApplyToTheConfiguredCopy()
    {
        User::factory()->with('_type')->make();

        $this->assertEmpty(User::factory()->make()->references);
    }

    public function testASeededFactoryPassesADerivedSeedToItsRelations()
    {
        $first = User::factory()->withSeed(42)->with('modules._module')->make()->toArray();
        $second = User::factory()->withSeed(42)->with('modules._module')->make()->toArray();

        $this->assertEquals($first, $second);
    }

    /** Each model reseeded its relation, so every related model got the same values. */
    public function testRelatedModelsOfManyModelsDiffer()
    {
        $userTypes = UserType::factory()->withSeed(42)->with('users')->makeMany(2);

        $this->assertNotEquals($userTypes[0]->users[0]->login, $userTypes[1]->users[0]->login);
    }

    public function testAnUnknownRelationThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        User::factory()->with('unknown')->make();
    }

    /** The referenced row comes from the relation, not from a second row created for the foreign key. */
    #[DataProvider('getDatabases')]
    public function testCreatingSavesTheRelations(Database $database)
    {
        $userTypeCount = count(UserType::select()->fetch($database));
        $user = User::factory()->with('_type')->create(database: $database);

        $this->assertCount($userTypeCount + 1, UserType::select()->fetch($database));
        $this->assertEquals($user->_type->id, UserType::find($user->type, database: $database)->id);

        $userType = UserType::factory()->with('users')->create(database: $database);
        $this->assertEquals($userType->users[0]->id, User::findWhere(['type' => $userType->id], database: $database)?->id);
    }
}

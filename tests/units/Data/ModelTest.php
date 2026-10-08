<?php 

namespace Cube\Tests\Units\Data;

use Cube\Data\Database\Database;
use Cube\Data\Models\Model;
use Cube\Data\Models\ModelField;
use Cube\Data\Models\Exceptions\MissingModelPrimaryKeyException;
use Cube\Tests\Units\Database\TestMultipleDrivers;
use Cube\Tests\Units\Models\Module;
use Cube\Tests\Units\Models\ModuleUser;
use Cube\Tests\Units\Models\Product;
use Cube\Tests\Units\Models\ProductManager;
use Cube\Tests\Units\Models\User;
use Cube\Web\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// #[ DataProvider('getDatabases') ] public function testInsertArray(Database $database) {}
// #[ DataProvider('getDatabases') ] public function testFindWhere(Database $database) {}

class ModelTest extends TestCase
{
    use TestMultipleDrivers;

    /**
     * markAsOriginal(true) used to walk the references as if each one held a single model,
     * where a hasMany holds an array : eager loading one crashed find() and findWhere().
     */
    #[ DataProvider('getDatabases') ]
    public function testEagerLoadingAHasManyRelation(Database $database)
    {
        $database->asGlobalInstance(function() {
            $product = Product::insertArray(['name' => 'screen']);
            ProductManager::insertArray(['product' => $product->id(), 'manager' => 'Alice']);

            $loaded = Product::find($product->id(), ['managers']);

            $this->assertCount(1, $loaded->managers);
            $this->assertEquals('Alice', $loaded->managers[0]->manager);
        });
    }

    /** toArray() wrote every date with a time, which the DATE field's own rule then refused. */
    public function testADateFieldSurvivesARoundTripThroughAnArray()
    {
        $event = new class(['day' => new \DateTime('2024-03-01 15:30:00')]) extends Model {
            public static function table(): string { return 'event'; }
            public static function fields(): array { return ['day' => ModelField::date('day')]; }
            public static function relations(): array { return []; }
            protected function allowNonTableAttributeSet(): bool { return true; }
        };
        $event->note = new \DateTime('2024-03-01 15:30:00');

        $array = $event->toArray();

        $this->assertEquals('2024-03-01', $array['day']);
        $this->assertEquals('2024-03-01 15:30:00', $array['note']);
        $this->assertEquals('2024-03-01', $event::fromArray(['day' => $array['day']])->toArray()['day']);
    }

    public function testAPrimaryKeyMethodNamesTheModelMissingIt()
    {
        $this->expectException(MissingModelPrimaryKeyException::class);
        $this->expectExceptionMessage(ModuleUser::class.' does not have a primary key, deleteId() needs one');

        ModuleUser::deleteId(1);
    }

    #[ DataProvider('getDatabases') ]
    public function testAColumnHoldingNullStaysOnTheModel(Database $database)
    {
        $database->asGlobalInstance(function() {
            $product = new Product(['name' => 'screen', 'price_dollar' => null]);

            // Dropping the key would make toArray() answer a different shape per row
            $this->assertArrayHasKey('price_dollar', $product->toArray());
            $this->assertNull($product->toArray()['price_dollar']);
        });
    }

    #[ DataProvider('getDatabases') ]
    public function testAssigningAnUnknownFieldIsRefused(Database $database)
    {
        $database->asGlobalInstance(function() {
            $product = new Product(['name' => 'screen']);

            $this->expectException(\InvalidArgumentException::class);

            $product->tihs_is_a_typo = 'lost in silence';
        });
    }

    protected function withBaseProducts(Database $database, callable $function) {
        $database->asGlobalInstance(function() use ($function) {
            Product::insertArray(['name' => 'screen']);
            Product::insertArray(['name' => 'mouse']);
            Product::insertArray(['name' => 'keyboard']);
            $function();
        });
    }

    #[ DataProvider('getDatabases') ]
    public function testBase(Database $database)
    {
        $database->asGlobalInstance(function(){
            $moduleUser = ModuleUser::findWhere(['user' => 1], ['_user', '_module']);

            $this->assertEquals($moduleUser->user, 1);
            $this->assertEquals($moduleUser->module, 4);

            $this->assertInstanceOf(User::class, $moduleUser->_user);
            $this->assertInstanceOf(Module::class, $moduleUser->_module);
        });
    }

    public function testFromRequest() {
        $sampleReq = fn($params) => new Request("GET", "/", [], $params);

        $prod = Product::fromRequest($sampleReq([
            "id" => 1,
            "created_at" => '2025-01-01 01:01:01',
            "name" => "Book",
            "price_dollar" => 20
        ]));
        $this->assertEquals(["created_at" => '2025-01-01 01:01:01', "name" => "Book","price_dollar" => '20'], $prod->toArray());


        $prod = Product::fromRequest($sampleReq([
            "id" => 1,
            "name" => "Book",
            "price_dollar" => 20
        ]), ["id" => 2], true, ["created_at"]);
        $this->assertEquals(["id" => 2, "name" => "Book", "price_dollar" => '20'], $prod->toArray());

    }

    public function testTable() {
        $this->assertEquals("product", Product::table());
        $this->assertEquals("user", User::table());
    }

    public function testFields()
    {
        $fields = Product::fields();
        $this->assertCount(4, $fields);
    }

    public function testRelations() {
        $this->assertEquals(['managers'], Product::relations());
    }

    public function testPrimaryKey() {
        $this->assertEquals('id', Product::primaryKey());
        $this->assertNull(ProductManager::primaryKey());
    }

    public function testId() {
        $product = new Product(['id' => 1, 'name' => 'screen']);
        $this->assertEquals(1, $product->id());

        $product->id = 12;
        $this->assertEquals(12, $product->id());
    }

    public function testHasField() {
        $this->assertTrue(Product::hasField('id'));
        $this->assertTrue(Product::hasField('name'));
        $this->assertFalse(Product::hasField('ID'));
    }

    #[ DataProvider('getDatabases') ]
    public function testSelect(Database $database) {
        $this->withBaseProducts($database, function(){
            $this->assertCount(3, Product::select()->fetch());
            $this->assertCount(1, Product::select()->where('name', 'mouse')->fetch());
        });
    }
    #[ DataProvider('getDatabases') ]
    public function testUpdate(Database $database) {
        $this->withBaseProducts($database, function(){
            Product::update()->where('name', 'screen')->set('name', 'monitor')->fetch();

            $this->assertNull(Product::findWhere(['name' => 'screen']));
            $this->assertInstanceOf(Product::class, Product::findWhere(['name' => 'monitor']));
        });
    }

    #[ DataProvider('getDatabases') ]
    public function testUpdateRow(Database $database) {
        $this->withBaseProducts($database, function(){
            $newProduct = Product::insertArray(['name' => 'ethernet card']);
            $id = $newProduct->id();

            $this->assertEquals('ethernet card', Product::find($id)->name);
            Product::updateRow($id, ['name' => 'wifi card']);
            $this->assertEquals('wifi card', Product::find($id)->name);
        });
    }

    #[ DataProvider('getDatabases') ]
    public function testPatch(Database $database) {
        $this->withBaseProducts($database, function(){
            $newProduct = Product::insertArray(['name' => '500W PowerSupply']);
            $id = $newProduct->id();

            $this->assertEquals('500W PowerSupply', Product::find($id)->name);

            $newProduct->patch(['name' => '1000W PowerSupply']);
            $this->assertEquals('1000W PowerSupply', $newProduct->name);
            $this->assertEquals('1000W PowerSupply', Product::find($id)->name);
        });
    }

    #[ DataProvider('getDatabases') ]
    public function testInsert(Database $database) {
        $this->withBaseProducts($database, function(){
            Product::insert()
                ->insertField(['name'])
                ->values(['graphic card'], ['monitor'], ['cables'])
                ->fetch();

            $this->assertEquals(6, Product::select()->count());
        });
    }

    #[ DataProvider('getDatabases') ]
    public function testLast(Database $database) {
        $this->withBaseProducts($database, function(){
            $this->assertNotEquals('ram memory', Product::last()->name);

            Product::insertArray(['name' => 'ram memory']);

            $this->assertEquals('ram memory', Product::last()->name);
        });
    }

    #[ DataProvider('getDatabases') ]
    public function testExistsWhere(Database $database) {
        $this->withBaseProducts($database, function(){

            Product::insertArray(['name' => 'printer', 'price_dollar' => 100]);

            $this->assertTrue(Product::existsWhere(['name' => 'printer']));
            $this->assertFalse(Product::existsWhere(['name' => 'void']));
            $this->assertTrue(Product::existsWhere(['name' => 'printer', 'price_dollar' => 100]));
            $this->assertFalse(Product::existsWhere(['name' => 'printer', 'price_dollar' => 30]));
        });
    }

    #[ DataProvider('getDatabases') ]
    public function testExists(Database $database) {
        $this->withBaseProducts($database, function(){
            $product = Product::insertArray(['name' => 'printer']);

            $this->assertTrue(Product::exists($product->id()));
            $this->assertFalse(Product::exists($product->id()+1)); // Last Id+1 shouldn't exists
        });
    }

    #[ DataProvider('getDatabases') ]
    public function testFind(Database $database) {
        $this->withBaseProducts($database, function(){
            $product = Product::insertArray(['name' => 'printer']);

            $this->assertInstanceOf(Product::class, Product::find($product->id()));
            $this->assertNull(Product::find($product->id()+1));
        });
    }

    #[ DataProvider('getDatabases') ]
    public function testFindOrCreate(Database $database) {
        $this->withBaseProducts($database, function(){
            $printer = Product::findOrCreate(['name' => 'printer'], extrasProperties: ['price_dollar' => 30]);

            $this->assertEquals(30, $printer->price_dollar);

            $foundPrinter = Product::findOrCreate(['name' => 'printer'], extrasProperties: ['price_dollar' => 50]);

            $this->assertEquals($printer->id(), $foundPrinter->id());
            $this->assertEquals(50, $foundPrinter->price_dollar);

            $printer->reload();
            $this->assertEquals(50, $printer->price_dollar);
        });
    }

    #[ DataProvider('getDatabases') ]
    public function testDelete(Database $database) {
        $this->withBaseProducts($database, function(){
            Product::delete()->fetch();
            $this->assertEquals(0, Product::select()->count());

            Product::insert()->insertField(['name', 'price_dollar'])
            ->values(['printer', 50], ['headset', 50], ['keyboard', 60], ['mouse', 40], ['graphic card', 1200])
            ->fetch();

            $this->assertEquals(5, Product::select()->count());

            Product::delete()->where('price_dollar', 50, '>')->fetch(); // Delete keyboard and graphic card
            $this->assertEquals(3, Product::select()->count());

            Product::delete()->where('name', 'headset')->fetch();
            $this->assertEquals(2, Product::select()->count());
        });
    }


    #[ DataProvider('getDatabases') ]
    public function testLoadMissing(Database $database) 
    {
        $database->asGlobalInstance(function() {
            Product::insertArray([
                'name' => 'Nice painting',
                'price_dollar' => 50,
                'managers' => [
                    ['manager' => 'Bob'],
                    ['manager' => 'Jack'],
                ]
            ]);

            $product = Product::findWhere(['name' => 'Nice painting']);
            $this->assertInstanceOf(Product::class, $product);

            $product->loadMissing('managers');
            $this->assertCount(2, $product->managers);
        });
    }

    public function testMergeWithArray()
    {
        $product = new Product(['id' => 1, 'name' => 'screen', 'price_dollar' => 10]);

        $product->merge(['name' => 'monitor', 'price_dollar' => 20]);

        $this->assertEquals('monitor', $product->name);
        $this->assertEquals(20, $product->price_dollar);
    }

    public function testMergeThrowsOnList()
    {
        $this->expectException(\InvalidArgumentException::class);

        $product = new Product(['name' => 'screen']);
        $product->merge(['monitor', 'keyboard']);
    }

    public function testMergeWithRequest()
    {
        $product = new Product(['id' => 1, 'name' => 'screen', 'price_dollar' => 10]);

        $request = new Request('POST', '/', [], ['name' => 'monitor', 'price_dollar' => 99]);
        $product->merge($request);

        $this->assertEquals('monitor', $product->name);
        $this->assertEquals(99, $product->price_dollar);
    }

    /**
     * __get() used to chain its lookups with ??, so a field holding NULL fell through to the
     * "unknown attribute" exception
     */
    #[ DataProvider('getDatabases') ]
    public function testReadingAFieldHoldingNull(Database $database)
    {
        $database->asGlobalInstance(function() {
            $product = Product::insertArray(['name' => 'screen']);

            $this->assertNull($product->price_dollar);
        });
    }

    /**
     * saveExisting() used to target the row through the current primary key value instead of the original one
     */
    #[ DataProvider('getDatabases') ]
    public function testSavingAChangedPrimaryKeyNeverTouchesAnotherRow(Database $database)
    {
        $database->asGlobalInstance(function() {
            $screen = Product::insertArray(['name' => 'screen']);
            $mouse = Product::insertArray(['name' => 'mouse']);

            $screen->id = $mouse->id;
            $screen->name = 'renamed screen';
            try {
                $screen->save();
            } catch (\PDOException) {
            }

            $this->assertEquals('mouse', Product::find($mouse->id)->name);
        });
    }

    /**
     * save() used to pick an UPDATE as soon as the primary key was filled, so the row was never inserted
     */
    #[ DataProvider('getDatabases') ]
    public function testInsertingWithAnExplicitPrimaryKey(Database $database)
    {
        $database->asGlobalInstance(function() {
            Product::insertArray(['id' => 10, 'name' => 'keyboard']);

            $this->assertEquals('keyboard', Product::find(10)?->name);
        });
    }

    #[ DataProvider('getDatabases') ]
    public function testInsertingADateTime(Database $database)
    {
        $database->asGlobalInstance(function() use ($database) {
            Product::insertArray(['name' => 'screen', 'created_at' => new \DateTime('2024-05-06 15:30:00')]);

            $this->assertStringStartsWith(
                '2024-05-06 15:30:00',
                $database->query('SELECT created_at FROM product')[0]['created_at']
            );
        });
    }

    /**
     * saveExisting() used to format a DateTime with a time only for DATE fields, and with a 12-hour clock
     */
    #[ DataProvider('getDatabases') ]
    public function testUpdatingADateTimeKeepsItsTime(Database $database)
    {
        $database->asGlobalInstance(function() use ($database) {
            $product = Product::insertArray(['name' => 'screen']);

            $product->created_at = new \DateTime('2024-05-06 15:30:00');
            $product->save();

            $this->assertStringStartsWith(
                '2024-05-06 15:30:00',
                $database->query('SELECT created_at FROM product')[0]['created_at']
            );
        });
    }

    /**
     * destroy() used to return early through existsInDatabase(), which required a primary key
     */
    #[ DataProvider('getDatabases') ]
    public function testDestroyAModelWithoutPrimaryKey(Database $database)
    {
        $database->asGlobalInstance(function() {
            $product = Product::insertArray(['name' => 'screen']);
            $manager = ProductManager::insertArray(['product' => $product->id, 'manager' => 'Alice']);

            $manager->destroy();

            $this->assertEquals(0, ProductManager::select()->count());
        });
    }

    #[ DataProvider('getDatabases') ]
    public function testLastOnAnEmptyTable(Database $database)
    {
        $database->asGlobalInstance(function() {
            $this->assertNull(Product::last());
        });
    }

    #[ DataProvider('getDatabases') ]
    public function testIdOfAModelWithoutItsPrimaryKey(Database $database)
    {
        $database->asGlobalInstance(function() {
            $this->assertFalse((new Product(['name' => 'screen']))->id());
        });
    }

    #[ DataProvider('getDatabases') ]
    public function testReloadingAMissingRowIsRefused(Database $database)
    {
        $database->asGlobalInstance(function() {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/no row has \[id\]/');

            (new Product(['id' => 404, 'name' => 'screen']))->reload();
        });
    }

    /**
     * reload() used to leave the model unpersisted, so the next save() inserted the row again
     */
    #[ DataProvider('getDatabases') ]
    public function testSavingAReloadedModelUpdatesItsRow(Database $database)
    {
        $database->asGlobalInstance(function() {
            $screen = Product::insertArray(['name' => 'screen']);

            $copy = new Product(['id' => $screen->id, 'name' => 'screen']);
            $copy->reload();
            $copy->name = 'monitor';
            $copy->save();

            $this->assertEquals(1, Product::select()->count());
            $this->assertEquals('monitor', Product::find($screen->id)->name);
        });
    }

    /*

    #[ DataProvider('getDatabases') ]
    public function testDeleteId(Database $database) {}
    #[ DataProvider('getDatabases') ]
    public function testDeleteWhere(Database $database) {}
    #[ DataProvider('getDatabases') ]
    public function testFromArray(Database $database) {}
    #[ DataProvider('getDatabases') ]
    public function testGetReference(Database $database) {}
    #[ DataProvider('getDatabases') ]
    public function testSetReference(Database $database) {}
    #[ DataProvider('getDatabases') ]
    public function testPushReference(Database $database) {}
    #[ DataProvider('getDatabases') ]
    public function test__get(Database $database) {}
    #[ DataProvider('getDatabases') ]
    public function testToArray(Database $database) {}
    #[ DataProvider('getDatabases') ]
    public function testLoad(Database $database) {}
    #[ DataProvider('getDatabases') ]
    public function testOnSaved(Database $database) {}
    #[ DataProvider('getDatabases') ]
    public function testSave(Database $database) {}
    #[ DataProvider('getDatabases') ]
    public function testDestroy(Database $database) {}
    #[ DataProvider('getDatabases') ]
    public function testReload(Database $database) {}
    #[ DataProvider('getDatabases') ]
    public function testAnonymize(Database $database) {}
    #[ DataProvider('getDatabases') ]
    public function testReplicate(Database $database) {}
    */
}
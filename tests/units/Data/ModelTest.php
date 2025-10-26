<?php 

namespace Cube\Tests\Units\Data;

use Cube\Data\Database\Database;
use Cube\Tests\Units\Database\TestMultipleDrivers;
use Cube\Tests\Units\Models\Module;
use Cube\Tests\Units\Models\ModuleUser;
use Cube\Tests\Units\Models\Product;
use Cube\Tests\Units\Models\User;
use Cube\Web\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ModelTest extends TestCase
{
    use TestMultipleDrivers;

    #[ DataProvider('getDatabases') ]
    public function testBase(Database $database)
    {
        $database->asGlobalInstance(function(){
            $moduleUser = ModuleUser::findWhere(['user' => 1]);

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
            "created_at" => '2025-01-01',
            "name" => "Book",
            "price_dollar" => 20
        ]));
        $this->assertEquals(["created_at" => '2025-01-01', "name" => "Book","price_dollar" => '20'], $prod->toArray());


        $prod = Product::fromRequest($sampleReq([
            "id" => 1,
            "created_at" => '2025-01-01',
            "name" => "Book",
            "price_dollar" => 20
        ]), ["id" => 2], true, ["created_at"]);
        $this->assertEquals(["id" => 2, "name" => "Book", "price_dollar" => '20'], $prod->toArray());

    }
}
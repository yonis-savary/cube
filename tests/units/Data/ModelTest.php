<?php 

namespace Cube\Tests\Units\Data;

use Cube\Data\Database\Database;
use Cube\Tests\Units\Database\TestMultipleDrivers;
use Cube\Tests\Units\Models\Module;
use Cube\Tests\Units\Models\ModuleUser;
use Cube\Tests\Units\Models\User;
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
}
<?php

namespace Cube\Tests\Units\Web;

use Cube\Data\Database\Database;
use Cube\Tests\Units\Database\TestMultipleDrivers;
use Cube\Tests\Units\Models\Product;
use Cube\Web\Http\Rules\AnyParam;
use Cube\Web\Http\Rules\Param;
use Cube\Web\Http\Rules\Rule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase
{
    use TestMultipleDrivers;

    protected function testRule(Rule $rule, mixed $value, bool $shouldPass=true, mixed $expectedFinalValue=null) {
        $results = $rule->validate($value);
        $expectedFinalValue ??= $value;
        if ($shouldPass) {
            $this->assertTrue($results->isValid());
            $this->assertEquals($expectedFinalValue, $results->getResult());
        } else {
            $this->assertFalse($results->isValid());
        }
    }

    public function testAnyParamValidation() {

        $rule = new AnyParam(true);

        $this->testRule($rule, null, true);
        $this->testRule($rule, 0, true);
        $this->testRule($rule, '', true);
        $this->testRule($rule, 'some test', true);
        $this->testRule($rule, $rule, true);
    }

    public function testIntegerValidation() {
        $rule = Param::integer(true);

        $this->testRule($rule, null);
        $this->testRule($rule, 5);
        $this->testRule($rule, '5', true, 5); // Assert value is parsed

        $this->testRule($rule, '', false);
        $this->testRule($rule, 'some test', false);
        $this->testRule($rule, $this, false);


        $rule = Param::integer(false); // Non-nullable

        $this->testRule($rule, 5);
        $this->testRule($rule, '5', true, 5);
        $this->testRule($rule, null, false);


        $rule = Param::integer()->isBetween(0, 10);
        $this->testRule($rule, -5, false);
        $this->testRule($rule, 0);
        $this->testRule($rule, 5);
        $this->testRule($rule, 10);
        $this->testRule($rule, 11, false);

        $rule = Param::integer()->inArray([0,2,4,6,8]);
        $this->testRule($rule, 11, false);
        $this->testRule($rule, 0);
        $this->testRule($rule, 6);
        $this->testRule($rule, 8);
        $this->testRule($rule, 9, false);
    }


    public function testFloatValidation() {
        $rule = Param::float(true);

        $this->testRule($rule, null);
        $this->testRule($rule, 5.2);
        $this->testRule($rule, '5.2', true, 5.2); // Assert value is parsed

        $this->testRule($rule, '', false);
        $this->testRule($rule, 'some test', false);
        $this->testRule($rule, $this, false);


        $rule = Param::float(false); // Non-nullable

        $this->testRule($rule, '3.14', true, 3.14);
        $this->testRule($rule, null, false);


        $rule = Param::float()->isBetween(-3.14, 3.14);
        $this->testRule($rule, -3.15, false);
        $this->testRule($rule, -3.14);
        $this->testRule($rule, 0);
        $this->testRule($rule, 3.14);
        $this->testRule($rule, 3.15, false);
    }


    public function testStringValidation() {

        $rule = Param::string(false, true); // No modification / Nullable

        $this->testRule($rule, null);
        $this->testRule($rule, 'hello ', true, 'hello ');
        $this->testRule($rule, 5, true, '5');

        $rule = Param::string(true, true);
        $this->testRule($rule, null);
        $this->testRule($rule, 'hello ', true, 'hello');

        $rule = Param::string(true, false);
        $this->testRule($rule, null, false);
        $this->testRule($rule, 'hello ', true, 'hello');
    }

    public function testArrayValidation() {
        $rule = Param::array(Param::integer(true)->isBetween(0, 5), true); // 0-5 / nullable
        $this->testRule($rule, null);
        $this->testRule($rule, [0,1,2,4,5,null]);
        $this->testRule($rule, [0,1,2,4,-5], false);

        $rule = Param::array(Param::integer(true)->isBetween(0, 5), false);
        $this->testRule($rule, null, false);
        $this->testRule($rule, [0,1,2,4,5,null]);
        $this->testRule($rule, [0,1,2,4,5]);

        $rule = Param::array(Param::integer(false)->isBetween(0, 5), false);
        $this->testRule($rule, [0,1,2,4,5]);
        $this->testRule($rule, [0,1,2,4,5,null], false);

        $rule = Param::array(Param::integer(false))->size(3);
        $this->testRule($rule, [], false);
        $this->testRule($rule, [0], false);
        $this->testRule($rule, [0,1], false);
        $this->testRule($rule, [0,1,2], true);
        $this->testRule($rule, [0,1,2,3], false);


        $rule = Param::array(Param::integer(false))->minSize(2);
        $this->testRule($rule, [], false);
        $this->testRule($rule, [0], false);
        $this->testRule($rule, [0,1], true);
        $this->testRule($rule, [0,1,2], true);
        $this->testRule($rule, [0,1,2,3], true);

        $rule = Param::array(Param::integer(false))->maxSize(2);
        $this->testRule($rule, [], true);
        $this->testRule($rule, [0], true);
        $this->testRule($rule, [0,1], true);
        $this->testRule($rule, [0,1,2], false);
        $this->testRule($rule, [0,1,2,3], false);


        $rule = Param::array(
            Param::array(
                Param::integer(false)->isBetween(0, 5)
            ), false
        );
        $this->testRule($rule, [
            [0,1,2,5],
            [3,5,3,5],
            [0,1,4,2],
        ]);

        $this->testRule($rule, [
            [0,1,2,5],
            [3,-6,3,5],
            [0,1,4,2],
        ], false);

    }


    public function testObjectValidation() {

        $rule = Param::object([
            "age" => Param::integer(false)->isBetween(0, 120),
            "option" => Param::boolean(true)
        ]);

        $this->testRule($rule, ['age' => 5, 'option' => true]);
        $this->testRule($rule, ['age' => 5, 'option' => null]);
        $this->testRule($rule, ['age' => 5], true, ['age' => 5, 'option' => null]);
        $this->testRule($rule, ['age' => 'invalid'], false);
        $this->testRule($rule, ['age' => 'invalid', 'option' => 'invalid'], false);


        $rule = Param::object([
            "age" => Param::integer(false)->isBetween(0, 120),
            "option" => Param::boolean()->default(true)
        ]);
        $this->testRule($rule, ['age' => 5], true, ['age' => 5, 'option' => true]);

        $rule = Param::object([
            "age" => Param::integer(false)->isBetween(0, 120)->default(21),
            "option" => Param::boolean()->default(true)
        ]);
        $this->testRule($rule, [], true, ['age' => 21, 'option' => true]);
        $this->testRule($rule, null, true, ['age' => 21, 'option' => true]);

        $rule = Param::object([
            "age" => Param::integer(false)->isBetween(0, 120),
            "option" => Param::boolean()->default(true)
        ]);
        $this->testRule($rule, [], false);
        $this->testRule($rule, null, false);

    }

    public function testEmailValidation() {
        $rule = Param::email();

        $this->testRule($rule, 'hello', false);
        $this->testRule($rule, 'hello@', false);

        $this->testRule($rule, 'hello@goobye.org');
        $this->testRule($rule, 'hello+service@goobye.org');
        $this->testRule($rule, 'hello@goobye.org$$$', false);
        $this->testRule($rule, '@goobye.org', false);
    }

    public function testBooleanValidation() {
        $rule = Param::boolean();

        $this->testRule($rule, 'true', true, true);
        $this->testRule($rule, 'True', true, true);
        $this->testRule($rule, '1', true, true);
        $this->testRule($rule, true, true, true);
        $this->testRule($rule, 'yes', true, true);
        $this->testRule($rule, 'YES', true, true);
        $this->testRule($rule, 'on', true, true);
        $this->testRule($rule, 'On', true, true);

        $this->testRule($rule, 'false', true, false);
        $this->testRule($rule, 'False', true, false);
        $this->testRule($rule, '0', true, false);
        $this->testRule($rule, false, true, false);
        $this->testRule($rule, 'Off', true, false);
        $this->testRule($rule, 'off', true, false);
        $this->testRule($rule, 'no', true, false);
        $this->testRule($rule, 'NO', true, false);
    }

    public function testUrlValidation() {
        $rule = Param::url();

        $this->testRule($rule, 'hello.com');
        $this->testRule($rule, 'http://hello.com');
        $this->testRule($rule, 'http://www.hello.com');
        $this->testRule($rule, 'hellocom', false);
        $this->testRule($rule, 'hello.com$$$', false);
    }

    public function testDateValidation() {
        $rule = Param::date();

        $this->testRule($rule, '2025-01-01');
        $this->testRule($rule, '2025-13-01', false);
        $this->testRule($rule, '2025-13-32', false);
        $this->testRule($rule, '02025-01-11', false);
        $this->testRule($rule, '2025-01-011', false);
        $this->testRule($rule, '2025-001-11', false);
        $this->testRule($rule, 'hello?', false);

        $rule = Param::date(false)->isBetween('2000-01-01', '2000-12-31');

        $this->testRule($rule, '1999-12-31', false);
        $this->testRule($rule, '2000-06-15');
        $this->testRule($rule, '2001-01-01', false);
    }

    public function testDatetimeValidation() {
        $rule = Param::datetime();

        $this->testRule($rule, '2025-01-01 00:00:00');
        $this->testRule($rule, '2025-12-31 23:59:59');

        $this->testRule($rule, '2025-13-31 23:59:59', false);
        $this->testRule($rule, '2025-12-99 23:59:59', false);
        $this->testRule($rule, '2025-12-31 99:59:59', false);
        $this->testRule($rule, '2025-12-31 23:99:59', false);
        $this->testRule($rule, '2025-12-31 23:59:99', false);

        $this->testRule($rule, '2025-01-01', false);
        $this->testRule($rule, 'hello?', false);

        $rule = Param::datetime(false, true); // Add time if missing
        $this->testRule($rule, '2025-01-01 00:00:00');
        $this->testRule($rule, '2025-01-01', true, '2025-01-01 00:00:00');

    }

    public function testUuidValidation() {
        $rule = Param::uuid();

        $this->testRule($rule, 'hello', false);
        $this->testRule($rule, '7a710a76-dd80-432a-8672-fb390030164c');
        $this->testRule($rule, 'c91a4830-23ef-498a-a559-cff6cc19a727');
        $this->testRule($rule, 'c91a4830-23ef-498a-a559-trytocheat00', false);
    }

    #[ DataProvider('getDatabases') ]
    public function testModelKeyValidation(Database $database) {
        $database->asGlobalInstance(function(){
            $newData = [
                ['name' => 'Desk'],
                ['name' => 'Mouse'],
                ['name' => 'Keyboard'],
            ];
            foreach($newData as $row) {
                Product::insertArray($row);
            }
    
            $rule = Param::model(Product::class, 'name');
    
            $results = $rule->validate('Desk');
            $this->assertTrue($results->isValid());
            $product = $results->getResult();
            $this->assertInstanceOf(Product::class, $product);
            $this->assertEquals('Desk', $product->name);
    
            $results = $rule->validate('Inexistant Object');
            $this->assertFalse($results->isValid());

            $rule = Param::model(Product::class, 'id', true);
            $results = $rule->validate($product->id);
            $this->assertTrue($results->isValid());
            $productFromId = $results->getResult();
            $this->assertEquals($productFromId->id, $product->id);

            $results = $rule->validate(null);
            $this->assertTrue($results->isValid());
            $this->assertNull($results->getResult());
        });
    }

    public function testNullableEdition() 
    {
        // Optionnal at first
        $rule = Param::array(Param::integer(), true);

        // then made mandatory
        $rule->nullable(false);

        $results = $rule->validate(null);
        $this->assertFalse($results->isValid());

        $results = $rule->validate([1,2,3]);
        $this->assertTrue($results->isValid());
    }

    public function testModelValidation()
    {
        $rule = Product::toObjectParam();

        $results = $rule->validate([]);
        $this->assertFalse($results->isValid());

        $results = $rule->validate(['name' => 'Painting']);
        $this->assertTrue($results->isValid());

        $rule = Product::toObjectParam(false, true)
            ->mandatory('managers');

        $result = $rule->validate(['name' => 'Painting']);
        $this->assertFalse($result->isValid()); // Manager is now mandatory

        $result = $rule->validate(['name' => 'Painting', 'managers' => [['manager' => 'Bob'], ['manager' => 'Homer']]]);
        $this->assertTrue($result->isValid());

        /** @var Product $model */
        $model = $results->getResult();

        $this->assertInstanceOf(Product::class, $model);
        $this->assertEquals('Painting', $model->name);

    }
}
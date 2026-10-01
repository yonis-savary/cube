<?php

namespace Cube\Tests\Units\Web;

use Cube\Data\Database\Database;
use Cube\Tests\Units\Database\TestMultipleDrivers;
use Cube\Tests\Units\Models\Product;
use Cube\Web\Http\Rules\AnyParam;
use Cube\Web\Http\Rules\Param;
use Cube\Web\Http\Rules\Rule;
use Cube\Web\Http\Rules\UploadRule;
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

    public function testAnyKeyObjectValidation() {

        $rule = Param::anyKeyObject(
            Param::integer(true)->isBetween(0, 100)
        );

        $this->testRule($rule, ['max' => 5, 'mary' => 12]);
        $this->testRule($rule, ['max' => 5, 'mary' => null]);
        $this->testRule($rule, ['max' => 'invalid'], false);
        $this->testRule($rule, ['max' => 'invalid', 'mary' => 12], false);


        $rule = Param::anyKeyObject(
            Param::integer(false)->isBetween(0, 100)
        );
        $this->testRule($rule, ['max' => null], false);
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

    /**
     * default() fills a missing value in, it does not overwrite the one that came in : the
     * transformer used to ignore its argument and hand the default back every time.
     */
    public function testDefaultOnlyReplacesAMissingValue()
    {
        $rule = Param::integer(true)->default(21);

        $this->testRule($rule, null, true, 21);
        $this->testRule($rule, 5, true, 5);
        $this->testRule($rule, '5', true, 5);
    }

    public function testDefaultRunsBeforeTheConditions()
    {
        $rule = Param::integer()->isBetween(0, 10)->default(5);

        $this->testRule($rule, null, true, 5);

        $rule = Param::integer()->isBetween(0, 10)->default(50);

        $this->testRule($rule, null, false);
    }

    public function testDefaultInsideAnObject()
    {
        $rule = Param::object([
            'name' => Param::string(),
            'quantity' => Param::integer(true)->default(1),
        ]);

        $this->testRule($rule, ['name' => 'screen'], true, ['name' => 'screen', 'quantity' => 1]);
        $this->testRule($rule, ['name' => 'screen', 'quantity' => 12], true, ['name' => 'screen', 'quantity' => 12]);
    }

    public function testOptionalAndMandatoryEditTheSameRules()
    {
        $rule = Param::object([
            'name' => Param::string(),
            'price' => Param::integer(),
        ]);

        $this->testRule($rule, ['name' => 'screen'], false);

        $rule->optional('price');
        $this->testRule($rule, ['name' => 'screen'], true, ['name' => 'screen', 'price' => null]);

        $rule->mandatory('price');
        $this->testRule($rule, ['name' => 'screen'], false);
    }

    public function testObjectRulesCanBeAddedAndRemoved()
    {
        $rule = Param::object(['name' => Param::string(), 'price' => Param::integer()]);

        $rule->without('price');
        $this->testRule($rule, ['name' => 'screen'], true, ['name' => 'screen']);

        $rule->with(['quantity' => Param::integer()]);
        $this->testRule($rule, ['name' => 'screen'], false);
        $this->testRule($rule, ['name' => 'screen', 'quantity' => 2], true, ['name' => 'screen', 'quantity' => 2]);
    }

    public function testAStringRuleRefusesWhatIsNotAString()
    {
        $rule = Param::string();

        $this->testRule($rule, ['an', 'array'], false);
        $this->testRule($rule, new \stdClass(), false);
    }

    /**
     * The index of the failing item is what tells the caller which one to fix, and the loop
     * counter used to overwrite the parameter carrying the path.
     */
    public function testAnArrayReportsWhichItemFailed()
    {
        $rule = Param::array(Param::integer());

        $errors = $rule->validate([1, 'nope', 3], 'quantities')->getErrors();

        $this->assertArrayHasKey(1, $errors);
        $this->assertArrayNotHasKey(0, $errors);
        $this->assertStringContainsString('quantities.1', $errors[1][0]);
    }

    public function testANullableDatetimeStaysNullWhenTimeIsAddedAutomatically()
    {
        $rule = Param::datetime(true, true);

        $this->testRule($rule, null, true, null);
        $this->testRule($rule, '2025-01-01', true, '2025-01-01 00:00:00');
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

    /** ObjectParam::validate() never ran its own checker steps, so a condition on the whole object was ignored. */
    public function testAConditionOnAnObjectIsChecked()
    {
        $rule = Param::object(['password' => Param::string(), 'confirmation' => Param::string()])
            ->withCondition(fn ($value) => $value['password'] === $value['confirmation'], 'passwords do not match');

        $this->assertFalse($rule->validate(['password' => 'secret', 'confirmation' => 'other'])->isValid());
    }

    /** ArrayParam::validate() never ran its own checker steps, so a condition on the whole list was ignored. */
    public function testAConditionOnAnArrayIsChecked()
    {
        $rule = Param::array(Param::integer())
            ->withCondition(fn ($value) => array_sum($value) <= 10, 'total cannot exceed 10');

        $this->assertFalse($rule->validate([8, 9])->isValid());
    }

    /** A null object was replaced by [] before the nullable check, so its children reported missing values. */
    public function testANullableObjectAcceptsNull()
    {
        $result = Param::object(['street' => Param::string()], true)->validate(null);

        $this->assertEquals([], $result->getErrors());
        $this->assertNull($result->getResult());
    }

    public static function getMalformedValues(): array
    {
        return [
            'string as object' => [Param::object(['street' => Param::string()]), 'main street'],
            'string as list' => [Param::array(Param::string()), 'chair'],
            'array as date' => [Param::date(), ['2024-01-01']],
            'array as datetime' => [Param::datetime(), ['2024-01-01 10:00:00']],
            'array as url' => [Param::url(), ['https://example.com']],
            'array as uuid' => [Param::uuid(), ['x']],
            'string as upload' => [new UploadRule(), 'avatar.png'],
            'array as boolean' => [Param::boolean(), ['yes']],
            'array as typed condition' => [Param::string()->withCondition(fn (string $value) => strlen($value) > 3, 'too short'), ['long enough']],
            'string as object of a nullable list' => [Param::array(Param::object(['name' => Param::string()]), true), ['bob']],
        ];
    }

    /** A value of the wrong type reached a typed closure and threw a TypeError instead of being refused. */
    #[ DataProvider('getMalformedValues') ]
    public function testAMalformedValueIsRefusedWithoutThrowing(Rule $rule, mixed $value)
    {
        $this->assertFalse($rule->validate($value)->isValid());
    }

    public function testAFailedCheckStopsTheFollowingSteps()
    {
        $result = Param::integer()
            ->withCondition(fn ($value) => is_int($value), 'must have been converted')
            ->validate('abc', 'quantity');

        $this->assertCount(1, $result->getErrors());
    }

    public function testATransformerOnAnObjectReceivesTheValidatedChildren()
    {
        $rule = Param::object(['first' => Param::string(), 'last' => Param::string()])
            ->withTransformer(fn (array $value) => "{$value['first']} {$value['last']}");

        $this->assertEquals('Ada Lovelace', $rule->validate(['first' => ' Ada ', 'last' => 'Lovelace'])->getResult());
    }

    public function testAConditionOnAnObjectWaitsForValidChildren()
    {
        $rule = Param::object(['password' => Param::string()])
            ->withCondition(fn (array $value) => strlen($value['password']) > 8, 'password too short');

        $this->assertEquals(['password' => ['password cannot be null']], $rule->validate([])->getErrors());
    }

    /** integer() used to accept anything is_numeric() accepts, truncating it on the way. */
    public function testIntegerOnlyAcceptsWholeNumbers()
    {
        foreach ([5, '5', '-12', (string) PHP_INT_MAX] as $valid) {
            $this->assertTrue(Param::integer()->validate($valid)->isValid(), var_export($valid, true).' should be accepted');
        }

        foreach (['1.9', ' 5', '5 ', '1e3', '0x1A', '99999999999999999999', 1.5, true, ''] as $invalid) {
            $this->assertFalse(Param::integer()->validate($invalid)->isValid(), var_export($invalid, true).' should be refused');
        }

        $this->assertSame(-12, Param::integer()->validate('-12')->getResult());
    }

    /** date() and datetime() used to accept any day from 01 to 31, whatever the month. */
    public function testDatesMustExistInTheCalendar()
    {
        $this->assertTrue(Param::date()->validate('2024-02-29')->isValid());
        $this->assertFalse(Param::date()->validate('2023-02-29')->isValid());
        $this->assertFalse(Param::date()->validate('2024-04-31')->isValid());

        $this->assertTrue(Param::datetime()->validate('2024-02-29 10:00:00')->isValid());
        $this->assertFalse(Param::datetime()->validate('2024-02-31 10:00:00')->isValid());
    }
}

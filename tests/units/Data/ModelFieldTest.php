<?php

namespace Cube\Tests\Units\Data;

use Cube\Data\Models\ModelField;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ModelFieldTest extends TestCase
{
    public function testDecimalWithoutAnyPrecision()
    {
        $field = ModelField::decimal('price');

        $this->assertEquals(10, $field->decimalMaximumDigits);
        $this->assertEquals(5, $field->decimalDigitsToTheRight);
    }

    public function testDecimalWithItsOwnPrecision()
    {
        $field = ModelField::decimal('price', 8, 2);

        $this->assertEquals(8, $field->decimalMaximumDigits);
        $this->assertEquals(2, $field->decimalDigitsToTheRight);
    }

    public function testPrecisionToTheRightCannotExceedTheMaximum()
    {
        $this->expectException(\Exception::class);

        ModelField::decimal('price', 2, 8);
    }

    /**
     * Drivers do not agree on how a boolean comes back : Postgres answers 't'/'f' where
     * MySQL and SQLite answer 1/0.
     *
     * @return array<string,array{mixed,bool}>
     */
    public static function getBooleanValues(): array
    {
        return [
            'postgres true' => ['t', true],
            'postgres false' => ['f', false],
            'mysql true' => [1, true],
            'mysql false' => [0, false],
            'string one' => ['1', true],
            'string zero' => ['0', false],
            'php true' => [true, true],
            'php false' => [false, false],
            'spelled out' => ['true', true],
            'spelled out false' => ['false', false],
        ];
    }

    #[DataProvider('getBooleanValues')]
    public function testBooleanParsing(mixed $stored, bool $expected)
    {
        $this->assertSame($expected, ModelField::boolean('active')->parse($stored));
    }

    public function testNullIsParsedAsNull()
    {
        $this->assertNull(ModelField::boolean('active')->parse(null));
        $this->assertNull(ModelField::integer('count')->parse(null));
    }

    /**
     * Whatever toPHPExpression() leaves out is lost the next time `models:generate` runs,
     * and every property below is read by a migration Plan.
     */
    public function testEveryPropertyAMigrationReadsSurvivesTheExpression()
    {
        $field = ModelField::integer('id')->primaryKey()->autoIncrement()->unique()->default(5);
        $expression = $field->toPHPExpression();

        $this->assertStringContainsString('->primaryKey()', $expression);
        $this->assertStringContainsString('->autoIncrement()', $expression);
        $this->assertStringContainsString('->unique()', $expression);
        $this->assertStringContainsString('->default(5)', $expression);
    }

    public function testTheExpressionKeepsPrecisionAndDeleteBehavior()
    {
        $expression = ModelField::decimal('price', 8, 2)->onDeleteCascade()->toPHPExpression();

        $this->assertStringContainsString('->precision(8, 2)', $expression);
        $this->assertStringContainsString('->onDeleteCascade()', $expression);
    }

    public function testTheExpressionRebuildsAnEquivalentField()
    {
        $field = ModelField::decimal('price', 8, 2)->notNull()->unique()->default('0.00');

        // The generated file imports ModelField, an eval() does not inherit that import
        $expression = str_replace('new ModelField', 'new \\'.ModelField::class, $field->toPHPExpression());

        /** @var ModelField $rebuilt */
        $rebuilt = eval('return '.$expression.';');

        $this->assertEquals($field, $rebuilt);
    }

    public function testABooleanRuleFollowsTheFieldNullability()
    {
        $this->assertTrue(ModelField::boolean('active')->nullable()->toRule()->validate(null)->isValid());
        $this->assertFalse(ModelField::boolean('active')->notNull()->toRule()->validate(null)->isValid());
        $this->assertTrue(ModelField::boolean('active')->notNull()->toRule(true)->validate(null)->isValid());
    }
}

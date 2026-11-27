<?php

namespace Cube\Tests\Units\Data;

use Cube\Data\DateTimeConfiguration;
use Cube\Data\Models\ModelField;
use Cube\Env\Configuration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ModelFieldTest extends TestCase
{
    public function testADatetimeIsOutputInAtomFormat()
    {
        $value = new \DateTime('2026-11-27 14:30:00', new \DateTimeZone('+01:00'));

        $this->assertEquals('2026-11-27T14:30:00+01:00', ModelField::datetime('sent_at')->toOutput($value));
    }

    /** A timestamp is kept as the database gives it : the output has to read the string. */
    public function testATimestampStringIsOutputInAtomFormat()
    {
        $expected = (new \DateTime('2026-11-27 14:30:00'))->format(DATE_ATOM);

        $this->assertEquals($expected, ModelField::timestamp('created_at')->toOutput('2026-11-27 14:30:00'));
    }

    public function testADateIsOutputWithoutTime()
    {
        $this->assertEquals('2026-11-27', ModelField::date('due_on')->toOutput(new \DateTime('2026-11-27 14:30:00')));
        $this->assertNull(ModelField::datetime('sent_at')->toOutput(null));
    }

    public function testTheOutputFormatIsConfigurable()
    {
        $output = (new Configuration(new DateTimeConfiguration('d/m/Y H:i')))->asGlobalInstance(
            fn () => ModelField::datetime('sent_at')->toOutput(new \DateTime('2026-11-27 14:30:00'))
        );

        $this->assertEquals('27/11/2026 14:30', $output);
    }

    /** A model copied from its own toArray(), or a date sent back by a client, carries an offset the column cannot hold. */
    public function testAnRfc3339DateIsStoredInTheApplicationTimezone()
    {
        $expected = (new \DateTime('2026-11-27 13:30:00', new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->format('Y-m-d H:i:s')
        ;

        $this->assertEquals($expected, ModelField::datetime('sent_at')->format('2026-11-27T14:30:00+01:00'));
        $this->assertEquals($expected, ModelField::timestamp('created_at')->format('2026-11-27T13:30:00Z'));
    }

    public function testADatabaseFormattedDateIsStoredAsIs()
    {
        $this->assertEquals('2026-11-27 14:30:00.123456', ModelField::datetime('sent_at')->format('2026-11-27 14:30:00.123456'));
    }

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

    #[DataProvider('getBooleanValues')]
    public function testBooleanParsing(mixed $stored, bool $expected)
    {
        $this->assertSame($expected, ModelField::boolean('active')->parse($stored));
    }

    /** A boolean column came back from MySQL and SQLite as 0/1 and was answered as is, while the API documentation announces a boolean. */
    #[DataProvider('getBooleanValues')]
    public function testABooleanIsFormattedAsABoolean(mixed $stored, bool $expected)
    {
        $this->assertSame($expected, ModelField::boolean('active')->format($stored));
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

    public function testANullBooleanIsFormattedAsNull()
    {
        $this->assertNull(ModelField::boolean('active')->format(null));
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

    public function testADefaultExpressionSurvivesTheExpression()
    {
        $field = ModelField::timestamp('created_at')->defaultExpression('CURRENT_TIMESTAMP');
        $expression = str_replace('new ModelField', 'new \\'.ModelField::class, $field->toPHPExpression());

        $this->assertStringContainsString("->defaultExpression('CURRENT_TIMESTAMP')", $expression);
        $this->assertEquals($field, eval('return '.$expression.';'));
    }

    public function testALiteralDefaultReplacesADefaultExpression()
    {
        $field = ModelField::string('status')->defaultExpression('CURRENT_TIMESTAMP')->default('draft');

        $this->assertFalse($field->defaultIsExpression);
        $this->assertStringContainsString("->default('draft')", $field->toPHPExpression());
    }

    public function testABooleanRuleFollowsTheFieldNullability()
    {
        $this->assertTrue(ModelField::boolean('active')->nullable()->toRule()->validate(null)->isValid());
        $this->assertFalse(ModelField::boolean('active')->notNull()->toRule()->validate(null)->isValid());
        $this->assertTrue(ModelField::boolean('active')->notNull()->toRule(true)->validate(null)->isValid());
    }
}

<?php

namespace Cube\Tests\Units\Data;

use Cube\Data\Database\Database;
use Cube\Data\Date;
use Cube\Data\Models\Model;
use Cube\Data\Models\ModelField;
use Cube\Data\Models\ModelGenerator\Table;
use Cube\Tests\Units\Database\Providers\SQLiteProvider;
use Cube\Tests\Units\Models\Product;
use Cube\Web\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class DateHydrationTest extends TestCase
{
    public function testDateFieldsAreHydratedWhenBuilt()
    {
        $meeting = $this->meeting(['starts_at' => '2026-11-27 14:30:00', 'held_on' => '2026-11-27', 'title' => 'Retro']);

        $this->assertSame(\DateTimeImmutable::class, $meeting->starts_at::class);
        $this->assertInstanceOf(Date::class, $meeting->held_on);
        $this->assertEquals('Retro', $meeting->title);
    }

    public function testAnAssignedDateIsHydrated()
    {
        $meeting = $this->meeting();

        $meeting->starts_at = '2026-11-27 14:30:00';
        $meeting->held_on = new \DateTime('2026-11-27');

        $this->assertSame(\DateTimeImmutable::class, $meeting->starts_at::class);
        $this->assertInstanceOf(Date::class, $meeting->held_on);
    }

    /** A model compares a field to its original value to know what to save : a new instance looked changed. */
    public function testAnAlreadyHydratedValueIsKept()
    {
        $startsAt = new \DateTimeImmutable('2026-11-27 14:30:00');

        $this->assertSame($startsAt, $this->meeting(['starts_at' => $startsAt])->starts_at);
    }

    public function testADateIsAnsweredWithoutItsTime()
    {
        $meeting = $this->meeting(['starts_at' => '2026-11-27 14:30:00', 'held_on' => '2026-11-27']);

        $this->assertEquals('2026-11-27', $meeting->toArray()['held_on']);
        $this->assertEquals((new \DateTimeImmutable('2026-11-27 14:30:00'))->format(DATE_ATOM), $meeting->toArray()['starts_at']);
    }

    /** A resource copying a field gave the database string, not the ATOM the model itself answers. */
    public function testAResourceCopyingDateFieldsAnswersThemFormatted()
    {
        $meeting = $this->meeting(['starts_at' => '2026-11-27 14:30:00', 'held_on' => '2026-11-27']);

        $json = Response::json(['starts' => $meeting->starts_at, 'day' => $meeting->held_on])->getJSON();

        $this->assertEquals((new \DateTimeImmutable('2026-11-27 14:30:00'))->format(DATE_ATOM), $json['starts']);
        $this->assertEquals('2026-11-27', $json['day']);
    }

    public function testAModelReadFromTheDatabaseIsHydrated()
    {
        $database = (new SQLiteProvider())->getEmptyDatabase();
        $database->asGlobalInstance(function () {
            Product::insertArray(['name' => 'Screen', 'created_at' => '2026-11-27 14:30:00']);

            $product = Product::findWhere(['name' => 'Screen']);

            $this->assertSame(\DateTimeImmutable::class, $product->created_at::class);
            $this->assertEquals('2026-11-27 14:30:00', $product->created_at->format('Y-m-d H:i:s'));
        });
    }

    public function testAParsedDateIsImmutable()
    {
        $this->assertSame(\DateTimeImmutable::class, ModelField::datetime('starts_at')->parse('2026-11-27 14:30:00')::class);
        $this->assertSame(\DateTimeImmutable::class, ModelField::timestamp('created_at')->parse('2026-11-27 14:30:00')::class);
        $this->assertInstanceOf(Date::class, ModelField::date('held_on')->parse('2026-11-27'));
    }

    public function testGeneratedModelsDocumentTheHydratedTypes()
    {
        $table = new class('meeting') extends Table {
            public function publicFieldPHPDoc(ModelField $field): string
            {
                return $this->getFieldPHPDoc($field);
            }
        };

        $this->assertStringContainsString('@property \DateTimeImmutable $starts_at', $table->publicFieldPHPDoc(ModelField::datetime('starts_at')));
        $this->assertStringContainsString('@property \DateTimeImmutable $created_at', $table->publicFieldPHPDoc(ModelField::timestamp('created_at')));
        $this->assertStringContainsString('@property \Cube\Data\Date $held_on', $table->publicFieldPHPDoc(ModelField::date('held_on')));
    }

    protected function meeting(array $data = []): Model
    {
        return new class($data) extends Model {
            public static function table(): string
            {
                return 'meeting';
            }

            public static function fields(): array
            {
                return [
                    'title' => ModelField::string('title'),
                    'starts_at' => ModelField::datetime('starts_at'),
                    'held_on' => ModelField::date('held_on'),
                ];
            }

            public static function relations(): array
            {
                return [];
            }
        };
    }
}

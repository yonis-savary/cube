<?php

namespace Cube\Tests\Units\Database;

use Cube\Data\Bunch;
use Cube\Data\Database\Database;
use Cube\Data\Models\ModelField;
use Cube\Data\Models\ModelGenerator\Adapters\SQLite;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ModelFieldTypeMappingTest extends TestCase
{
    /** DATE was tried before DATETIME : every DATETIME column was generated as a DATE field. */
    #[DataProvider('getColumnTypes')]
    public function testAColumnTypeGivesItsFieldType(string $columnType, string $fieldType)
    {
        $adapter = new class(new Database()) extends SQLite {
            public function publicGetModelFieldType(string $sqlType): string
            {
                return $this->getModelFieldType($sqlType);
            }
        };

        $this->assertEquals($fieldType, $adapter->publicGetModelFieldType($columnType));
    }

    public static function getColumnTypes(): array
    {
        return [
            'MySQL datetime' => ['datetime', ModelField::DATETIME],
            'MySQL datetime with precision' => ['datetime(6)', ModelField::DATETIME],
            'date' => ['date', ModelField::DATE],
            'timestamp' => ['timestamp', ModelField::TIMESTAMP],
            'Postgres timestamptz' => ['timestamptz', ModelField::TIMESTAMP],
            'MySQL boolean' => ['tinyint(1)', ModelField::BOOLEAN],
            'MySQL tinyint' => ['tinyint(4)', ModelField::INTEGER],
            'unsigned int' => ['int(11) unsigned', ModelField::INTEGER],
            'Postgres float8' => ['float8', ModelField::FLOAT],
            'double precision' => ['double precision', ModelField::FLOAT],
            'decimal' => ['decimal(10,2)', ModelField::DECIMAL],
            'unknown' => ['json', ModelField::STRING],
        ];
    }

    public function testASqliteDatetimeColumnIsGeneratedAsADatetimeField()
    {
        $database = new Database();
        $database->exec('CREATE TABLE event (id INTEGER PRIMARY KEY AUTOINCREMENT, starts_at DATETIME NOT NULL, day DATE)');

        $adapter = new SQLite($database);
        $adapter->process();
        $types = Bunch::wrap($adapter->getTables()[0]->fields)
            ->zip(fn (ModelField $field) => [$field->name, $field->type])
        ;

        $this->assertEquals(ModelField::DATETIME, $types['starts_at']);
        $this->assertEquals(ModelField::DATE, $types['day']);
    }
}

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
            'Postgres bool' => ['bool', ModelField::BOOLEAN],
            'Postgres interval' => ['interval', ModelField::STRING],
            'Postgres int4range' => ['int4range', ModelField::STRING],
            'Postgres datemultirange' => ['datemultirange', ModelField::STRING],
        ];
    }

    /** The adapter read sqlite_sequence, which SQLite only creates once a table declares AUTOINCREMENT. */
    public function testASqliteDatabaseWithoutAutoIncrementIsGenerated()
    {
        $database = new Database();
        $database->exec('CREATE TABLE tag (id INTEGER PRIMARY KEY, label TEXT)');

        $adapter = new SQLite($database);
        $adapter->process();

        $this->assertEquals('tag', $adapter->getTables()[0]->table);
        $this->assertFalse($adapter->getTables()[0]->fields[0]->autoIncrement);
    }

    /** sqlite_sequence only lists a table after its first insert : a freshly migrated table lost its AUTOINCREMENT. */
    public function testAnEmptySqliteTableKeepsItsAutoIncrement()
    {
        $database = new Database();
        $database->exec('CREATE TABLE tag (id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT)');

        $adapter = new SQLite($database);
        $adapter->process();

        $this->assertTrue($adapter->getTables()[0]->fields[0]->autoIncrement);
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

<?php

namespace Cube\Tests\Units\Database;

use Cube\Data\Bunch;
use Cube\Data\Database\Database;
use Cube\Data\Models\ModelField;
use Cube\Data\Models\ModelGenerator;
use Cube\Data\Models\ModelGenerator\Table;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ModelGenerationTest extends TestCase
{
    use TestMultipleDrivers;

    /**
     * @param Table[] $tables
     */
    public function assertTableGotParsed(array $tables, string $tableName) {
        $matching = Bunch::of($tables)->first(fn($table) => $table->table === $tableName);
        $this->assertInstanceOf(Table::class, $matching);
    }

    /**
     * @param Table[] $tables
     * @param \Closure(Table) $callback
     */
    public function assertForTable(array $tables, string $tableName, callable $callback) {
        $matching = Bunch::of($tables)->first(fn($table) => $table->table === $tableName);
        $this->assertInstanceOf(Table::class, $matching);
        if ($matching)
            $callback($matching);
    }

    /**
     * @param \Closure(ModelField) $callback
     */
    public function assertForField(Table $table, string $fieldName, callable $callback) {
        $matching = Bunch::of($table->fields)->first(fn($field) => $field->name === $fieldName);
        $this->assertInstanceOf(ModelField::class, $matching);
        if ($matching)
            $callback($matching);
    }

    /**
     * @param Table[] $tables
     * @param \Closure(ModelField) $callback
     */
    public function assertForTableField(array $tables, string $tableName, string $fieldName, callable $callback) {
        $this->assertForTable($tables, $tableName, function($table) use ($fieldName, $callback) {
            $this->assertForField($table, $fieldName, $callback);
        });
    }

    #[ DataProvider('getDatabases') ]
    public function testCorrectTableParsing(Database $database) {
        $modelGenerator = ModelGenerator::getInstance()->getAdapter($database);
        $modelGenerator->process();

        $tables = $modelGenerator->getTables();
        $this->assertTableGotParsed($tables, 'user');
        $this->assertTableGotParsed($tables, 'module');
        $this->assertTableGotParsed($tables, 'product_manager');
        $this->assertTableGotParsed($tables, 'addition');

        $this->assertForTableField($tables, 'user', 'id', function($idColumn) {
            $this->assertEquals(ModelField::INTEGER, $idColumn->type);
            $this->assertTrue($idColumn->autoIncrement);
        });

        $this->assertForTableField($tables, 'user', 'type', function($typeColumn) {
            $this->assertEquals(ModelField::INTEGER, $typeColumn->type);
            $this->assertEquals('UserType', $typeColumn->referenceModel);
            $this->assertEquals('id', $typeColumn->referenceField);
        });

        $this->assertForTableField($tables, 'product_manager', 'manager', function($managerColumn) {
            $this->assertEquals(ModelField::STRING, $managerColumn->type);
            $this->assertFalse($managerColumn->nullable);
        });

        $this->assertForTableField($tables, 'addition', 'result', function($resultColumn) {
            $this->assertEquals(ModelField::FLOAT, $resultColumn->type);
            $this->assertTrue($resultColumn->isGenerated());
        });
    }
}
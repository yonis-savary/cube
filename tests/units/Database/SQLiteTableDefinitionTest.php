<?php

namespace Cube\Tests\Units\Database;

use Cube\Data\Database\Migration\Plans\SQLiteTableDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class SQLiteTableDefinitionTest extends TestCase
{
    public function testSplitsTopLevelDefinitionsOnly()
    {
        $definition = SQLiteTableDefinition::fromSQL(<<<'SQL'
            CREATE TABLE "odd (name)" (
                id INTEGER PRIMARY KEY, -- a comment, with (parentheses)
                price DECIMAL(10, 2) DEFAULT 'a, ''b'' (c)',
                "quoted, ""column""" TEXT,
                [bracketed, column] TEXT /* a , block */,
                `backticked, column` TEXT,
                CONSTRAINT positive CHECK (price > 0 AND id IN (1, 2))
            ) WITHOUT ROWID, STRICT
            SQL);

        $this->assertCount(6, $definition->definitions);
        $this->assertEquals('WITHOUT ROWID, STRICT', $definition->options);
        $this->assertStringEndsWith("price DECIMAL(10, 2) DEFAULT 'a, ''b'' (c)'", $definition->definitions[1], 'A comment after a comma belongs to the next definition');
        $this->assertEquals(
            ['id', 'price', 'quoted, "column"', 'bracketed, column', 'backticked, column', null],
            array_map(SQLiteTableDefinition::getColumnName(...), $definition->definitions)
        );
    }

    public function testTellsTableConstraintsFromColumns()
    {
        $this->assertTrue(SQLiteTableDefinition::isTableConstraint('FOREIGN KEY (a) REFERENCES b(id)'));
        $this->assertTrue(SQLiteTableDefinition::isTableConstraint('unique (a, b)'));
        $this->assertFalse(SQLiteTableDefinition::isTableConstraint('"check" TEXT'));
        $this->assertFalse(SQLiteTableDefinition::isTableConstraint('check_date DATE'));
    }

    public function testKeywordsAreNotFoundInStringsOrQuotedNames()
    {
        $this->assertFalse(SQLiteTableDefinition::hasKeyword("label TEXT DEFAULT 'UNIQUE'", 'UNIQUE'));
        $this->assertFalse(SQLiteTableDefinition::hasKeyword('"unique" TEXT', 'UNIQUE'));
        $this->assertTrue(SQLiteTableDefinition::hasKeyword('code TEXT NOT NULL unique', 'UNIQUE'));
        $this->assertTrue(SQLiteTableDefinition::mentions('CREATE INDEX idx ON t("code", other)', 'code'));
        $this->assertFalse(SQLiteTableDefinition::mentions('CREATE INDEX idx ON t(code_bis)', 'code'));
    }

    public function testAddsAColumnBeforeTheTableConstraints()
    {
        $definition = SQLiteTableDefinition::fromSQL('CREATE TABLE t (a INTEGER, b INTEGER, UNIQUE (a, b))');

        $definition->addColumn('c TEXT');

        $this->assertEquals(['a INTEGER', 'b INTEGER', 'c TEXT', 'UNIQUE (a, b)'], $definition->definitions);
    }

    public function testRemovesAColumnWithTheConstraintsNamingIt()
    {
        $definition = SQLiteTableDefinition::fromSQL('CREATE TABLE t (a INTEGER, b INTEGER, c INTEGER, UNIQUE (a, b), CHECK (c > 0))');

        $definition->removeColumn('b');

        $this->assertEquals(['a INTEGER', 'c INTEGER', 'CHECK (c > 0)'], $definition->definitions);
    }

    public function testATrailingLineCommentDoesNotSwallowTheNextComma()
    {
        $definition = new SQLiteTableDefinition(['a INTEGER -- first', 'b INTEGER']);

        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec($definition->toSQL('t'));

        $this->assertCount(2, $pdo->query('PRAGMA table_info(t)')->fetchAll());
    }

    public function testRefusesAStatementWithoutDefinitions()
    {
        $this->expectException(\InvalidArgumentException::class);
        SQLiteTableDefinition::fromSQL('CREATE TABLE t AS SELECT 1');
    }
}

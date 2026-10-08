<?php

namespace Cube\Tests\Units\Database;

use Cube\Data\Database\Database;
use Cube\Tests\Units\Models\Product;
use Cube\Tests\Units\Core\Classes\Habitat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DatabaseTest extends TestCase
{
    use TestMultipleDrivers;

    /**
     * Values a user can legitimately store, and that the `{}` interpolation
     * must give back untouched
     *
     * @return array<string,string>
     */
    protected function getSpecialValues(): array
    {
        return [
            'single quote' => "O'Brien",
            'doubled single quote' => "it''s",
            'double quote' => 'say "hello"',
            'backslash' => 'C:\\Users\\cube',
            'backslash before quote' => "ends with \\'",
            'backtick' => '`product`',
            'percent and underscore' => '100% _sure',
            'placeholder' => 'a {} placeholder',
            'semicolon and comment' => "x'); DROP TABLE product; --",
            'new lines' => "multi\nline\r\nvalue",
            'unicode' => 'éàü 🧊',
        ];
    }

    #[ DataProvider('getDatabases') ]
    public function testBase(Database $database)
    {
        $this->assertNotNull(
            $database->query('SELECT 1')
        );
    }

    #[ DataProvider('getDatabases') ]
    public function testBuildQuotesStrings(Database $database)
    {
        $this->assertSame("SELECT 'screen'", $database->build('SELECT {}', ['screen']));

        // How a quote gets escaped belongs to the driver — MySQL answers 'O\'Brien' where
        // Postgres and SQLite answer 'O''Brien'. What has to hold is that the literal reads
        // back as the value it was built from.
        $this->assertSame(
            "O'Brien",
            $database->query('SELECT {} AS quoted', ["O'Brien"])[0]['quoted']
        );
    }

    #[ DataProvider('getDatabases') ]
    public function testBuildScalarValues(Database $database)
    {
        $this->assertSame('SELECT NULL', $database->build('SELECT {}', [null]));
        $this->assertSame('SELECT TRUE', $database->build('SELECT {}', [true]));
        $this->assertSame('SELECT FALSE', $database->build('SELECT {}', [false]));
    }

    #[ DataProvider('getDatabases') ]
    public function testBuildArrayBecomesAList(Database $database)
    {
        $this->assertSame("SELECT ('a','b')", $database->build('SELECT {}', [['a', 'b']]));
        $this->assertSame("SELECT ('1','2')", $database->build('SELECT {}', [[1, 2]]));

        // Each element is escaped by the driver, so the list only has to match back
        $database->exec('INSERT INTO product (name) VALUES ({})', ["O'Brien"]);

        $this->assertCount(
            1,
            $database->query('SELECT name FROM product WHERE name IN {}', [["O'Brien"]])
        );
    }

    #[ DataProvider('getDatabases') ]
    public function testBuildModelBecomesItsPrimaryKey(Database $database)
    {
        $product = new Product(['id' => 12, 'name' => 'screen']);

        $this->assertSame("SELECT '12'", $database->build('SELECT {}', [$product]));
    }

    #[ DataProvider('getDatabases') ]
    public function testBuildEnumBecomesItsValue(Database $database)
    {
        $this->assertSame("SELECT 'sky'", $database->build('SELECT {}', [Habitat::Sky]));
    }

    #[ DataProvider('getDatabases') ]
    public function testBuildDoesNotQuoteTwiceInsideQuotes(Database $database)
    {
        $this->assertSame(
            "SELECT * FROM product WHERE name LIKE '%scr%'",
            $database->build("SELECT * FROM product WHERE name LIKE '%{}%'", ['scr'])
        );

        // A value needing escaping still lands inside the author's literal, whatever
        // escaping the driver picked for it
        $database->exec('INSERT INTO product (name) VALUES ({})', ["a O'Brien product"]);

        $this->assertCount(
            1,
            $database->query("SELECT name FROM product WHERE name LIKE '%{}%'", ["O'Brien"])
        );
    }

    #[ DataProvider('getDatabases') ]
    public function testBuildFillsPlaceholdersInOrder(Database $database)
    {
        $this->assertSame(
            "SELECT 'a', 'b', 'c'",
            $database->build('SELECT {}, {}, {}', ['a', 'b', 'c'])
        );
    }

    #[ DataProvider('getDatabases') ]
    public function testBuildDoesNotInterpolateInsertedValues(Database $database)
    {
        // A value containing a placeholder must not consume the next context value
        $this->assertSame(
            "SELECT '{}', 'b'",
            $database->build('SELECT {}, {}', ['{}', 'b'])
        );
    }

    #[ DataProvider('getDatabases') ]
    public function testBuildWithMissingContextValue(Database $database)
    {
        // A placeholder without a value is currently interpolated as NULL
        $this->assertSame("SELECT 'a', NULL", $database->build('SELECT {}, {}', ['a']));
    }

    #[ DataProvider('getDatabases') ]
    public function testBuildWithExtraContextValues(Database $database)
    {
        $this->assertSame("SELECT 'a'", $database->build('SELECT {}', ['a', 'unused']));
    }

    #[ DataProvider('getDatabases') ]
    public function testSpecialValuesSurviveARoundTrip(Database $database)
    {
        foreach ($this->getSpecialValues() as $label => $value) {
            $database->exec('INSERT INTO product (name) VALUES ({})', [$value]);

            $rows = $database->query('SELECT name FROM product WHERE name = {}', [$value]);

            $this->assertCount(1, $rows, "The [{$label}] value did not match itself back");
            $this->assertSame($value, $rows[0]['name'], "The [{$label}] value was altered");
        }
    }

    #[ DataProvider('getDatabases') ]
    public function testInjectionAttemptIsStoredAsData(Database $database)
    {
        $payload = "x'); DROP TABLE product; --";

        $database->exec('INSERT INTO product (name) VALUES ({})', [$payload]);

        $this->assertTrue($database->hasTable('product'), 'The product table was dropped by the payload');
        $this->assertSame($payload, $database->query('SELECT name FROM product')[0]['name']);
    }

    #[ DataProvider('getDatabases') ]
    public function testInjectionAttemptInsideQuotedPlaceholder(Database $database)
    {
        $database->exec('INSERT INTO product (name) VALUES ({})', ['screen']);

        $rows = $database->query(
            "SELECT name FROM product WHERE name LIKE '%{}%'",
            ["' OR 1=1 --"]
        );

        $this->assertCount(0, $rows, 'The payload was interpreted instead of being escaped');
    }

    #[ DataProvider('getDatabases') ]
    public function testQueryFetchModes(Database $database)
    {
        $database->exec('INSERT INTO product (name) VALUES ({})', ['screen']);

        $assoc = $database->query('SELECT name FROM product');
        $this->assertSame('screen', $assoc[0]['name']);

        $num = $database->query('SELECT name FROM product', [], \PDO::FETCH_NUM);
        $this->assertSame('screen', $num[0][0]);
    }

    #[ DataProvider('getDatabases') ]
    public function testExecReturnsAffectedRows(Database $database)
    {
        $database->exec('INSERT INTO product (name) VALUES ({})', ['screen']);
        $database->exec('INSERT INTO product (name) VALUES ({})', ['mouse']);

        $this->assertSame(2, $database->exec('DELETE FROM product WHERE name IN {}', [['screen', 'mouse']]));
    }

    #[ DataProvider('getDatabases') ]
    public function testLastInsertId(Database $database)
    {
        $database->exec('INSERT INTO product (name) VALUES ({})', ['screen']);

        // Read it right away : on MySQL any following statement resets it
        $lastInsertId = $database->lastInsertId();

        $id = $database->query('SELECT id FROM product WHERE name = {}', ['screen'])[0]['id'];

        $this->assertEquals($id, $lastInsertId);
    }

    #[ DataProvider('getDatabases') ]
    public function testTransactionCommits(Database $database)
    {
        $error = $database->transaction(function (Database $database) {
            $database->exec('INSERT INTO product (name) VALUES ({})', ['screen']);
        });

        $this->assertNull($error);
        $this->assertCount(1, $database->query('SELECT name FROM product'));
    }

    #[ DataProvider('getDatabases') ]
    public function testTransactionRollsBackAndReturnsTheThrowable(Database $database)
    {
        $error = $database->transaction(function (Database $database) {
            $database->exec('INSERT INTO product (name) VALUES ({})', ['screen']);

            throw new RuntimeException('Something went wrong');
        });

        $this->assertInstanceOf(RuntimeException::class, $error);
        $this->assertCount(0, $database->query('SELECT name FROM product'));
    }

    #[ DataProvider('getDatabases') ]
    public function testDryRunBuildsWithoutExecuting(Database $database)
    {
        $return = $database->dryRun(function () use ($database) {
            $this->assertSame([], $database->query('SELECT name FROM product'));
            $this->assertSame(0, $database->exec('INSERT INTO product (name) VALUES ({})', ['screen']));

            return 'done';
        });

        $this->assertSame('done', $return);
        $this->assertCount(0, $database->query('SELECT name FROM product'));
    }

    #[ DataProvider('getDatabases') ]
    public function testTableAndFieldIntrospection(Database $database)
    {
        $this->assertTrue($database->hasTable('product'));
        $this->assertFalse($database->hasTable('not_a_table'));
        $this->assertTrue($database->missingTable('not_a_table'));

        $this->assertTrue($database->hasField('product', 'name'));
        $this->assertFalse($database->hasField('product', 'not_a_field'));
    }

    #[ DataProvider('getDatabases') ]
    public function testCraftedTableNameHasNoSideEffect(Database $database)
    {
        $database->hasTable('product`; DROP TABLE product; --');
        $database->hasField('product`; DROP TABLE product; --', 'name');

        $this->assertTrue($database->hasTable('product'), 'The product table was dropped through an identifier');
    }

    #[ DataProvider('getDatabases') ]
    public function testConnectionAccessors(Database $database)
    {
        $this->assertTrue($database->isConnected());
        $this->assertInstanceOf(\PDO::class, $database->getConnection());
        $this->assertContains($database->getDriver(), ['mysql', 'pgsql', 'sqlite']);
    }

    public function testSqliteWithoutAFileIsHeldInMemory()
    {
        $database = new Database('sqlite');

        $this->assertNull($database->getDatabase());

        $database->exec('CREATE TABLE in_memory (id INTEGER)');
        $this->assertTrue($database->hasTable('in_memory'));
    }

    /**
     * A callback throwing used to leave the connection in dry run, where every later query
     * silently does nothing while reporting success.
     */
    #[ DataProvider('getDatabases') ]
    public function testDryRunIsLeftBehindEvenWhenTheCallbackThrows(Database $database)
    {
        try {
            $database->dryRun(fn () => throw new RuntimeException('failed halfway'));
        } catch (RuntimeException) {
        }

        $database->exec('CREATE TABLE after_dry_run (id INTEGER)');

        $this->assertTrue($database->hasTable('after_dry_run'));
    }

    #[ DataProvider('getDatabases') ]
    public function testDryRunSwallowsWritesWhileItLasts(Database $database)
    {
        $database->dryRun(fn () => $database->exec('CREATE TABLE never_created (id INTEGER)'));

        $this->assertFalse($database->hasTable('never_created'));
    }

    /**
     * A `{}` written inside an identifier quote used to get the string escaping of the driver,
     * which never doubles a backtick nor a double quote : the value closed the identifier.
     */
    #[ DataProvider('getDatabases') ]
    public function testIdentifierPlaceholderCannotBeClosedByItsValue(Database $database)
    {
        $this->assertFalse($database->hasTable('product` -- '));
        $this->assertFalse($database->hasTable('product" -- '));
        $this->assertFalse($database->hasField('product', 'name` FROM product -- '));
        $this->assertFalse($database->hasField('product', 'name" FROM product -- '));
    }
}

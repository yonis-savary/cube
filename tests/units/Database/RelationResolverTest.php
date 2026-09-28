<?php

namespace Cube\Tests\Units\Database;

use Cube\Data\Database\Database;
use Cube\Tests\Units\Models\Module;
use Cube\Tests\Units\Models\ModuleUser;
use Cube\Tests\Units\Models\User;
use Cube\Tests\Units\Models\UserType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class RelationResolverTest extends TestCase
{
    use TestMultipleDrivers;

    /**
     * The dump already holds `root` (type 1) with the module 4, this adds :
     * - `john` (type 2) with the modules 1 and 2
     * - `jane` (type 2) without any module.
     */
    protected function insertUsers(): void
    {
        $john = User::insertArray(['login' => 'john', 'password' => 'secret', 'type' => 2]);
        User::insertArray(['login' => 'jane', 'password' => 'secret', 'type' => 2]);

        ModuleUser::insertArray(['user' => $john->id, 'module' => 1]);
        ModuleUser::insertArray(['user' => $john->id, 'module' => 2]);
    }

    /**
     * @param User[] $users
     *
     * @return array<string,int[]>
     */
    protected function modulesByLogin(array $users): array
    {
        $modules = [];
        foreach ($users as $user) {
            $moduleIds = array_map(fn (ModuleUser $moduleUser) => $moduleUser->module, $user->modules);
            sort($moduleIds);
            $modules[$user->login] = $moduleIds;
        }

        return $modules;
    }

    protected function countingDatabase(Database $database): Database
    {
        return new class(connection: $database->getConnection()) extends Database {
            public int $queryCount = 0;

            public function query(string $query, array $context = [], int $fetchMode = \PDO::FETCH_ASSOC): array
            {
                ++$this->queryCount;

                return parent::query($query, $context, $fetchMode);
            }
        };
    }

    #[DataProvider('getDatabases')]
    public function testHasManyIsLoadedOnEveryRow(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertUsers();

            $users = User::select(['modules'])->order('id', 'ASC')->fetch();

            $this->assertEquals([
                'root' => [4],
                'john' => [1, 2],
                'jane' => [],
            ], $this->modulesByLogin($users));

            $this->assertContainsOnlyInstancesOf(ModuleUser::class, $users[1]->modules);
        });
    }

    #[DataProvider('getDatabases')]
    public function testRowWithoutRelatedRowsGetsAnEmptyArray(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertUsers();

            $jane = User::select(['modules'])->where('login', 'jane')->first();

            $this->assertSame([], $jane->modules);
        });
    }

    #[DataProvider('getDatabases')]
    public function testEmptyResultDoesNotFail(Database $database)
    {
        $database->asGlobalInstance(function () {
            $users = User::select(['modules'])->where('login', 'nobody')->fetch();

            $this->assertSame([], $users);
        });
    }

    #[DataProvider('getDatabases')]
    public function testOneQueryPerHasManyRelation(Database $database)
    {
        $counting = $this->countingDatabase($database);

        Database::withInstance($counting, function () use ($counting) {
            $this->insertUsers();
            $counting->queryCount = 0;

            User::select(['modules'])->fetch();
            $this->assertEquals(2, $counting->queryCount);

            $counting->queryCount = 0;
            UserType::select(['users.modules'])->fetch();
            $this->assertEquals(3, $counting->queryCount);
        });
    }

    #[DataProvider('getDatabases')]
    public function testHasOneInsideHasManyIsJoined(Database $database)
    {
        $counting = $this->countingDatabase($database);

        Database::withInstance($counting, function () use ($counting) {
            $this->insertUsers();
            $counting->queryCount = 0;

            $john = User::select(['modules._module'])->where('login', 'john')->first();

            $this->assertEquals(2, $counting->queryCount);

            $labels = array_map(fn (ModuleUser $moduleUser) => $moduleUser->_module->label, $john->modules);
            sort($labels);

            $this->assertContainsOnlyInstancesOf(Module::class, array_map(fn (ModuleUser $moduleUser) => $moduleUser->_module, $john->modules));
            $this->assertEquals(['order', 'product'], $labels);
        });
    }

    #[DataProvider('getDatabases')]
    public function testHasManyInsideHasMany(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertUsers();

            $types = UserType::select(['users.modules'])->order('id', 'ASC')->fetch();

            $this->assertEquals(['root' => [4]], $this->modulesByLogin($types[0]->users));
            $this->assertEquals(['john' => [1, 2], 'jane' => []], $this->modulesByLogin($types[1]->users));
            $this->assertSame([], $types[2]->users);
        });
    }

    #[DataProvider('getDatabases')]
    public function testHasManyInsideHasOne(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertUsers();

            $moduleUsers = ModuleUser::select(['_user.modules'])->where('module', 1)->fetch();

            $this->assertCount(1, $moduleUsers);
            $this->assertEquals(['john' => [1, 2]], $this->modulesByLogin([$moduleUsers[0]->_user]));
        });
    }

    #[DataProvider('getDatabases')]
    public function testSameQueryCanBeFetchedTwice(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertUsers();

            $query = User::select(['modules'])->order('id', 'ASC');

            $expected = ['root' => [4], 'john' => [1, 2], 'jane' => []];
            $this->assertEquals($expected, $this->modulesByLogin($query->fetch()));
            $this->assertEquals($expected, $this->modulesByLogin($query->fetch()));
        });
    }

    #[DataProvider('getDatabases')]
    public function testChunkLoadsTheRelationOfEveryChunk(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->insertUsers();

            $modules = [];
            User::select(['modules'])
                ->order('id', 'ASC')
                ->chunk(1, function (array $users) use (&$modules) {
                    $modules += $this->modulesByLogin($users);
                });

            $this->assertEquals(['root' => [4], 'john' => [1, 2], 'jane' => []], $modules);
        });
    }

    /**
     * The database given to fetch() must be the one read by the resolver, not the global instance.
     */
    #[DataProvider('getDatabases')]
    public function testResolverUsesTheGivenDatabase(Database $database)
    {
        $database->asGlobalInstance(fn () => $this->insertUsers());

        $users = User::select(['modules'])->order('id', 'ASC')->fetch($database);

        $this->assertEquals([
            'root' => [4],
            'john' => [1, 2],
            'jane' => [],
        ], $this->modulesByLogin($users));
    }
}

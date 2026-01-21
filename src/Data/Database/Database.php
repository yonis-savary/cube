<?php

namespace Cube\Data\Database;

use Cube\Core\Component;
use Cube\Data\Bunch;
use Cube\Data\Database\Builders\QueryBuilder;
use Cube\Env\Storage;
use PDO;
use Throwable;

class Database
{
    use Component;

    protected \PDO $connection;
    protected \PDOStatement $lastStatement;

    public function __construct(
        protected string $driver = 'sqlite',
        protected ?string $database = null,
        protected ?string $host = null,
        protected ?int $port = null,
        protected ?string $user = null,
        protected ?string $password = null,
        protected ?QueryBuilder $queryBuilder = null,
        ?\PDO $connection = null
    ) {
        if ($connection) {
            $this->driver = $connection->getAttribute(\PDO::ATTR_DRIVER_NAME);
            $this->connection = $connection;
        }
        else if ('sqlite' === $driver) {
            $dsn = $database
                ? 'sqlite:'.Storage::getInstance()->path($database)
                : 'sqlite::memory:';

            $this->connection = new \PDO($dsn);
            $this->exec('PRAGMA foreign_keys = ON');
        }
        else {
            $dsn = "{$driver}:dbname={$database};host={$host};port={$port}";
            $this->connection = new \PDO($dsn, $user, $password);
        }

        if (!$this->queryBuilder) {
            $driver = $this->getDriver();

            $this->queryBuilder = Bunch::fromExtends(QueryBuilder::class)
                ->first(fn($builder) => $builder->supports($driver));

            if (!$this->queryBuilder) {
                throw new \InvalidArgumentException("Could not find a query builder that supports [{$driver}] database");
            }
        }
    }

    public static function getDefaultInstance(): static
    {
        $config = DatabaseConfiguration::resolve();

        return new self(
            $config->driver,
            $config->database,
            $config->host,
            $config->port,
            $config->user,
            $config->password,
        );
    }

    public static function fromPDO(\PDO $connection, ?string $database=null): self
    {
        return new self(database: $database, connection: $connection);
    }

    /**
     * @return ?\PDO The current connection to the database (`null` if not connected)
     */
    public function getConnection(): ?\PDO
    {
        return $this->connection;
    }

    public function getLastStatement(): \PDOStatement
    {
        return $this->lastStatement;
    }

    public function isConnected(): bool
    {
        return null !== $this->connection;
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }

    /**
     * @return string Get used PDO driver (trimmed & lowercase)
     */
    public function getDriver(): string
    {
        return $this->driver;
    }

    /**
     * @return ?string Return used database name (can be `null` for a in-memory database)
     */
    public function getDatabase(): ?string
    {
        return $this->database;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function getPort(): ?string
    {
        return $this->port;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }

    /**
     * @return int The last inserted Id by the connection (if any, `false` otherwise)
     */
    public function lastInsertId(): false|int
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Build a query by replacing placeholders (`{}`) with `$context` values.
     *
     * @param string $sql     Query to complete
     * @param array  $context Placeholders-replacing values
     *
     * @example NULL `build('UPDATE ... SET name = {}', ['Dale']) // UPDATE ... SET name = 'Dale'`
     */
    public function build(string $sql, array $context = []): string
    {
        $queryClone = $sql;

        $matchesQuoted = [];

        // This regex capture quoted content
        preg_match_all('/([\'"`])(?:.*?(?:\1\1|\\\1)?)+?\1/', $sql, $matchesQuoted, PREG_OFFSET_CAPTURE);

        $quotedPositions = [];
        foreach ($matchesQuoted[0] as $m) {
            $offset = 0;
            while (($pos = strpos($m[0], '{}', $offset)) !== false) {
                $quotedPositions[] = $m[1] + $pos;
                $offset = $pos + 1;
            }
        }

        $count = 0;

        return preg_replace_callback(
            '/\{\}/',
            function ($match) use (&$count, $quotedPositions, $context) {
                $doQuote = !in_array($match[0][1], $quotedPositions);
                $val = $this->queryBuilder->prepareString($context[$count] ?? null, $doQuote);
                ++$count;

                return $val;
            },
            $queryClone,
            flags: PREG_OFFSET_CAPTURE
        );
    }

    /**
     * Perform a query with the database.
     *
     * @param string $query     SQL Query to execute
     * @param array  $context   Data for the query (values replaces placeholders `{}`)
     * @param int    $fetchMode PDO Fetch mode constant
     */
    public function query(string $query, array $context = [], int $fetchMode = \PDO::FETCH_ASSOC): array
    {
        $queryWithContext = $this->build($query, $context);

        $statement = $this->connection->query($queryWithContext);
        $this->lastStatement = $statement;

        $results = $statement->fetchAll($fetchMode);
        $statement->closeCursor();

        return $results;
    }

    /**
     * Perform a query in the database and return the number of affected rows
     * Cannot be used with queries that return a result like SELECT.
     *
     * @param string $query   SQL Query to execute
     * @param array  $context Data for the query (values replaces placeholders `{}`)
     */
    public function exec(string $query, array $context = []): int
    {
        $queryWithContext = $this->build($query, $context);

        return $this->connection->exec($queryWithContext);
    }

    /**
     * @return `true` if the given table exists in the database, `false` otherwise
     */
    public function hasTable(string $table): bool
    {
        return $this->queryBuilder->hasTable($table, $this);
    }

    public function missingTable(string $table): bool
    {
        return !$this->hasTable($table);
    }

    /**
     * @return `true` if both the given table AND field exists in the database, `false` otherwise
     */
    public function hasField(string $table, string $field): bool
    {
        return $this->queryBuilder->hasField($table, $field, $this);
    }

    /**
     * @param \Closure(Database) $callback
     */
    public function transaction(callable $callback): true|Throwable
    {
        return $this->queryBuilder->transaction($callback, $this);
    }
}

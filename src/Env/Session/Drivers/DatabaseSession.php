<?php

namespace Cube\Env\Session\Drivers;

use Cube\Data\Database\Database;
use Cube\Data\Database\Migration\Plan;
use Cube\Data\Database\Query;
use Cube\Data\Models\ModelField;
use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

class DatabaseSession extends PHPGlobalSession implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    /** @var array<string,true> */
    protected array $readSessions = [];

    public function __construct(
        public readonly string $table = '__cube_session',
        string $sameSite = 'Lax',
        protected ?Database $database = null,
        ?bool $secureCookie = null,
    ) {
        parent::__construct($sameSite, $secureCookie);
    }

    public function getDatabase(): Database
    {
        return $this->database ?? Database::getInstance();
    }

    protected function start(): void
    {
        session_set_save_handler($this, true);

        parent::start();
    }

    public function createTableIfMissing(): void
    {
        $database = $this->getDatabase();
        if ($database->hasTable($this->table))
            return;

        Plan::forDatabase($database)->create($this->table, [
            ModelField::string('id', 128)->primaryKey(),
            ModelField::string('payload')->notNull(),
            ModelField::integer('updated_at')->notNull(),
        ]);
    }

    public function open(string $path, string $name): bool
    {
        $this->createTableIfMissing();

        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $query = Query::select($this->table)->selectField('payload');
        $row = $this->whereAlive($query, $id)->first($this->getDatabase());

        if (!$row)
            return '';

        $this->readSessions[$id] = true;

        return base64_decode($row->payload);
    }

    // The garbage collector may never run (session.gc_probability=0 on Debian) : an expired session must be refused on its own
    protected function whereAlive(Query $query, string $id): Query
    {
        return $query
            ->where('id', $id)
            ->where('updated_at', time() - (int) ini_get('session.gc_maxlifetime'), '>=');
    }

    public function write(string $id, string $data): bool
    {
        $payload = base64_encode($data);

        if ($this->validateId($id)) {
            Query::update($this->table)
                ->set('payload', $payload)
                ->set('updated_at', time())
                ->where('id', $id)
                ->fetch($this->getDatabase());

            return true;
        }

        // Destroyed while this request ran (a logout elsewhere) : writing it back would revive it
        if (isset($this->readSessions[$id]))
            return true;

        $this->destroy($id);

        Query::insert($this->table)
            ->insertField(['id', 'payload', 'updated_at'])
            ->values([$id, $payload, time()])
            ->fetch($this->getDatabase());

        return true;
    }

    public function destroy(string $id): bool
    {
        Query::delete($this->table)
            ->where('id', $id)
            ->fetch($this->getDatabase());

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $expired = fn(Query $query) => $query->where('updated_at', time() - $max_lifetime, '<');

        $database = $this->getDatabase();

        $count = $expired(Query::select($this->table)->selectField('id'))->count($database);
        $expired(Query::delete($this->table))->fetch($database);

        return $count;
    }

    public function validateId(string $id): bool
    {
        return $this->whereAlive(Query::select($this->table)->selectField('id'), $id)
            ->exists($this->getDatabase());
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        Query::update($this->table)
            ->set('updated_at', time())
            ->where('id', $id)
            ->fetch($this->getDatabase());

        return true;
    }
}

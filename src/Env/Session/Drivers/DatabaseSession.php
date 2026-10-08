<?php

namespace Cube\Env\Session\Drivers;

use Cube\Utils\Implementations;
use Cube\Data\Database\Database;
use Cube\Data\Database\Migration\Plan;
use Cube\Data\Database\Query;
use Cube\Data\Models\ModelField;
use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

class DatabaseSession extends PHPGlobalSession implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    protected Database $database;

    public function __construct(
        public readonly string $table = '__cube_session',
        string $sameSite = 'Lax',
        ?Database $database = null
    ) {
        parent::__construct($sameSite);
        $this->database = $database ?? Database::getInstance();
    }

    protected function start(): void
    {
        session_set_save_handler($this, true);

        parent::start();
    }

    public function createTableIfMissing(): void
    {
        if ($this->database->hasTable($this->table))
            return;

        $driver = $this->database->getDriver();
        $plan = Implementations::findOrFail(
            Plan::class,
            fn ($plan) => $plan::supports($driver),
            [$this->database],
            $driver
        );

        $plan->create($this->table, [
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
        $row = Query::select($this->table)
            ->selectField('payload')
            ->where('id', $id)
            ->first();

        return $row
            ? base64_decode($row->payload)
            : '';
    }

    public function write(string $id, string $data): bool
    {
        $payload = base64_encode($data);

        if ($this->validateId($id)) {
            Query::update($this->table)
                ->set('payload', $payload)
                ->set('updated_at', time())
                ->where('id', $id)
                ->fetch();

            return true;
        }

        Query::insert($this->table)
            ->insertField(['id', 'payload', 'updated_at'])
            ->values([$id, $payload, time()])
            ->fetch();

        return true;
    }

    public function destroy(string $id): bool
    {
        Query::delete($this->table)
            ->where('id', $id)
            ->fetch();

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $expired = fn(Query $query) => $query->where('updated_at', time() - $max_lifetime, '<');

        $count = $expired(Query::select($this->table)->selectField('id'))->count();
        $expired(Query::delete($this->table))->fetch();

        return $count;
    }

    public function validateId(string $id): bool
    {
        return Query::select($this->table)
            ->selectField('id')
            ->where('id', $id)
            ->exists();
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        Query::update($this->table)
            ->set('updated_at', time())
            ->where('id', $id)
            ->fetch();

        return true;
    }
}

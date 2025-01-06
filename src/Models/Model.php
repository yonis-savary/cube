<?php

namespace YonisSavary\Cube\Models;

use InvalidArgumentException;
use YonisSavary\Cube\Database\Database;
use YonisSavary\Cube\Database\Query;

abstract class Model
{
    public array $data = [];
    protected array $references = [];

    abstract public static function table(): string;

    public static function primaryKey(): ?string
    {
        return null;
    }

    public static function select(): Query
    {
        /** @var self $self */
        $self = get_called_class();

        return Query::select($self::table());
    }

    public static function update(): Query
    {
        /** @var self $self */
        $self = get_called_class();

        return Query::update($self::table());
    }

    public static function insert(): Query
    {
        /** @var self $self */
        $self = get_called_class();

        return Query::insert($self::table());
    }

    public static function insertArray(array $data, Database $database=null): mixed
    {
        $database ??= Database::getInstance();
        $keys = array_keys($data);
        $values = array_values($data);

        /** @var self $self */
        $self = get_called_class();
        $self::insert()->insertField($keys)->values($values)->fetch($database);

        return $database->lastInsertId();
    }

    public static function existsWhere(array $conditions, Database $database=null): bool
    {
        $database ??= Database::getInstance();
        /** @var self $self */
        $self = get_called_class();

        return $self::findWhere($conditions, false, $database) !== null;
    }

    public static function exists(mixed $primaryKeyValue, Database $database=null): bool
    {
        $database ??= Database::getInstance();
        /** @var self $self */
        $self = get_called_class();

        return $self::existsWhere([$self::primaryKey() => $primaryKeyValue], $database);
    }

    /**
     * @return ?static
     */
    public static function findWhere(array $conditions, bool $explore=true, Database $database=null): ?self
    {
        $database ??= Database::getInstance();
        /** @var self $self */
        $self = get_called_class();

        $query = $self::select($explore);
        foreach ($conditions as $column => $value)
            $query->where($column, $value, "=", $self::table());

        $query->limit(1);
        return $query->fetch($database)[0] ?? null;
    }

    /**
     * @return ?static
     */
    public static function find(mixed $primaryKeyValue, bool $explore=true, Database $database=null): ?self
    {
        $database ??= Database::getInstance();
        /** @var self $self */
        $self = get_called_class();

        return $self::findWhere([$self::primaryKey() => $primaryKeyValue], $explore, $database);
    }

    public static function findOrCreate(array $data, bool $explore=true, Database $database=null): mixed
    {
        $database ??= Database::getInstance();
        return self::findWhere($data, $explore, $database) ?? self::insertArray($data, $database);
    }

    public static function delete(): Query
    {
        /** @var self $self */
        $self = get_called_class();

        return Query::delete($self::table());
    }


    public function __construct(array $data=[])
    {
        $this->data ??= $data;
    }

    public function &getReference(string $referenceName, string $class): Model
    {
        $this->references[$referenceName] ??= new $class;
        return $this->references[$referenceName];
    }

    public function &__get(string $name): Model
    {
        if (!isset($this->references[$name]))
            throw new InvalidArgumentException("Model " . get_called_class() . " do not have a reference to $name");

        return $this->references[$name];
    }

    public function toArray(): array
    {
        $array = ["data" => $this->data];

        /** @var Model $model */
        foreach ($this->references as $key => $model)
            $array[$key] = $model->toArray();

        return $array;
    }
}
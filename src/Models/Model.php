<?php

namespace Cube\Models;

use InvalidArgumentException;
use RuntimeException;
use stdClass;
use Cube\Core\Autoloader;
use Cube\Data\Bunch;
use Cube\Database\Database;
use Cube\Database\Query;
use Cube\Event\EventDispatcher;
use Cube\Http\Request;
use Cube\Http\Rules\Param;
use Cube\Http\Rules\Validator;
use Cube\Models\Events\SavedModel;
use Cube\Models\Relations\HasMany;
use Cube\Models\Relations\HasOne;
use Cube\Models\Relations\Relation;
use Exception;

use function Cube\debug;

abstract class Model extends EventDispatcher
{
    public object $data;
    public array $references = [];

    abstract public static function table(): string;

    /** @return ModelField[] */
    abstract public static function fields(): array;

    /** @return string[] */
    abstract public static function relations(): array;

    public static function primaryKey(): ?string
    {
        return null;
    }

    public function id(): mixed
    {
        /** @var class-string<static> $self */
        $self = get_called_class();

        $primary = $self::primaryKey();

        return $this->$primary ?? false;
    }

    public static function hasField(string $field): bool
    {
        /** @var class-string<static> $self */
        $self = get_called_class();

        return array_key_exists($field, $self::fields());
    }

    /**
     * @return Query<static>
     */
    public static function select(bool $withRelations=true): Query
    {
         /** @var class-string<static> $self */
        $self = get_called_class();

        $table = $self::table();
        $query = Query::select($table)->withBaseModel($self);
        if ($withRelations)
        {
            $query->exploreModel($self, $table);
        }
        else
        {
            foreach ($self::fields() as $field)
                $query->selectField($field->name, $table, null, $self);
        }

        return $query;
    }

    /**
     * @return Query<static>
     */
    public static function update(): Query
    {
         /** @var class-string<static> $self */
        $self = get_called_class();

        return Query::update($self::table())->withBaseModel($self);
    }


    public static function updateRow(mixed $id, array $newData): self
    {
        /** @var class-string<static> $self */
        $self = get_called_class();

        if (!$self::primaryKey())
            throw new RuntimeException("cannot call updateRow static function without a primary key"); // TODO Add a custom exception for needed primary key

        $query = $self::update()->where($self::primaryKey(), $id);

        foreach ($newData as $column => $value)
            $query->set($column, $value);

        $query->fetch();
        return $self::find($id);
    }

    /**
     * @return Query<static>
     */
    public static function insert(): Query
    {
         /** @var class-string<static> $self */
        $self = get_called_class();

        return Query::insert($self::table())->withBaseModel($self);
    }

    public static function last(?Database $database=null): self
    {
        /** @var class-string<static> $self */
        $self = get_called_class();
        $database ??= Database::getInstance();

        if (! $primary = $self::primaryKey())
            throw new Exception("Use of last() method without primary key is not supported");

        return $self::select()
            ->order($primary, 'DESC')
            ->first($database);
    }

    public static function insertArray(array $data, ?Database $database=null): self
    {
        $database ??= Database::getInstance();

         /** @var class-string<Model> $self */
        $self = get_called_class();

        $validator = $self::toValidator();
        if (true !== ($errors = $validator->validateArray($data)))
            throw new InvalidArgumentException("Given data does not match mode validator : ".  print_r($errors, true));

        $instance = new $self($data);
        $instance->save($database);

        return $instance;
    }

    public static function existsWhere(array $conditions, ?Database $database=null): bool
    {
        $database ??= Database::getInstance();
         /** @var class-string<static> $self */
        $self = get_called_class();

        return $self::findWhere($conditions, false, $database) !== null;
    }

    public static function exists(mixed $primaryKeyValue, ?Database $database=null): bool
    {
        $database ??= Database::getInstance();
         /** @var class-string<static> $self */
        $self = get_called_class();

        if (! $primaryKey = $self::primaryKey())
            throw new RuntimeException("$self model does not have a primary key, cannot use the exists method");

        return $self::existsWhere([$primaryKey => $primaryKeyValue], $database);
    }

    /**
     * @return ?static
     */
    public static function findWhere(array $conditions, bool $explore=true, ?Database $database=null): ?self
    {
        $database ??= Database::getInstance();
         /** @var class-string<static> $self */
        $self = get_called_class();

        $query = $self::select($explore)->withBaseModel($self);
        foreach ($conditions as $column => $value)
            $query->where($column, $value, "=", $self::table());

        $query->limit(1);

        return $query->fetch($database)[0] ?? null;
    }


    public static function toValidator(): Validator
    {
        /** @var static $self */
        $self = get_called_class();
        $instance = new $self();

        $rules = $self::fields();

        foreach ($rules as &$field)
            $field = $field->toRule();

        foreach ($self::relations() as $relationName)
        {
            /** @var Relation $relation */
            $relation = $instance->$relationName();

            if ($relation instanceof HasMany)
            {
                $toModel = $relation->toModel;
                $rules[$relationName] = Param::array($toModel::toValidator());
            }
        }

        return Validator::from($rules);
    }

    /**
     * @return ?static
     */
    public static function find(mixed $primaryKeyValue, bool $explore=true, ?Database $database=null): ?self
    {
        $database ??= Database::getInstance();
         /** @var class-string<static> $self */
        $self = get_called_class();

        if (! $primaryKey = $self::primaryKey())
            throw new RuntimeException("$self model does not have a primary key, cannot use the exists method");

        return $self::findWhere([$primaryKey => $primaryKeyValue], $explore, $database);
    }

    public static function findOrCreate(array $data, bool $explore=true, ?Database $database=null): mixed
    {
        $database ??= Database::getInstance();
        return self::findWhere($data, $explore, $database) ?? self::insertArray($data, $database);
    }

    /**
     * @return Query<static>
     */
    public static function delete(): Query
    {
         /** @var class-string<static> $self */
        $self = get_called_class();

        return Query::delete($self::table())->withBaseModel($self);
    }

    public static function deleteId(mixed $id): ?static
    {
        /** @var class-string<static> $self */
       $self = get_called_class();

        if (! $primaryKey = $self::primaryKey())
            throw new InvalidArgumentException("Cannot call deleteId on a model without a primary key");

        if ($toDelete = $self::find($id))
            $toDelete->destroy();

        return $toDelete;
    }

    /**
     * @return self[]
     */
    public static function deleteWhere(array $conditions, ?Database $database=null): array
    {
        /** @var class-string<static> $self */
        $self = get_called_class();
        $select = $self::select();
        $delete = $self::delete();

        foreach ($conditions as $field => $value)
        {
            $select->where($field, $value);
            $delete->where($field, $value);
        }

        $deleted = $select->fetch($database);
        $delete->fetch($database);

        return $deleted;
    }

    public static function fromArray(Array $array): static
    {
        /** @var self $self */
        $self = get_called_class();

        $validator = $self::toValidator();
        $validator->validateArray($array);

        $validated = $validator->getLastValues();

        return new $self($validated);
    }

    public static function fromRequest(Request $request): static
    {
        /** @var self $self */
        $self = get_called_class();

        $validator = $self::toValidator();
        $error = $validator->validateRequest($request);

        debug($error);

        $validated = $validator->getLastValues();

        return new $self($validated);
    }


    public function __construct(array $data=[])
    {
        $fields = $this->fields();

        $modelData = [];

        foreach ($fields as $key => $field)
        {
            if ($field->autoIncrement)
                continue;

            if (array_key_exists($key, $fields) && isset($data[$key]))
                $modelData[$key] = $data[$key];
        }

        $this->data = empty($modelData) ? new stdClass : (object) $modelData;
        $this->completeModelDataWithRelations($data);
    }


    public function completeModelDataWithRelations(array $constructData)
    {
        /** @var self $self */
        $self = get_called_class();

        foreach ($self::relations() as $relationName)
        {
            /** @var Relation $relation */
            $relation = $this->$relationName();
            $relationKey = $relation->getName();

            if ($relation instanceof HasOne)
            {
                $relationModel = $relation->toModel;

                if ($data = $constructData[$relation->fromColumn] ?? false)
                {
                    $oneModel = new $relationModel($data);
                    $relation->bind($oneModel);
                }
            }
            else if ($relation instanceof HasMany)
            {
                $relationModel = $relation->toModel;
                if ($data = $constructData[$relationKey] ?? false)
                {
                    foreach ($data as $row)
                    {
                        $manyModel = new $relationModel($row);
                        $relation->bind($manyModel);
                    }
                }
            }
        }
    }

    /**
     * @template X
     * @param null|string|X $class
     * @return X
     */
    public function &getReference(string $referenceName, ?string $class=null): Model
    {
        $class ??= DummyModel::class;
        if (!Autoloader::extends($class, Model::class))
            throw new InvalidArgumentException("\$model must extends Model");

        $this->references[$referenceName] ??= new $class;
        return $this->references[$referenceName];
    }

    /**
     * @return static
     */
    public function setReference(string $referenceName, Model|array $model): self
    {
        $this->references[$referenceName] = $model;
        return $this;
    }

    /**
     * @return static
     */
    public function pushReference(string $referenceName, Model $model): self
    {
        $this->references[$referenceName] ??= [];
        $this->references[$referenceName][] = $model;

        return $this;
    }

    public function &__get(string $name): mixed
    {
        if (isset($this->references[$name]))
            return $this->references[$name];

         /** @var class-string<static> $self */
        $self = get_called_class();

        if ($self::hasField($name))
            return $this->data->$name;

        throw new RuntimeException("Either $self does not have a $name attribute, or a relation needs to be loaded");
    }

    public function __set($name, $value)
    {
         /** @var class-string<static> $self */
        $self = get_called_class();

        if ($self::hasField($name))
            $this->data->$name = $value;
    }

    public function toArray(): array
    {
        $array = (array) $this->data;

        /** @var Model $model */
        foreach ($this->references as $key => $modelOrCollection)
        {
            if (is_array($modelOrCollection))
                $array[$key] = Bunch::of($modelOrCollection)->map(fn(Model $model) => $model->toArray())->toArray();
            else if ($modelOrCollection instanceof Model)
                $array[$key] = $modelOrCollection->toArray();
        }

        return $array;
    }


    /**
     * @return HasOne<static>
     */
    public function hasOne(string $fromColumn, string $toModel, string $toColumn): HasOne
    {
        return new HasOne($this::class, $fromColumn, $toModel, $toColumn, $this);
    }


    /**
     * @return HasMany<static>
     */
    public function hasMany(string $toModel, string $toColumn, string $fromColumn): HasMany
    {
        return new HasMany($this::class, $fromColumn, $toModel, $toColumn, $this);
    }


    public function onSaved(callable $callback)
    {
        $this->on(SavedModel::class, $callback);
    }

    protected function existsInDatabase(): bool
    {
        $primaryKey = $this->primaryKey();

        return $primaryKey && $this->$primaryKey;
    }

    public function save(?Database $database=null)
    {
        if ($this->existsInDatabase())
            $this->saveExisting($database);
        else
            $this->saveNew($database);
    }

    protected function saveExisting(?Database $database=null)
    {
        $primaryKey = $this->primaryKey();

        /** @var self $self */
        $self = get_called_class();
        $query = $self::update()->where($primaryKey, $this->$primaryKey);

        foreach ($this->data as $key => $value)
            $query->set($key, $value);

        $query->fetch($database);
        $this->dispatch(new SavedModel($this, $database));
    }

    protected function saveNew(?Database $database=null)
    {
        $data = [];
        foreach ($this->fields() as $name => $field)
        {
            if (!$field->isInsertable())
                continue;

            if (isset($this->data->$name))
                $data[$name] = $this->data->$name;
        }

        /** @var self $self */
        $self = get_called_class();

        if (count($data))
        {
            $self::insert()
                ->insertField(array_keys($data))
                ->values(array_values($data))
                ->fetch($database);

            if ($primaryKey = $this->primaryKey())
            {
                $id = $self::last($database)->id();
                $this->data->$primaryKey = $id;
                $this->reload($database);
            }

            $this->dispatch(new SavedModel($this, $database));
        }
    }

    public function destroy(?Database $database=null): void
    {
        if (!$this->existsInDatabase())
            return;

        /** @var class-string<static> $self */
        $self = get_called_class();

        $query = $self::delete();

        if ($primaryKey = $self::primaryKey())
        {
            $query->where($primaryKey, $this->id());
        }
        else
        {
            foreach ($this->data as $key => $value)
                $query->where($key, $value);
        }
        $query->limit(1)->fetch($database);
    }

    public function reload(?Database $database=null): void
    {
        if (! $this->primaryKey())
            return;

        /** @var self $self */
        $self = get_called_class();

        $newInstance = $self::find($this->id(), database: $database);
        $this->data = $newInstance->data;
        $this->references = $newInstance->references;
    }
}
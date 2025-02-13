<?php

namespace YonisSavary\Cube\Models;

use InvalidArgumentException;
use RuntimeException;
use stdClass;
use YonisSavary\Cube\Core\Autoloader;
use YonisSavary\Cube\Data\Bunch;
use YonisSavary\Cube\Database\Database;
use YonisSavary\Cube\Database\Query;
use YonisSavary\Cube\Database\Query\FieldComparaison;
use YonisSavary\Cube\Event\EventDispatcher;
use YonisSavary\Cube\Http\Request;
use YonisSavary\Cube\Http\Rules\Rule;
use YonisSavary\Cube\Http\Rules\Validator;
use YonisSavary\Cube\Models\Events\SavedModel;
use YonisSavary\Cube\Models\Relations\HasMany;
use YonisSavary\Cube\Models\Relations\HasOne;
use YonisSavary\Cube\Models\Relations\Relation;

use function Cube\debug;

abstract class Model extends EventDispatcher
{
    public object $data;
    protected array $references = [];

    abstract public static function table(): string;

    /** @return ModelField[] */
    abstract public static function fields(): array;

    /** @return string[] */
    abstract public static function relations(): array;

    public static function primaryKey(): ?string
    {
        return null;
    }

    public static function hasField(string $field): bool
    {
        /** @var class-string<static> $self */
        $self = get_called_class();

        return array_key_exists($field, $self::fields());
    }

    protected static function exploreModel(Model|string $class, Query &$query, string $joinAcc)
    {
        $fields = Bunch::fromValues($class::fields());

        $fields->forEach(function(ModelField $field) use (&$query, &$joinAcc, $class) {
            $fieldName = $field->name;
            $fieldAlias = "$joinAcc.$fieldName";
            $query->selectField($fieldName, $joinAcc, $fieldAlias, $class, $field);
        });

        $fields
        ->filter(fn(ModelField $x) => $x->referenceModel)
        ->forEach(function(ModelField $field) use (&$query, &$joinAcc) {
            $fieldName = $field->name;
            $refModel = $field->referenceModel;
            $refColumn = $field->referenceField;

            $refTable = $refModel::table();
            $newAcc = $joinAcc . "&" . $refTable;
            $query->join("LEFT", $refTable, $newAcc,
                new FieldComparaison($joinAcc, $fieldName, "=", $newAcc, $refColumn)
            );

            $toExploreQueue[] = [$refModel, $newAcc];
            self::exploreModel($refModel, $query, $newAcc);
        });
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
            self::exploreModel($self, $query, $table);
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

    /**
     * @return Query<static>
     */
    public static function insert(): Query
    {
         /** @var class-string<static> $self */
        $self = get_called_class();

        return Query::insert($self::table())->withBaseModel($self);
    }

    public static function insertArray(array $data, ?Database $database=null): mixed
    {
        $database ??= Database::getInstance();
        $keys = array_keys($data);
        $values = array_values($data);

         /** @var class-string<static> $self */
        $self = get_called_class();
        $self::insert()->insertField($keys)->values($values)->fetch($database);

        return $database->lastInsertId();
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
                $rules[$relationName] = Rule::array($toModel::toValidator());
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
        $validated = $request->validated($validator);

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

        debug("TRANSLATE MODEL " . $this::class, $array);


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
     * @return HasOne<static>
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

    public function save()
    {
        if ($this->existsInDatabase())
            $this->saveExisting();
        else
            $this->saveNew();
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
        $this->dispatch(new SavedModel($this));
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
            $id = $self::insertArray($data, $database);

            if ($primaryKey = $this->primaryKey())
            {
                $this->data->$primaryKey = $id;
                $this->reload();
            }

            $this->dispatch(new SavedModel($this));
        }
    }

    public function reload(): void
    {
        if (! $primary = $this->primaryKey())
            throw new RuntimeException("Cannot reload a model without a primary key");

        /** @var self $self */
        $self = get_called_class();

        $this->data = $self::find($this->data->$primary)->data;
    }
}
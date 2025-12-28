<?php

namespace Cube\Data\Models;

use Cube\Core\Autoloader;
use Cube\Data\Bunch;
use Cube\Data\Database\Database;
use Cube\Data\Database\Query;
use Cube\Event\EventDispatcher;
use Cube\Web\Http\Request;
use Cube\Web\Http\Rules\Param;
use Cube\Data\Models\Events\SavedModel;
use Cube\Data\Models\Relations\HasMany;
use Cube\Data\Models\Relations\HasOne;
use Cube\Data\Models\Relations\Relation;
use Cube\Utils\Text;
use Cube\Utils\Utils;
use Cube\Web\Http\Rules\ObjectParam;
use DateTime;
use Exception;

abstract class Model extends EventDispatcher
{
    public object $data;
    public object $original;
    public array $references = [];

    public function __construct(array $data = [], string $relationAccumulator = '')
    {
        $fields = $this->fields();

        $modelData = [];

        foreach ($fields as $key => $field) {
            if (array_key_exists($key, $fields) && isset($data[$key])) {
                if (is_array($data[$key])) {
                    continue;
                }

                $modelData[$key] = $data[$key];
            }
        }

        foreach ($modelData as $key => $_) {
            unset($data[$key]);
        }

        $this->data = empty($modelData) ? new \stdClass() : (object) $modelData;
        $this->markAsOriginal();
        $this->completeModelDataWithRelations($data, $relationAccumulator);
    }

    public function __set($name, $value)
    {
        if (static::hasField($name)) {
            $this->data->{$name} = $value;
        }
    }

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
        $primary = static::primaryKey();

        return $this->{$primary} ?? false;
    }

    public static function hasField(string $field): bool
    {
        return array_key_exists($field, static::fields());
    }

    /**
     * @return Query<static>
     */
    public static function select(bool $withRelations = true): Query
    {
        $table = static::table();
        $query = Query::select($table)->withBaseModel(static::class);
        if ($withRelations) {
            $query->exploreModel(static::class, $table);
        } else {
            foreach (static::fields() as $field) {
                $query->selectField($field->name, $table, null, static::class);
            }
        }

        return $query;
    }

    /**
     * @return Query<static>
     */
    public static function update(): Query
    {
        return Query::update(static::table())->withBaseModel(static::class);
    }

    public static function updateRow(mixed $id, array $newData): self
    {
        if (!static::primaryKey()) {
            throw new \RuntimeException('cannot call updateRow static function without a primary key');
        } // TODO Add a custom exception for needed primary key

        $query = static::update()->where(static::primaryKey(), $id);

        foreach ($newData as $column => $value) {
            $query->set($column, $value);
        }

        $query->fetch();

        return static::find($id);
    }

    public function patch(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
        $this->save();
    }

    public function markAsOriginal(bool $relationsToo = false): self
    {
        $this->original = clone $this->data;
        if ($relationsToo) {
            foreach ($this->references as $name => $model) {
                $model->markAsOriginal(true);
            }
        }

        return $this;
    }

    /**
     * @return Query<static>
     */
    public static function insert(): Query
    {
        return Query::insert(static::table())->withBaseModel(static::class);
    }

    public static function last(?string $key=null, ?Database $database = null): static
    {
        $database ??= Database::getInstance();
        $key ??= static::primaryKey();

        if (!$key) {
            throw new \Exception('Use of last() method without primary key (or any given key) is not supported');
        }

        return static::select()
            ->order($key, 'DESC')
            ->first($database)
        ;
    }

    public static function insertArray(array $data, ?Database $database = null): static
    {
        $database ??= Database::getInstance();

        $instance = new static($data);
        $instance->save($database);

        return $instance;
    }

    public static function existsWhere(array $conditions, ?Database $database = null): bool
    {
        $database ??= Database::getInstance();
        return null !== static::findWhere($conditions, false, $database);
    }

    public static function exists(mixed $primaryKeyValue, ?Database $database = null): bool
    {
        $database ??= Database::getInstance();
        if (!$primaryKey = static::primaryKey()) {
            throw new \RuntimeException( static::class . " model does not have a primary key, cannot use the exists method");
        }

        return static::existsWhere([$primaryKey => $primaryKeyValue], $database);
    }

    /**
     * @return ?static
     */
    public static function findWhere(array $conditions, bool $explore = true, ?Database $database = null): ?self
    {
        $database ??= Database::getInstance();
        $query = static::select($explore)->withBaseModel(static::class);
        foreach ($conditions as $column => $value) {
            $query->where($column, $value, '=', static::table());
        }

        $query->limit(1);

        if ($model = $query->fetch($database)[0] ?? false) {
            return $model->markAsOriginal(true);
        }

        return null;
    }

    public static function toObjectParam(bool $nullable=false, bool $withRelations=true): ObjectParam
    {
        $instance = new static();

        $fields = static::fields();
        $rules = [];

        foreach ($fields as &$field) {
            if ($field->autoIncrement) {
                $field = null;
                continue;
            }

            $forceNullable = ($field->hasReference() && !$withRelations)
                ? true
                : null;

            $rules[$field->name] = $field->toRule($forceNullable);
        }

        if ($withRelations) {
            foreach (static::relations() as $relationName) {
                /** @var Relation $relation */
                $relation = $instance->{$relationName}();

                /** @var class-string<static> $toModel */
                $toModel = $relation->toModel;
                if ($relation instanceof HasMany) {
                    $rules[$relationName] = Param::array($toModel::toObjectParam(true, false));
                }
                if ($relation instanceof HasOne) {
                    $rules[$relationName] = $toModel::toObjectParam(true, false);
                }
            }
        }

        return Param::object($rules, $nullable);
    }

    /**
     * @return ?static
     */
    public static function find(mixed $primaryKeyValue, bool $explore = true, ?Database $database = null): ?self
    {
        $database ??= Database::getInstance();
        if (!$primaryKey = static::primaryKey()) {
            throw new \RuntimeException( static::class . " model does not have a primary key, cannot use the exists method");
        }

        return static::findWhere([$primaryKey => $primaryKeyValue], $explore, $database);
    }

    /**
     * @return static
     */
    public static function findOrCreate(array $data, bool $explore = true, ?Database $database = null, array $extrasProperties = []): self
    {
        $database ??= Database::getInstance();

        if (!$model = self::findWhere($data, $explore, $database))
            return  self::insertArray(array_merge($data, $extrasProperties), $database);

        foreach ($extrasProperties as $key => $value)
            $model->$key = $value;

        return $model->save();
    }

    /**
     * @return Query<static>
     */
    public static function delete(): Query
    {
        return Query::delete(static::table())->withBaseModel(static::class);
    }

    public static function deleteId(mixed $id): ?static
    {
        if (!static::primaryKey()) {
            throw new \InvalidArgumentException('Cannot call deleteId on a model without a primary key');
        }

        if ($toDelete = static::find($id)) {
            $toDelete->destroy();
        }

        return $toDelete;
    }

    /**
     * @return self[]
     */
    public static function deleteWhere(array $conditions, ?Database $database = null): array
    {
                $select = static::select();
        $delete = static::delete();

        foreach ($conditions as $field => $value) {
            $select->where($field, $value);
            $delete->where($field, $value);
        }

        $deleted = $select->fetch($database);
        $delete->fetch($database);

        return $deleted;
    }

    public static function fromArray(array $array): static
    {
        $validated = static::toObjectParam()->validate($array)->getResult();

        return new static($validated);
    }

    public static function fromRequest(Request $request, array $forcedAttributes=[], bool $forbidsPrimaryKey=true, array $forbiddenAttributes=[]): static
    {
        $validated = static::toObjectParam()->validate($request)->getResult();

        $pk = static::primaryKey();
        if ($pk && $forbidsPrimaryKey && isset($validated[$pk]))
            unset($validated[$pk]);

        foreach($forbiddenAttributes as $attribute) {
            if (isset($validated[$attribute]))
                unset($validated[$attribute]);
        }

        return new static(array_merge($validated ?? [], $forcedAttributes));
    }

    protected function completeModelDataWithRelations(array $constructData, string $relationAccumulator = '')
    {
        foreach (static::relations() as $relationName) {
            /** @var Relation $relation */
            $relation = $this->{$relationName}();
            $relationKey = $relation->getName();
            $relationModel = $relation->toModel;

            if ($relation instanceof HasOne) {
                $accumulatorKey = $relation->fromModel . ':' . $relationKey;
                if (str_contains($relationAccumulator, $accumulatorKey)) {
                    continue;
                }

                if ($data = $constructData[$relationKey] ?? false) {
                    $oneModel = new $relationModel($data, "{$relationAccumulator}&{$accumulatorKey}");
                    $relation->bind($oneModel);
                }
            } elseif ($relation instanceof HasMany) {
                $accumulatorKey = $relation->fromModel . ':' . $relationKey;
                if (str_contains($relationAccumulator, $accumulatorKey)) {
                    continue;
                }

                if ($data = $constructData[$relationKey] ?? false) {
                    foreach ($data as $row) {
                        $manyModel = new $relationModel($row, "{$relationAccumulator}&{$accumulatorKey}");
                        $relation->bind($manyModel);
                    }
                }
            }
        }
    }

    /**
     * @template X
     *
     * @param null|string|X $class
     *
     * @return X
     */
    public function &getReference(string $referenceName, ?string $class = null): Model
    {
        $class ??= DummyModel::class;
        if (!Autoloader::extends($class, Model::class)) {
            throw new \InvalidArgumentException('$model must extends Model');
        }

        $this->references[$referenceName] ??= new $class();

        return $this->references[$referenceName];
    }

    /**
     * @return static
     */
    public function setReference(string $referenceName, array|Model $model): self
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
        if (isset($this->references[$name])) {
            return $this->references[$name];
        }
        if (static::hasField($name)) {
            return $this->data->{$name};
        }

        throw new \RuntimeException("Either ".static::class." does not have a {$name} attribute, or a relation needs to be loaded");
    }

    public function toArray(): array
    {
        $array = (array) $this->data;

        // @var Model $model
        foreach ($this->references as $key => $modelOrCollection) {
            if (is_array($modelOrCollection)) {
                $array[$key] = Bunch::of($modelOrCollection)->map(fn(Model $model) => $model->toArray())->toArray();
            } elseif ($modelOrCollection instanceof Model) {
                $array[$key] = $modelOrCollection->toArray();
            }
        }

        foreach ($array as &$value) {
            if ($value instanceof DateTime)
                $value = $value->format("Y-m-d H:i:s");
        }

        return $array;
    }

    /**
     * @return HasOne<static>
     */
    protected function hasOne(string $relationName, string $fromColumn, string $toModel, string $toColumn): HasOne
    {
        return new HasOne($relationName, $this::class, $fromColumn, $toModel, $toColumn, $this);
    }

    /**
     * @return HasMany<static>
     */
    protected function hasMany(string $relationName, string $toModel, string $toColumn, string $fromColumn): HasMany
    {
        return new HasMany($relationName, $this::class, $fromColumn, $toModel, $toColumn, $this);
    }

    protected function getComputedRelationToArray(string $relation)
    {
        if (!str_contains($relation, "."))
            return [$relation];

        $parts = explode(".", $relation);

        $rest = $parts;
        array_pop($rest);

        return [
            ...$this->getComputedRelationToArray(join(".", $rest)),
            $relation
        ];
    }

    /**
     * @param string|string[] $relations
     */
    public function load(string ...$relations): self
    {
        /** @var string[] $relations */
        $relations = Utils::toArray($relations);

        $relations = Bunch::of($relations)
            ->map(fn($rel) => $this->getComputedRelationToArray($rel))
            ->flat()
            ->uniques()
            ->get();

        foreach ($relations as $relation) {
            if (str_contains($relation, "."))
                continue;

            $this->{$relation}()->load(false);

            $childRelations = Bunch::of($relations)
                ->filter(fn($rel) => str_starts_with($rel, "$relation."))
                ->map(fn(string $rel) => Text::dontStartsWith($rel, "$relation."))
                ->get();

            if (!count($childRelations))
                continue;

            $relationInstance = &$this->references[$relation];

            if (is_array($relationInstance)) {
                foreach ($relationInstance as $child)
                    $child->load(...$childRelations);
            } else if ($relationInstance) {
                $relationInstance->load(...$childRelations);
            }
        }

        return $this;
    }

    public function loadMissing(array $relations = []): self
    {
        foreach ($relations as $relation) {
            if (str_contains($relation, "."))
                continue;

            if (!array_key_exists($relation, $this->references))
                $this->{$relation}()->load();

            $childRelations = Bunch::of($relation)
                ->diff([$relation])
                ->map(fn($rel) => Text::dontStartsWith($rel, "$relation\."))
                ->get();

            $this->references[$relation]->load($childRelations);
        }

        return $this;
    }

    public function onSaved(callable $callback)
    {
        $this->on(SavedModel::class, $callback);
    }

    public function save(?Database $database = null): self
    {
        if ($this->existsInDatabase()) {
            $this->saveExisting($database);
        } else {
            $this->saveNew($database);
        }

        return $this;
    }

    public function destroy(?Database $database = null): void
    {
        if (!$this->existsInDatabase()) {
            return;
        }
        $query = static::delete();

        if ($primaryKey = static::primaryKey()) {
            $query->where($primaryKey, $this->id());
        } else {
            foreach ($this->data as $key => $value) {
                $query->where($key, $value);
            }
        }
        $query->limit(1)->fetch($database);
    }

    public function reload(?Database $database = null): void
    {
        if (!$this->primaryKey()) {
            return;
        }

        $newInstance = static::find($this->id(), database: $database);
        $this->data = clone $newInstance->data;
        $this->markAsOriginal();

        foreach ($this->references as $referenceObject) {
            if (is_array($referenceObject)) {
                foreach ($referenceObject as $model) {
                    $model->reload($database);
                }
            } else {
                $referenceObject->reload($database);
            }
        }
    }

    public function anonymize(): self
    {
        if ($key = $this->primaryKey()) {
            unset($this->data->{$key});
        }

        foreach ($this->references as $referenceObject) {
            if (is_array($referenceObject)) {
                foreach ($referenceObject as $model) {
                    $model->anonymize();
                }
            } else {
                $referenceObject->anonymize();
            }
        }

        return $this;
    }

    public function replicate(): static
    {
        $newInstance = new static();
        $newInstance->data = clone $this->data;

        $newInstance->references = [];
        foreach ($this->references as $refName => $referenceObject) {
            /** @var Relation $relation */
            $relation = $newInstance->{$refName}();

            if ($relation instanceof HasMany) {
                foreach ($referenceObject as $model) {
                    $relation->bind($model->replicate());
                }
            } elseif ($relation instanceof HasOne) {
                $relation->bind($referenceObject->replicate());
            }
        }

        $newInstance->anonymize();

        return $newInstance;
    }

    protected function existsInDatabase(): bool
    {
        $primaryKey = $this->primaryKey();

        return $primaryKey && $this->{$primaryKey};
    }

    protected function saveExisting(?Database $database = null)
    {
        $primaryKey = $this->primaryKey();

        $query = static::update()->where($primaryKey, $this->{$primaryKey});

        $gotAnyChange = false;
        foreach ($this->data as $key => $value) {
            $field = static::fields()[$key] ?? null;

            if ($value instanceof \DateTime) {
                $type = ($field?->type ?? ModelField::DATE);
                $value = $value->format('Y-m-d' . (ModelField::DATE === $type ? ' h:i:s' : ''));
            }
            if ($field?->isGenerated() ?? false) {
                continue;
            }

            if (property_exists($this->original, $key)) {
                if ($this->original->{$key} === $value) {
                    continue;
                }
            }

            $gotAnyChange = true;
            $query->set($key, $value);
        }

        if ($gotAnyChange) {
            $query->fetch($database);
        }

        $this->markAsOriginal();
        $this->dispatch(new SavedModel($this, $database));
    }

    protected function saveNew(?Database $database = null)
    {
        $data = [];
        foreach ($this->fields() as $name => $field) {
            if (!$field->isInsertable()) {
                continue;
            }

            if (isset($this->data->{$name})) {
                $value = $this->data->{$name};
                if ($field->hasDefault && null === $value) {
                    continue;
                }
                if ($field->isGenerated()) {
                    continue;
                }

                $data[$name] = $value;
            }
        }

        if (count($data)) {
            static::insert()
                ->insertField(array_keys($data))
                ->values(array_values($data))
                ->fetch($database)
            ;

            if ($primaryKey = $this->primaryKey()) {
                $id = static::last(null, $database)->id();
                $this->data->{$primaryKey} = $id;
                $this->reload($database);
            }

            $this->dispatch(new SavedModel($this, $database));
        }
    }
}

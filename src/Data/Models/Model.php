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
use Cube\Data\Models\Exceptions\MissingModelPrimaryKeyException;
use Cube\Data\Models\Relations\HasMany;
use Cube\Data\Models\Relations\HasOne;
use Cube\Data\Models\Relations\Relation;
use Cube\Utils\Utils;
use Cube\Web\Http\Rules\ObjectParam;
use InvalidArgumentException;

abstract class Model extends EventDispatcher
{
    public object $data;
    public object $original;

    /** @var array<string,Model|Model[]> */
    public array $references = [];

    protected bool $persisted = false;

    public function __construct(array|Model $data = [], string $relationAccumulator = '')
    {
        if ($data instanceof Model)
            $data = $data->toArray();

        $modelData = array_filter(
            array_intersect_key($data, static::fields()),
            fn ($value) => !is_array($value)
        );

        $this->data = (object) $modelData;
        $this->markAsOriginal();
        $this->completeModelDataWithRelations(array_diff_key($data, $modelData), $relationAccumulator);
    }

    protected function getAttributeDefaultValue(string $name): mixed {
        throw new \RuntimeException("Either ".static::class." does not have a {$name} attribute, or a relation needs to be loaded");
    }

    protected function allowNonTableAttributeSet(): bool {
        return false;
    }

    public function __set(string $name, mixed $value)
    {
        if (!static::hasField($name) && !$this->allowNonTableAttributeSet()) {
            throw new InvalidArgumentException(static::class." does not have a [{$name}] field, use merge() to ignore the keys a model does not hold");
        }

        $this->data->{$name} = $value;
    }

    abstract public static function table(): string;

    /** @return array<string,ModelField> */
    abstract public static function fields(): array;

    /** @return string[] */
    abstract public static function relations(): array;

    public static function primaryKey(): ?string
    {
        return null;
    }

    protected static function assertPrimaryKey(string $method): string
    {
        return static::primaryKey()
            ?: throw new MissingModelPrimaryKeyException(static::class, $method);
    }

    public function id(): mixed
    {
        $primary = static::primaryKey();

        return $this->data->{$primary} ?? false;
    }

    public static function hasField(string $field): bool
    {
        return array_key_exists($field, static::fields());
    }

    /**
     * @return Query<static>
     */
    public static function select(array $with=[]): Query
    {
        $table = static::table();
        $query = Query::select($table)->withBaseModel(static::class);
        foreach (static::fields() as $field) {
            $query->selectField($field->name, $table, null, static::class);
        }

        if (count($with))
            $query->with(...$with);

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
        $primaryKey = static::assertPrimaryKey(__FUNCTION__);

        static::update()
            ->where($primaryKey, $id)
            ->setAssoc($newData)
            ->fetch();

        return static::find($id);
    }

    public function patch(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
        $this->save();
    }

    public function markAsPersisted(bool $relationsToo = false): self
    {
        $this->persisted = true;

        if (!$relationsToo) {
            return $this;
        }

        foreach ($this->referencedModels() as $model)
            $model->markAsPersisted(true);

        return $this;
    }

    public function markAsOriginal(bool $relationsToo = false): self
    {
        $this->original = clone $this->data;

        if (!$relationsToo) {
            return $this;
        }

        foreach ($this->referencedModels() as $model)
            $model->markAsOriginal(true);

        return $this;
    }

    /** @return Model[] */
    protected static function modelsOf(Model|array|null $reference): array
    {
        $models = is_array($reference)
            ? array_values($reference)
            : [$reference];

        return Bunch::of($models)->onlyInstancesOf(Model::class)->get();
    }

    /** @return Model[] */
    protected function referencedModels(): array
    {
        return Bunch::fromValues($this->references)
            ->flatMap(fn ($reference) => self::modelsOf($reference))
            ->get();
    }

    /**
     * @return Query<static>
     */
    public static function insert(): Query
    {
        return Query::insert(static::table())->withBaseModel(static::class);
    }

    public static function last(?string $key=null, ?Database $database = null): ?static
    {
        $database ??= Database::getInstance();
        $key ??= static::assertPrimaryKey(__FUNCTION__);

        return static::select()
            ->order($key, 'DESC')
            ->first($database)
        ;
    }

    public static function insertArray(array $data, ?Database $database = null): static
    {
        $database ??= Database::getInstance();

        return (new static($data))->save($database);
    }

    public static function existsWhere(array $conditions, ?Database $database = null): bool
    {
        $database ??= Database::getInstance();
        return null !== static::findWhere($conditions, [], $database);
    }

    public static function exists(mixed $primaryKeyValue, ?Database $database = null): bool
    {
        $database ??= Database::getInstance();
        $primaryKey = static::assertPrimaryKey(__FUNCTION__);

        return static::existsWhere([$primaryKey => $primaryKeyValue], $database);
    }

    /**
     * @return ?static
     */
    public static function findWhere(array $conditions, array $with = [], ?Database $database = null): ?self
    {
        $database ??= Database::getInstance();
        $query = static::select($with)
            ->whereAssoc($conditions, static::table())
            ->limit(1);

        if ($model = $query->fetch($database)[0] ?? false) {
            $model->loadMissing(...$with);
            return $model->markAsOriginal(true);
        }

        return null;
    }

    public static function toObjectParam(bool $nullable=false, bool $withRelations=true): ObjectParam
    {
        $instance = new static();

        $fields = static::fields();
        $rules = [];

        foreach ($fields as $field) {
            if ($field->autoIncrement) {
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

                /** @var class-string<Model> $toModel */
                $toModel = $relation->toModel;

                /** @var ObjectParam $baseRule */
                $baseRule = $toModel::toObjectParam(true, false);
                $baseRule->optional($relation->toColumn);

                if ($relation instanceof HasMany) {
                    $rules[$relationName] = Param::array($baseRule, true);
                }
                if ($relation instanceof HasOne) {
                    $rules[$relationName] = $baseRule;
                }
            }
        }

        return Param::object($rules, $nullable)
            ->withTransformer(fn($data) => $data ? new static($data): null);
    }

    public static function find(mixed $primaryKeyValue, array $with = [], ?Database $database = null): ?static
    {
        $database ??= Database::getInstance();
        $primaryKey = static::assertPrimaryKey(__FUNCTION__);

        return static::findWhere([$primaryKey => $primaryKeyValue], $with, $database);
    }

    public static function findOrCreate(array $data, array $with = [], ?Database $database = null, array $extrasProperties = []): static
    {
        $database ??= Database::getInstance();

        if (!$model = self::findWhere($data, $with, $database))
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
        static::assertPrimaryKey(__FUNCTION__);

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
        $deleted = static::select()->whereAssoc($conditions)->fetch($database);
        static::delete()->whereAssoc($conditions)->fetch($database);

        return $deleted;
    }

    public static function fromArray(array $array): static
    {
        $validated = static::toObjectParam()->validate($array)->getResult();

        return new static($validated);
    }

    public static function fromRequest(Request $request, array $forcedAttributes=[], bool $forbidsPrimaryKey=true, array $forbiddenAttributes=[]): static
    {
        $pk = static::primaryKey();
        $rule = static::toObjectParam();
        if ($pk && $forbidsPrimaryKey)
            $rule->without($pk);

        if (count($forbiddenAttributes))
            $rule->without($forbiddenAttributes);

        $validation = $rule->validate($request);
        if (!$validation->isValid() || !$model = $validation->getResult()) {
            throw new InvalidArgumentException('Could not build a '.static::class.' out of the given request, validate it before calling fromRequest()');
        }

        foreach ($forcedAttributes as $key => $value) {
            $model->$key = $value;
        }

        return $model;
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

        # Two line syntax is needed, this function returns a reference
        $this->references[$referenceName] ??= new $class();

        return $this->references[$referenceName];
    }

    public function setReference(string $referenceName, array|Model $model): static
    {
        $this->references[$referenceName] = $model;

        return $this;
    }

    public function pushReference(string $referenceName, Model $model): static
    {
        $this->references[$referenceName] ??= [];
        $this->references[$referenceName][] = $model;

        return $this;
    }

    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->references))
            return $this->references[$name];

        if (property_exists($this->data, $name))
            return $this->data->{$name};

        return $this->getAttributeDefaultValue($name);
    }

    public function toArray(): array
    {
        $fields = static::fields();

        $data = (array) $this->data;
        $array = [];
        foreach ($data as $name => $value)
        {
            $field = $fields[$name] ?? new ModelField($name);
            $array[$name] = $field->format($value);
        }

        foreach ($this->references as $key => $modelOrCollection)
        {
            $array[$key] = match(true) {
                is_array($modelOrCollection) =>
                    Bunch::of($modelOrCollection)->map(fn(Model $model) => $model->toArray())->toArray(),

                ($modelOrCollection instanceof Model) =>
                    $modelOrCollection->toArray(),

                default => null
            };
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

    protected function loadTree(array $tree, bool $skipLoaded=false)
    {
        foreach ($tree as $relation => $subtree)
        {
            $skip = $skipLoaded && array_key_exists($relation, $this->references);
            if (!$skip)
                $this->{$relation}()->load();

            if (!count($subtree))
                continue;

            $relationInstance = &$this->references[$relation];

            foreach (self::modelsOf($relationInstance) as $child)
                $child->loadTree($subtree);
        }
    }

    /**
     * @param string|string[] $relations
     */
    public function load(string ...$relations): self
    {
        $tree = new RelationTree(...$relations);
        $this->loadTree($tree->getTree());
        return $this;
    }

    public function loadMissing(string ...$relations): self
    {
        $tree = new RelationTree(...$relations);
        $this->loadTree($tree->getTree(), true);
        return $this;
    }

    public function onSaved(callable $callback)
    {
        $this->on(SavedModel::class, $callback);
    }

    public function save(?Database $database = null): self
    {
        $this->existsInDatabase()
            ? $this->saveExisting($database)
            : $this->saveNew($database);

        return $this;
    }

    public function destroy(?Database $database = null): void
    {
        if (!$this->existsInDatabase()) {
            return;
        }

        $primaryKey = static::primaryKey();
        $data = $primaryKey
            ? [$primaryKey => $this->original->{$primaryKey}]
            : (array) $this->original;

        static::delete()
            ->whereAssoc($data)
            ->first($database);

        $this->persisted = false;
    }

    public function reload(?Database $database = null): void
    {
        if (!$this->primaryKey()) {
            return;
        }

        if (!$newInstance = static::find($this->id(), database: $database)) {
            throw new \RuntimeException('Cannot reload '.static::class.', no row has ['.static::primaryKey().'] = '.var_export($this->id(), true));
        }

        $this->data = clone $newInstance->data;
        $this->markAsOriginal()->markAsPersisted();

        foreach ($this->referencedModels() as $model)
            $model->reload($database);
    }

    public function anonymize(): self
    {
        if ($key = $this->primaryKey()) {
            unset($this->data->{$key});
        }

        foreach ($this->referencedModels() as $model)
            $model->anonymize();

        return $this;
    }

    public function replicate(): static
    {
        $newInstance = new static();
        $newInstance->data = clone $this->data;

        foreach ($this->references as $refName => $referenceObject)
        {
            /** @var Relation $relation */
            $relation = $newInstance->{$refName}();

            if ($relation instanceof HasMany)
            {
                foreach ($referenceObject as $model)
                    $relation->bind($model->replicate());
            }
            elseif ($relation instanceof HasOne)
            {
                $relation->bind($referenceObject->replicate());
            }
        }

        $newInstance->anonymize();

        return $newInstance;
    }

    protected function existsInDatabase(): bool
    {
        return $this->persisted;
    }

    protected function saveExisting(?Database $database = null)
    {
        $fields = static::fields();

        $patch = [];
        foreach ($this->data as $key => $value) {
            $field = $fields[$key] ?? null;

            if ($field?->isGenerated())
                continue;

            if (property_exists($this->original, $key) && $this->original->{$key} === $value)
                continue;

            $patch[$key] = $field?->format($value) ?? $value;
        }

        if (count($patch)) {
            $primaryKey = $this->primaryKey();

            static::update()
                ->where($primaryKey, $this->original->{$primaryKey})
                ->setAssoc($patch)
                ->fetch($database);
        }

        $this->markAsOriginal();
        $this->dispatch(new SavedModel($this, $database));
    }

    protected function saveNew(?Database $database = null)
    {
        $database ??= Database::getInstance();

        $data = [];
        foreach (static::fields() as $name => $field) {
            if (!$field->isInsertable() || $field->isGenerated() || !isset($this->data->{$name}))
                continue;

            $data[$name] = $field->format($this->data->{$name});
        }

        if (!count($data))
            return;

        static::insert()
            ->insertField(array_keys($data))
            ->values(array_values($data))
            ->fetch($database);

        $this->persisted = true;

        if ($primaryKey = $this->primaryKey()) {
            $this->data->{$primaryKey} = $data[$primaryKey] ?? $database->lastInsertId();
            $this->reload($database);
        } else {
            $this->markAsOriginal();
        }

        $this->dispatch(new SavedModel($this, $database));
    }

    /**
     * Allow the modification of multiple keys at once through an assoc array
     * @param array|Request $assocData Associative array to merge with, it a Request is given `validated()` is used
     */
    public function merge(array|Request $assocData): self {
        if ($assocData instanceof Request)
            $assocData = $assocData->validated();

        if (!Utils::isAssoc($assocData))
            throw new InvalidArgumentException('Given $assocData must be an associative array, got a list.');

        foreach ($assocData as $key => $value) {
            if ($this->hasField($key))
                $this->$key = $value;
        }

        return $this;
    }
}

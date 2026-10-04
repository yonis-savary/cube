<?php

namespace Cube\Data\Database;

use Cube\Core\Autoloader;
use Cube\Data\Bunch;
use Cube\Data\Database\Builders\QueryBuilder;
use Cube\Data\Database\Query\Field;
use Cube\Data\Database\Query\FieldComparaison;
use Cube\Data\Database\Query\FieldCondition;
use Cube\Data\Database\Query\InsertField;
use Cube\Data\Database\Query\InsertValues;
use Cube\Data\Database\Query\Join;
use Cube\Data\Database\Query\Limit;
use Cube\Data\Database\Query\Order;
use Cube\Data\Database\Query\QueryBase;
use Cube\Data\Database\Query\RawCondition;
use Cube\Data\Database\Query\UpdateField;
use Cube\Data\Models\DummyModel;
use Cube\Data\Models\Model;
use Cube\Data\Models\ModelField;
use Cube\Data\Models\Relations\HasMany;
use Cube\Data\Models\Relations\HasOne;
use Cube\Data\Models\Relations\Relation;
use Cube\Data\Models\Relations\RelationResolver;
use Cube\Data\Models\RelationTree;
use Cube\Env\Logger\Logger;
use stdClass;

/**
 * @template TModel
 */
class Query
{
    public QueryBase $base;

    /** @var InsertValues[] */
    public array $insertValues = [];

    public InsertField $insertFields;

    /** @var UpdateField[] */
    public array $updateFields = [];

    /** @var Field[] */
    public array $selectFields = [];

    /** @var Field[] */
    public array $knownFields = [];

    /** @var Join[] */
    public array $joins = [];

    /** @var array<FieldComparaison|FieldCondition|RawCondition|'OR'|array> */
    public array $conditions = [];

    /** @var Order[] */
    public array $orders = [];

    public ?Limit $limit = null;

    /** @var RelationResolver[] $resolvers */
    public array $resolvers = [];

    public function __construct(string $type, string $table, string $model = DummyModel::class)
    {
        $this->base = new QueryBase($type, $table, $model);
    }

    public static function insert(string $table): self
    {
        return new self(QueryBase::INSERT, $table);
    }

    public static function select(string $table): self
    {
        return new self(QueryBase::SELECT, $table);
    }

    public static function update(string $table): self
    {
        return new self(QueryBase::UPDATE, $table);
    }

    public static function delete(string $table): self
    {
        return new self(QueryBase::DELETE, $table);
    }

    public function withBaseModel(string $model): self
    {
        if (!Autoloader::extends($model, Model::class)) {
            throw new \InvalidArgumentException('Given $model must extends Model');
        }

        $this->base->model = $model;

        return $this;
    }

    /**
     * Build a grouped condition parenthesis
     * The query is given to the callback (nested whereGroup is supported)
     *
     * @param \Closure(self):void $groupBuilder
     */
    public function whereGroup(callable $groupBuilder): self {

        $parentConditions = $this->conditions;
        $this->conditions = [];

        $groupBuilder($this);

        $parentConditions[] = $this->conditions;
        $this->conditions = $parentConditions;

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function where(string $field, mixed $value, string $operator = '=', ?string $table = null): self
    {
        $table ??= $this->getFieldTable($field);

        if ($value instanceof Bunch)
            $value = $value->toArray();

        if (is_array($value)) {
            if ('=' === $operator) {
                $operator = 'IN';
            }
            if ('<>' === $operator) {
                $operator = 'NOT IN';
            }

            if (!count($value))
            {
                $condition = 'NOT IN' === $operator
                    ? '1=1'
                    : '1=0';
                $this->conditions[] = new RawCondition($condition);

                return $this;
            }
        }
        if (is_null($value)) {
            if ('=' === $operator) {
                $operator = 'IS';
            }
            if ('<>' === $operator) {
                $operator = 'IS NOT';
            }
        }

        $this->conditions[] = new FieldCondition($table, $field, $operator, $value);

        return $this;
    }

    public function whereIn(string $field, array|Bunch|Query $value, ?string $table = null): self
    {
        return $this->where($field, $value, 'IN', $table);
    }

    public function whereNotIn(string $field, array|Bunch|Query $value, ?string $table = null): self
    {
        return $this->where($field, $value, 'NOT IN', $table);
    }


    /**
     * @return self<TModel>
     */
    public function or(): self {
        $this->conditions[] = "OR";
        return $this;
    }

    /**
     * @param \Closure(self<TModel>) $callback
     * @return self<TModel>
     */
    public function when(mixed $condition, callable|\Closure $callback): self
    {
        if ($condition)
            ($callback)($this);

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function whereRaw(string $expression): self
    {
        $this->conditions[] = new RawCondition($expression);
        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function insertField(array $fields): self
    {
        $this->insertFields = new InsertField($fields);

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function values(array ...$values): self
    {
        foreach ($values as $set) {
            foreach ($set as &$value) {
                if ($value instanceof Model) {
                    $value = $value->id();
                }
            }

            $this->insertValues[] = new InsertValues($set);
        }

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function selectField(string $field, ?string $table = null, ?string $alias = null, string $model = stdClass::class, ?ModelField $modelField = null): self
    {
        $table ??= $this->getFieldTable($field);

        $this->selectFields[] = new Field($table, $field, null, $alias, $model, $modelField);

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function selectExpression(string $expression, ?string $alias = null): self
    {
        $this->selectFields[] = new Field(null, null, $expression, $alias);

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function join(string $type, string $tableToJoin, ?string $alias = null, ?FieldComparaison $condition = null): self
    {
        $this->joins[] = new Join($type, $tableToJoin, $alias, $condition);

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function limit(?int $limit = null, ?int $offset = null): self
    {
        $this->limit = new Limit($limit, $offset);

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function limitless(): self
    {
        $this->limit = null;

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function order(string $fieldOrAlias, string $type = 'DESC', ?string $table = null): self
    {
        $table ??= $this->getFieldTable($fieldOrAlias);
        $this->orders[] = new Order($fieldOrAlias, $type, $table);

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function set(string $field, mixed $newValue, ?string $table = null): self
    {
        $table ??= $this->getFieldTable($field);
        $this->updateFields[] = new UpdateField($table, $field, $newValue);

        return $this;
    }

    public function build(?Database $database = null): string
    {
        $database ??= Database::getInstance();
        $builder = $database->getQueryBuilder();

        return $builder->build($this, $database);
    }

    public function count(?Database $database = null): int
    {
        $database ??= Database::getInstance();
        $builder = $database->getQueryBuilder();

        return $builder->count($this, $database);
    }

    public function exists(?Database $database = null): bool
    {
        return (clone $this)->limit(1)->count($database) > 0;
    }

    /**
     * @return TModel[]
     */
    public function fetch(?Database $database = null): array
    {
        $database ??= Database::getInstance();
        $query = $this->build($database);

        $data = $database->query($query, [], \PDO::FETCH_NUM);

        $baseModel = $this->base->model;

        $results = [];
        foreach ($data as $row) {
            /** @var Model $compiledRow */
            $compiledRow = new $baseModel();

            $fieldCount = 0;
            foreach ($this->selectFields as $field) {
                /** @var Model $ref */
                $ref = &$compiledRow;

                $alias = $field->alias ?? ($field->table.'.'.$field->field);
                $model = $field->model;

                list($scope, $column) = str_contains($alias, '.')
                    ? explode('.', $alias, 2)
                    : [$this->base->table, $alias];
                $scope = explode('&', $scope);
                array_shift($scope);
                foreach ($scope as $subscope) {
                    $ref = &$ref->getReference($subscope, $model);
                }

                $value = $row[$fieldCount];
                if ($modelField = $field->modelField) {
                    $value = $modelField->parse($value);
                }

                $ref->{$column} = $value;
                ++$fieldCount;
            }

            $results[] = $compiledRow->markAsPersisted(true);
        }

        foreach ($this->resolvers as $resolver) {
            $resolver->enrichData($results, $database);
        }

        return $results;
    }

    /**
     * @param \Closure(TModel[],Bunch<int,TModel>):void $callback
     */
    public function chunk(int $chunkSize, callable $callback, ?Database $database = null): void
    {
        $count = $this->limitless()->count($database);
        $chunkCount = ceil($count / $chunkSize);

        for ($i=0; $i<$chunkCount; $i++)
        {
            $chunkData = $this->limit($chunkSize, $i * $chunkSize)->fetch($database);
            $callback($chunkData, Bunch::of($chunkData));
        }
    }


    /**
     * @return Bunch<TModel>
     */
    public function fetchBunch(?Database $database = null) {
        return Bunch::of($this->fetch($database));
    }

    /**
     * @return TModel|null
     */
    public function first(?Database $database = null): ?Model
    {
        return $this->limit(1)->fetch($database)[0] ?? null;
    }

    /**
     * @return Bunch<int,TModel>
     */
    public function toBunch(?Database $database = null): Bunch
    {
        $database ??= Database::getInstance();

        return Bunch::of($this->fetch($database));
    }

    public function with(string ...$relations): Query {

        $tree = new RelationTree(...$relations);
        $treeArray = $tree->getTree();

        $this->exploreTree(
            $this->base->model,
            $treeArray,
            $this->base->table
        );

        return $this;
    }

    /**
     * @param class-string<Model> $referenceClass
     */
    public function exploreTree(string $referenceClass, array $tree, ?string $joinAcc=null): self {
        $modelRelations = $referenceClass::relations();
        foreach ($tree as $relationName => $subtree) {
            if (!in_array($relationName, $modelRelations)) {
                Logger::getInstance()->error("Cannot load $relationName on model $referenceClass");
                continue;
            }

            $instance = new $referenceClass();
            /** @var Relation $relation */
            $relation = $instance->{$relationName}();

            $fieldName = $relation->fromColumn;
            $refModel = $relation->toModel;
            $refColumn = $relation->toColumn;

            $refTable = $refModel::table();
            $subJoinAcc = $joinAcc.'&'.$relation->getName();

            if ($relation instanceof HasMany) {
                $newRelationAcc = array_slice(explode('&', $joinAcc ?? ''), 1);
                $this->resolvers[] = new RelationResolver($relation, $newRelationAcc, $subtree);
                continue;
            }
            else if (!$relation instanceof HasOne) {
                Logger::getInstance()->error("Can only load HasOne relations on queries model ($referenceClass.$relationName)");
                continue;
            }

            $this->join(
                'LEFT',
                $refTable,
                $subJoinAcc,
                new FieldComparaison($joinAcc, $fieldName, '=', $subJoinAcc, $refColumn)
            );

            $subfields = $refModel::fields();
            foreach ($subfields as $subField) {
                $subFieldName = $subField->name;
                $subFieldAlias = "{$subJoinAcc}.{$subFieldName}";

                $this->selectField($subFieldName, $subJoinAcc, $subFieldAlias, $refModel, $subField);
            }

            $this->exploreTree($refModel, $subtree, $subJoinAcc);
        }

        return $this;
    }

    protected function getFieldTable(string $field): ?string
    {
        if (!count($this->joins)) {
            return $this->base->table;
        }

        $existingField = Bunch::of($this->selectFields)
            ->push(...$this->knownFields)
            ->first(fn (Field $fieldObj) => $fieldObj->field === $field)
        ;

        if (!$existingField) {
            throw new \Exception("Could not determine a table for field [{$field}]");
        }

        return $existingField->table;
    }
}

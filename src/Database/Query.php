<?php

namespace Cube\Database;

use Cube\Core\Autoloader;
use Cube\Data\Bunch;
use Cube\Database\Builders\QueryBuilder;
use Cube\Database\Query\Field;
use Cube\Database\Query\FieldComparaison;
use Cube\Database\Query\FieldCondition;
use Cube\Database\Query\InsertField;
use Cube\Database\Query\InsertValues;
use Cube\Database\Query\Join;
use Cube\Database\Query\Limit;
use Cube\Database\Query\Order;
use Cube\Database\Query\QueryBase;
use Cube\Database\Query\RawCondition;
use Cube\Database\Query\UpdateField;
use Cube\Models\DummyModel;
use Cube\Models\Model;
use Cube\Models\ModelField;

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

    /** @var array<FieldComparaison|FieldCondition|RawCondition> */
    public array $conditions = [];

    /** @var Order[] */
    public array $orders = [];

    public ?Limit $limit = null;

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

    public function where(string $field, mixed $value, string $operator = '=', ?string $table = null): self
    {
        $table ??= $this->getFieldTable($field);

        if (is_array($value)) {
            if ('=' === $operator) {
                $operator = 'IN';
            }
            if ('<>' === $operator) {
                $operator = 'NOT IN';
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


    public function when(mixed $condition, callable|\Closure $callback): self
    {
        if ($condition)
            ($callback)($this);

        return $this;
    }

    public function whereRaw(string $expression): self
    {
        $this->conditions[] = new RawCondition($expression);
        return $this;
    }

    public function insertField(array $fields): self
    {
        $this->insertFields = new InsertField($fields);

        return $this;
    }

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

    public function selectField(string $field, ?string $table = null, ?string $alias = null, string $model = DummyModel::class, ?ModelField $modelField = null): self
    {
        $table ??= $this->getFieldTable($field);

        $this->selectFields[] = new Field($table, $field, null, $alias, $model, $modelField);

        return $this;
    }

    public function selectExpression(string $expression, ?string $alias = null): self
    {
        $this->selectFields[] = new Field(null, null, $expression, $alias);

        return $this;
    }

    public function join(string $type, string $tableToJoin, ?string $alias = null, ?FieldComparaison $condition = null): self
    {
        $this->joins[] = new Join($type, $tableToJoin, $alias, $condition);

        return $this;
    }

    public function limit(?int $limit = null, ?int $offset = null): self
    {
        $this->limit = new Limit($limit, $offset);

        return $this;
    }

    public function order(?string $fieldOrAlias = null, string $type = 'DESC', ?string $table = null): self
    {
        $table ??= $this->getFieldTable($fieldOrAlias);
        $this->orders[] = new Order($fieldOrAlias, $type, $table);

        return $this;
    }

    public function set(string $field, mixed $newValue, ?string $table = null): self
    {
        $table ??= $this->getFieldTable($field);
        $this->updateFields[] = new UpdateField($table, $field, $newValue);

        return $this;
    }

    public function build(?Database $database = null): string
    {
        $database ??= Database::getInstance();
        $builder = $this->getQueryBuilder($database);

        return $builder->build($this, $database);
    }

    public function count(): int
    {
        $database ??= Database::getInstance();
        $builder = $this->getQueryBuilder($database);

        return $builder->count($this, $database);
    }

    /**
     * @return array<TModel>
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

                list($scope, $column) = explode('.', $alias);
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

            $results[] = $compiledRow;
        }

        return $results;
    }

    /**
     * @return TModel
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

        return Bunch::of($this->fetch());
    }

    public function exploreModel(Model|string $class, string $joinAcc): self
    {
        $fields = Bunch::fromValues($class::fields());

        $fields->forEach(function (ModelField $field) use (&$joinAcc, $class) {
            $fieldName = $field->name;
            $fieldAlias = "{$joinAcc}.{$fieldName}";
            $this->selectField($fieldName, $joinAcc, $fieldAlias, $class, $field);
        });

        $fields
            ->filter(fn (ModelField $x) => $x->referenceModel)
            ->forEach(function (ModelField $field) use (&$joinAcc) {
                $fieldName = $field->name;
                $refModel = $field->referenceModel;
                $refColumn = $field->referenceField;

                $refTable = $refModel::table();
                $newAcc = $joinAcc.'&'.$refTable;
                $this->join(
                    'LEFT',
                    $refTable,
                    $newAcc,
                    new FieldComparaison($joinAcc, $fieldName, '=', $newAcc, $refColumn)
                );

                $toExploreQueue[] = [$refModel, $newAcc];
                $this->exploreModel($refModel, $newAcc);
            })
        ;

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

    protected function getQueryBuilder(Database $database): QueryBuilder
    {
        $driver = $database->getDriver();

        $builder = Bunch::fromExtends(QueryBuilder::class)
            ->first(fn (QueryBuilder $builder) => $builder->supports($driver))
        ;

        if (!$builder) {
            throw new \InvalidArgumentException("Could not find a query builder that supports [{$driver}] database");
        }

        return $builder;
    }
}

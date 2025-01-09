<?php

namespace YonisSavary\Cube\Database;

use Exception;
use InvalidArgumentException;
use PDO;
use YonisSavary\Cube\Core\Autoloader;
use YonisSavary\Cube\Data\Bunch;
use YonisSavary\Cube\Database\Builders\BuilderInterface;
use YonisSavary\Cube\Database\Query\Field;
use YonisSavary\Cube\Database\Query\FieldComparaison;
use YonisSavary\Cube\Database\Query\FieldCondition;
use YonisSavary\Cube\Database\Query\InsertField;
use YonisSavary\Cube\Database\Query\InsertValues;
use YonisSavary\Cube\Database\Query\Join;
use YonisSavary\Cube\Database\Query\Limit;
use YonisSavary\Cube\Database\Query\Order;
use YonisSavary\Cube\Database\Query\QueryBase;
use YonisSavary\Cube\Database\Query\SelectField;
use YonisSavary\Cube\Database\Query\UpdateField;
use YonisSavary\Cube\Models\DummyModel;
use YonisSavary\Cube\Models\Model;

class Query
{
    public QueryBase $base;

    /** @var array<InsertValues> */
    public array $insertValues = [];

    public InsertField $insertFields;

    /** @var array<UpdateField> */
    public array $updateFields = [];

    /** @var array<Field> */
    public array $selectFields = [];
    /** @var array<Field> */
    public array $knownFields = [];

    /** @var array<Join> */
    public array $joins = [];

    /** @var array<FieldComparaison|FieldCondition|RawCondition> */
    public array $conditions = [];

    /** @var array<Order> */
    public array $orders = [];

    public ?Limit $limit = null;

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

    public function __construct(string $type, string $table, string $model=DummyModel::class)
    {
        $this->base = new QueryBase($type, $table, $model);
    }

    protected function getFieldTable(string $field): ?string
    {
        if (!count($this->joins))
            return null;

        $existingField = Bunch::of($this->selectFields)
            ->push(...$this->knownFields)
            ->first(fn(Field $fieldObj) => $fieldObj->field === $field);

        if (!$existingField)
            throw new Exception("Could not determine a table for field [$field]");

        return $existingField->table;
    }

    public function where(string $field, mixed $value, string $operator="=", ?string $table=null): self
    {
        $table ??= $this->getFieldTable($field);

        $this->conditions[] = new FieldCondition($table, $field, $operator, $value);
        return $this;
    }

    public function insertField(array $fields): self
    {
        $this->insertFields = new InsertField($fields);
        return $this;
    }

    public function values(array ...$values): self
    {
        foreach ($values as $set)
            $this->insertValues[] = new InsertValues($set);
        return $this;
    }

    public function selectField(string $field, ?string $table=null, ?string $alias=null): self
    {
        $table ??= $this->getFieldTable($field);

        $this->selectFields[] = new Field($table, $field, null, $alias);
        return $this;
    }

    public function selectExpression(string $expression, ?string $alias=null): self
    {
        $this->selectFields[] = new Field(null, null, $expression, $alias);
        return $this;
    }

    public function join(string $type, string $tableToJoin, ?string $alias=null, ?FieldComparaison $condition=null): self
    {
        $this->joins[] = new Join($type, $tableToJoin, $alias, $condition);
        return $this;
    }

    public function limit(?int $limit=null, ?int $offset=null): self
    {
        $this->limit = new Limit($limit, $offset);
        return $this;
    }

    public function order(?string $fieldOrAlias=null, string $type="DESC", ?string $table=null): self
    {
        $this->orders[] = new Order($fieldOrAlias, $type, $table);
        return $this;
    }

    public function set(string $field, mixed $newValue, ?string $table=null): self
    {
        $table ??= $this->getFieldTable($field);
        $this->updateFields[] = new UpdateField($table, $field, $newValue);
        return $this;
    }


    public function build(?Database $database=null): string
    {
        $database ??= Database::getInstance();
        $driver = $database->getDriver();

        $builder = Bunch::of(Autoloader::classesThatImplements(BuilderInterface::class))
            ->map(fn($class) => new $class)
            ->first(fn(BuilderInterface $builder) =>
                Bunch::of($builder->getSupportedPDODriver())
                ->has($driver)
            );

        if (!$builder)
            throw new InvalidArgumentException("Could not find a query builder that supports [$driver] database");

        /** @var BuilderInterface $builder */
        return $builder->build($this, $database);
    }

    public function fetch(?Database $database=null): array
    {
        $database ??= Database::getInstance();
        $query = $this->build($database);

        $data = $database->query($query, [], PDO::FETCH_ASSOC);

        $baseModel = $this->base->model;

        $results = [];
        foreach ($data as $row)
        {
            /** @var Model $compiledRow */
            $compiledRow = new $baseModel;

            foreach ($this->selectFields as $field)
            {
                /** @var Model $ref */
                $ref = &$compiledRow;

                $fieldName = $field->field;
                $alias = $field->alias;
                $model = $field->model;

                if (preg_match("/^(\w+&)+\w+\.\w+$/", $alias))
                {
                    list($scope, $column) = explode(".", $alias);
                    foreach (explode("&", $scope) as $subscope)
                        $ref = &$ref->getReference($subscope, $model);

                    $ref->data[$column] = $row[$alias];
                }
                else
                {
                    $ref->data[$fieldName] = $row[$fieldName];
                }
            }

            $results[] = $compiledRow;
        }

        return $results;
    }

    public function toBunch(?Database $database=null): Bunch
    {
        $database ??= Database::getInstance();
        return Bunch::of($this->fetch());
    }
}
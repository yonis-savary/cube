<?php

namespace Cube\Data\Models;

use Cube\Core\Autoloader;
use Cube\Data\Database\Database;
use Cube\Data\Models\Relations\HasMany;
use Cube\Data\Models\Relations\HasOne;
use Cube\Utils\Implementations;
use Faker\Factory;
use Faker\Generator;
use InvalidArgumentException;
use RuntimeException;

/**
 * @template TModel of Model
 */
abstract class ModelFactory
{
    /** @var array<class-string<Model>,ModelFactory> */
    private static array $factories = [];

    private ?Generator $faker = null;

    private ?int $seed = null;

    /** @var array<string,array<mixed>> */
    private array $relationTree = [];

    /** @var array<string,ModelFactory<Model>> */
    private array $relationFactories = [];

    /** @return class-string<TModel> */
    abstract public static function getModelClass(): string;

    /**
     * Prefer `Model::factory()`
     *
     * @template TFor of Model
     * @param class-string<TFor> $modelClass
     * @return ModelFactory<TFor>
     */
    final public static function for(string $modelClass): ModelFactory
    {
        return self::$factories[$modelClass] ??=
            Implementations::find(self::class, fn (string $factory) => $factory::getModelClass() === $modelClass)
            ?? new GenericModelFactory($modelClass);
    }

    /**
     * @return array<string,mixed> Values of the fields a type alone cannot fake well (an email, a price...)
     */
    protected function definition(Generator $faker): array
    {
        return [];
    }

    /**
     * @return class-string<TModel>
     */
    protected function getFactoredClass(): string
    {
        return static::getModelClass();
    }

    /**
     * @return TModel A model that is not saved : its key and the rows it references get made-up values
     */
    public function make(array $overrides = []): Model
    {
        return $this->fakeDependentRelations($this->build($overrides, null), null);
    }

    /**
     * @return TModel A saved model, the rows it references created first through their own factory
     */
    public function create(array $overrides = [], ?Database $database = null): Model
    {
        $database ??= Database::getInstance();

        $model = $this->build($overrides, $database)->save($database);

        return $this->fakeDependentRelations($model, $database);
    }

    /**
     * @return TModel[]
     */
    public function makeMany(int $count, array $overrides = []): array
    {
        return array_map(fn () => $this->make($overrides), range(1, $count));
    }

    /**
     * @return TModel[]
     */
    public function createMany(int $count, array $overrides = [], ?Database $database = null): array
    {
        return array_map(fn () => $this->create($overrides, $database), range(1, $count));
    }

    /**
     * The same seed gives the same values : what a document or a fixture needs to stay stable
     */
    public function withSeed(int $seed): static
    {
        $this->seed = $seed;
        $this->faker = null;
        $this->relationFactories = [];

        return $this;
    }

    /**
     * Unlike `withSeed()`, gives a copy : the factory shared by `Model::factory()` keeps making bare models
     *
     * @param string ...$relations Relation names, dot-separated for nested ones (`'lines.product'`)
     */
    public function with(string ...$relations): static
    {
        return $this->withRelationTree((new RelationTree(...$relations))->getTree());
    }

    protected function faker(): Generator
    {
        if ($this->faker)
            return $this->faker;

        if (!class_exists(Factory::class))
            throw new RuntimeException(static::class.' needs fakerphp/faker to make up values : composer require --dev fakerphp/faker');

        $this->faker = Factory::create();
        if ($this->seed !== null)
            $this->faker->seed($this->seed);

        return $this->faker;
    }

    /** @param array<string,array<mixed>> $relationTree */
    private function withRelationTree(array $relationTree): static
    {
        $factory = clone $this;
        $factory->relationTree = $relationTree;
        $factory->relationFactories = [];

        return $factory;
    }

    /** @return TModel */
    private function build(array $overrides, ?Database $database): Model
    {
        $modelClass = $this->getFactoredClass();
        $values = [...$this->definition($this->faker()), ...$overrides];

        $referencedModels = [];
        foreach ($this->getRelations() as $name => $relation) {
            if (!$this->isReferencedByTheModel($relation))
                continue;

            $referencedModels[$name] = $this->buildRelated($name, $relation, [], $database);
            $values[$relation->fromColumn] = $referencedModels[$name]->{$relation->toColumn};
        }

        foreach ($modelClass::fields() as $name => $field) {
            // A saved model leaves its key to the database, an example still needs one
            if (array_key_exists($name, $values) || ($database && $this->isLeftToTheDatabase($modelClass, $field)))
                continue;

            $values[$name] = $this->fakeValue($field, $database);
        }

        $model = new $modelClass($values);
        foreach ($referencedModels as $name => $referencedModel)
            $model->setReference($name, $referencedModel);

        return $model;
    }

    /**
     * @param TModel $model
     * @return TModel
     */
    private function fakeDependentRelations(Model $model, ?Database $database): Model
    {
        foreach ($this->getRelations() as $name => $relation) {
            if ($this->isReferencedByTheModel($relation))
                continue;

            $related = $this->buildRelated($name, $relation, [$relation->toColumn => $model->{$relation->fromColumn}], $database);
            $model->setReference($name, $relation instanceof HasMany ? [$related] : $related);
        }

        return $model;
    }

    /** @return array<string,HasOne<Model>|HasMany<Model>> */
    private function getRelations(): array
    {
        $modelClass = $this->getFactoredClass();
        $instance = new $modelClass();

        $relations = [];
        foreach (array_keys($this->relationTree) as $name) {
            if (!in_array($name, $modelClass::relations(), true))
                throw new InvalidArgumentException("Cannot fake relation [{$name}], it is not one of {$modelClass}::relations()");

            $relations[$name] = $instance->{$name}();
        }

        return $relations;
    }

    /** @param HasOne<Model>|HasMany<Model> $relation */
    private function isReferencedByTheModel(HasOne|HasMany $relation): bool
    {
        // A HasOne pointing to the key of its target is a foreign key held by the model : the target must exist first
        return $relation instanceof HasOne && $relation->toColumn === $relation->toModel::primaryKey();
    }

    /**
     * @param HasOne<Model>|HasMany<Model> $relation
     * @param array<string,mixed> $overrides
     */
    private function buildRelated(string $name, HasOne|HasMany $relation, array $overrides, ?Database $database): Model
    {
        // Kept per relation, so its values keep coming from one sequence across makeMany()/createMany()
        $factory = $this->relationFactories[$name] ??= $this->getRelatedFactory($name, $relation);

        return $database
            ? $factory->create($overrides, $database)
            : $factory->make($overrides);
    }

    /**
     * @param HasOne<Model>|HasMany<Model> $relation
     * @return ModelFactory<Model>
     */
    private function getRelatedFactory(string $name, HasOne|HasMany $relation): ModelFactory
    {
        $factory = $relation->toModel::factory()->withRelationTree($this->relationTree[$name]);

        return $this->seed === null
            ? $factory
            : $factory->withSeed(crc32($this->seed.$relation->toModel));
    }

    /** @param class-string<Model> $modelClass */
    protected function isLeftToTheDatabase(string $modelClass, ModelField $field): bool
    {
        return $field->autoIncrement
            || $field->isGenerated()
            || ($field->hasDefault && $field->name === $modelClass::primaryKey());
    }

    protected function fakeValue(ModelField $field, ?Database $database): mixed
    {
        if ($field->hasReference())
            return $this->fakeReference($field, $database);

        $faker = $field->isUnique 
            ? $this->faker()->unique()
            : $this->faker();

        return match ($field->type) {
            ModelField::INTEGER => $faker->numberBetween(1, 1000),
            ModelField::FLOAT => $faker->randomFloat(2, 0, 1000),
            ModelField::BOOLEAN => $faker->boolean(),
            ModelField::DECIMAL => $this->fakeDecimal($field),
            ModelField::DATE => $faker->dateTimeBetween('2000-01-01', '2030-12-31')->format('Y-m-d'),
            ModelField::DATETIME,
            ModelField::TIMESTAMP => $faker->dateTimeBetween('2000-01-01', '2030-12-31')->format('Y-m-d H:i:s'),
            default => mb_substr($faker->words(3, true), 0, $field->maximumLength ?? 50),
        };
    }

    protected function fakeReference(ModelField $field, ?Database $database): mixed
    {
        $referenced = $field->referenceModel;

        if (!$database || !is_string($referenced) || !Autoloader::extends($referenced, Model::class))
            return $this->faker()->numberBetween(1, 1000);

        return $referenced::factory()->create([], $database)->{$field->referenceField};
    }

    protected function fakeDecimal(ModelField $field): string
    {
        $decimals = $field->decimalDigitsToTheRight ?? 2;
        $integerDigits = ($field->decimalMaximumDigits ?? 10) - $decimals;

        $fakeFloat = $this->faker()->randomFloat($decimals, 0, min(10 ** $integerDigits - 1, 1_000_000));
        return number_format($fakeFloat, $decimals, '.', '');
    }
}

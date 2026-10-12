<?php

namespace Cube\Data\Models;

use Cube\Core\Autoloader;
use Cube\Data\Database\Database;
use Cube\Utils\Implementations;
use Faker\Factory;
use Faker\Generator;

/**
 * @template TModel of Model
 */
abstract class ModelFactory
{
    /** @var array<class-string<Model>,ModelFactory> */
    private static array $factories = [];

    private ?Generator $faker = null;

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
        return $this->build($overrides, null);
    }

    /**
     * @return TModel A saved model, the rows it references created first through their own factory
     */
    public function create(array $overrides = [], ?Database $database = null): Model
    {
        $database ??= Database::getInstance();

        return $this->build($overrides, $database)->save($database);
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
        $this->faker()->seed($seed);
        $this->faker()->unique(true);

        return $this;
    }

    protected function faker(): Generator
    {
        return $this->faker ??= Factory::create();
    }

    /** @return TModel */
    private function build(array $overrides, ?Database $database): Model
    {
        $modelClass = $this->getFactoredClass();
        $values = [...$this->definition($this->faker()), ...$overrides];

        foreach ($modelClass::fields() as $name => $field) {
            // A saved model leaves its key to the database, an example still needs one
            if (array_key_exists($name, $values) || ($database && $this->isLeftToTheDatabase($modelClass, $field)))
                continue;

            $values[$name] = $this->fakeValue($field, $database);
        }

        return new $modelClass($values);
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

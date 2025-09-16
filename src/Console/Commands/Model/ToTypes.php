<?php

namespace Cube\Console\Commands\Model;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Core\Autoloader;
use Cube\Data\Bunch;
use Cube\Env\Storage;
use Cube\Data\Models\Model;
use Cube\Data\Models\ModelField;
use Cube\Data\Models\Relations\HasMany;
use Cube\Data\Models\Relations\HasOne;
use Cube\Utils\Text;
use InvalidArgumentException;

class ToTypes extends Command
{
    public function getHelp(): string
    {
        return 'Generate Typescript Types from your models';
    }

    public function getScope(): string
    {
        return 'models';
    }

    public function getFieldTypeScript(ModelField $field): array
    {
        $type = match ($field->type) {
            ModelField::STRING => 'string',
            ModelField::INTEGER => 'number',
            ModelField::FLOAT => 'number',
            ModelField::BOOLEAN => 'boolean',
            ModelField::DECIMAL => 'number',
            ModelField::DATE => 'Date',
            ModelField::DATETIME => 'Date',
            ModelField::TIMESTAMP => 'Date',
        };

        $nullableStr = ($field->nullable && (!$field->autoIncrement)) ? '?' : '';

        return [$field->name, $field->name."{$nullableStr}: ".$type];
    }

    /**
     * @param class-string<Model> $model
     */
    public function getRelationTypeScript(string $relationName, string $model, HasMany|HasOne $relation): array
    {
        switch ($relation::class) {
            case HasOne::class:
                $nullableStr = $model::fields()[$relation->fromColumn]->nullable ? '?' : '';

                return [$relationName, $relationName."{$nullableStr}: ".$this->toPascalCase($relation->toModel::table())];

            case HasMany::class:
                return [$relationName, $relationName.'?: Array<'.$this->toPascalCase($relation->toModel::table()).'>'];
        }

        throw new InvalidArgumentException("Unsupported relation type " . $relation::class);
    }

    public function toPascalCase(string $string): string
    {
        return ucfirst(preg_replace_callback(
            '/([a-z])(?:_|-)([a-z])/',
            fn ($matches) => $matches[1].strtoupper($matches[2]),
            $string
        ));
    }

    /**
     * @param class-string<Model> $model
     */
    public function generateForModel(string $model): string
    {
        $typeName = $this->toPascalCase($model::table());

        echo "Type $typeName\n";

        $instance = new $model();

        $fields = Bunch::fromValues([
            ...Bunch::fromValues($model::fields())
                ->zip(fn ($field) => $this->getFieldTypeScript($field)),

            ...Bunch::of($model::relations())
                ->map(fn ($relation) => [$relation, $instance->{$relation}()])
                ->zip(fn ($relationObject) => $this->getRelationTypeScript($relationObject[0], $model, $relationObject[1])),
        ])
            ->join("\n\t")
        ;

        return Text::toFile("export type {$typeName} = {
            {$fields}
        }
        export type {$typeName}s = Array<{$typeName}>
        ");
    }

    public function execute(Args $args): int
    {
        $models = Bunch::of(Autoloader::classesThatExtends(Model::class))
            ->map(fn ($class) => $this->generateForModel($class))
            ->join("\n\n")
        ;

        Storage::getInstance()->write('cube-types.ts', $models);

        return 0;
    }
}

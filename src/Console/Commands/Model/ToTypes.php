<?php

namespace YonisSavary\Cube\Console\Commands\Model;

use YonisSavary\Cube\Console\Args;
use YonisSavary\Cube\Console\Command;
use YonisSavary\Cube\Core\Autoloader;
use YonisSavary\Cube\Data\Bunch;
use YonisSavary\Cube\Env\Storage;
use YonisSavary\Cube\Models\Model;
use YonisSavary\Cube\Models\ModelField;
use YonisSavary\Cube\Models\Relations\HasMany;
use YonisSavary\Cube\Models\Relations\HasOne;
use YonisSavary\Cube\Utils\Text;

class ToTypes extends Command
{
    public function getHelp(): string
    {
        return "Generate Typescript Types from your models";
    }

    public function getScope(): string
    {
        return 'models';
    }

    public function getFieldTypeScript(ModelField $field): array
    {
        $type = match($field->type) {
            ModelField::STRING    => "string",
            ModelField::INTEGER   => "number",
            ModelField::FLOAT     => "number",
            ModelField::BOOLEAN   => "boolean",
            ModelField::DECIMAL   => "number",
            ModelField::DATE      => "Date",
            ModelField::DATETIME  => "Date",
            ModelField::TIMESTAMP => "Date",
        };

        echo $field->name . " => " . ($field->nullable ? "1": "0") . "\n";

        $nullableStr = ($field->nullable && (!$field->autoIncrement)) ? "?": "";
        return [$field->name, $field->name . "$nullableStr: " . $type];
    }

    /**
     * @param class-string<Model> $model
     */
    public function getRelationTypeScript(string $relationName, string $model, HasMany|HasOne $relation): array
    {
        switch($relation::class)
        {
            case HasOne::class:
                $nullableStr = $model::fields()[$relation->fromColumn]->nullable ? "?": "";
                return [$relationName, $relationName . "$nullableStr: " . $this->toPascalCase($relation->toModel::table())];
            case HasMany::class:
                return [$relationName, $relationName . "?: Array<" . $this->toPascalCase($relation->toModel::table()) . ">"];
        }
    }

    public function toPascalCase(string $string): string
    {
        return ucfirst(preg_replace_callback(
            "/([a-z])(?:_|-)([a-z])/",
            fn($matches) => $matches[1] . strtoupper($matches[2])
        , $string));
    }

    /**
     * @param class-string<Model> $model
     */
    public function generateForModel(string $model): string
    {
        $typeName = $this->toPascalCase($model::table());

        $instance = new $model();

        $fields = Bunch::fromValues([
            ...Bunch::fromValues($model::fields())
            ->zip(fn($field) => $this->getFieldTypeScript($field)),

            ...Bunch::of($model::relations())
            ->map(fn($relation) => [$relation, $instance->$relation()])
            ->zip(fn($relationObject) => $this->getRelationTypeScript($relationObject[0], $model, $relationObject[1]))
        ])
        ->join("\n\t");

        return Text::toFile("type $typeName = {
            $fields
        }
        type {$typeName}s = Array<$typeName>
        ");
    }

    public function execute(Args $args): int
    {
        $models = Bunch::of(Autoloader::classesThatExtends(Model::class))
            ->map(fn($class) => $this->generateForModel($class))
            ->join("\n\n");

        Storage::getInstance()->write("types.d.ts", $models);
        return 0;
    }
}
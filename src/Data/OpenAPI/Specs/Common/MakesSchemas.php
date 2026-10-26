<?php

namespace Cube\Data\OpenAPI\Specs\Common;

use Cube\Core\Autoloader;
use Cube\Data\Models\Model;
use Cube\Data\Models\ModelField;
use Cube\Data\OpenAPI\OpenAPIGenerationContext;
use Cube\Utils\Utils;
use Cube\Web\Http\Request;
use Cube\Web\Http\Rules\AnyParam;
use Cube\Web\Http\Rules\ArrayParam;
use Cube\Web\Http\Rules\ObjectParam;
use Cube\Web\Http\Rules\Rule;
use Cube\Web\Router\Route;
use DateTime;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use stdClass;

trait MakesSchemas
{
    use ModelRef;

    private function mutateParameterWithMethodType(?string $type, array &$schema) {
        if (!$type)
            return;

        if (Autoloader::extends($type, Model::class)) {
            $schema = $this->getModelKeySchema($type);
            return;
        }

        $schema = match ($type) {
            'int'    => ['type' => 'integer'],
            'float'  => ['type' => 'number', 'format' => 'float'],
            'string' => ['type' => 'string'],
            'bool'   => ['type' => 'boolean'],
            default  => [],
        };
    }

    protected function mutateParameterWithSlugType(string $slugType, array &$schema) {
        $schema = match($slugType) {
            'int'      => ['type' => 'integer'],
            'float'    => ['type' => 'number', 'format' => 'float',],
            'any'      => [],
            'date'     => ['type' => 'string', 'format' => 'date'],
            'time'     => ['type' => 'string', 'pattern' => Route::SLUG_FORMATS['time']],
            'datetime' => ['type' => 'string', 'pattern' => Route::SLUG_FORMATS['datetime']],
            'hex'      => ['type' => 'string', 'pattern' => Route::SLUG_FORMATS['hex']],
            'uuid'     => ['type' => 'string', 'format' => 'uuid'],
            default    => ['type' => 'string', 'pattern' => $slugType]
        };
    }

    protected function mutateParameterWithRule(Rule $rule, array &$schema) {
        match (true) {
            $rule instanceof ObjectParam => $this->mutateParameterWithObjectRule($rule, $schema),
            $rule instanceof ArrayParam  => $this->mutateParameterWithArrayRule($rule, $schema),
            default                      => $this->mutateParameterWithValueRule($rule, $schema),
        };

        if (!$rule->isNullable() || !isset($schema['type']))
            return;

        $schema['type'] = [$schema['type'], 'null'];
        if (isset($schema['enum']))
            $schema['enum'][] = null;
    }

    private function mutateParameterWithArrayRule(ArrayParam $rule, array &$schema) {
        $schema['type'] = 'array';
        $schema['items'] = [];
        $this->mutateParameterWithRule($rule->getChildRule(), $schema['items']);
        $schema['items'] = $this->toEncodableSchema($schema['items']);
    }

    private function mutateParameterWithValueRule(Rule $rule, array &$schema) {
        $meta = $rule->getMetadata();

        if ($type = $meta[Rule::META_TYPE] ?? false)
            $schema = $this->getValueTypeSchema($type, $meta);

        if ($enum = $meta[Rule::META_ENUM] ?? false)
            $schema['enum'] = $enum;

        // OpenAPI only bounds numbers : isBetween() on a date or a string has no standard keyword
        if (!in_array($schema['type'] ?? '', ['number', 'integer']))
            return;

        if (null !== ($min = $meta[Rule::META_MIN] ?? null))
            $schema['minimum'] = $min;
        if (null !== ($max = $meta[Rule::META_MAX] ?? null))
            $schema['maximum'] = $max;
    }

    private function getValueTypeSchema(string $type, array $meta): array {
        return match ($type) {
            'model'    => $this->getModelKeySchema($meta[Rule::META_MODEL]),
            'integer'  => ['type' => 'integer'],
            'float'    => ['type' => 'number', 'format' => 'float',],
            'any'      => [],
            'string'   => ['type' => 'string'],
            'email'    => ['type' => 'string', 'format' => 'email'],
            'boolean'  => ['type' => 'boolean'],
            'date'     => ['type' => 'string', 'format' => 'date'],
            'time'     => ['type' => 'string', 'pattern' => Route::SLUG_FORMATS['time']],
            'date-time'=> ['type' => 'string', 'pattern' => Route::SLUG_FORMATS['datetime']],
            'hex'      => ['type' => 'string', 'pattern' => Route::SLUG_FORMATS['hex']],
            'uuid'     => ['type' => 'string', 'format' => 'uuid'],
        };
    }

    private function mutateParameterWithObjectRule(ObjectParam $rule, array &$schema) {
        $schema['type'] = 'object';
        $schema['properties'] ??= [];
        $required = [];

        foreach ($rule->getRules() as $key => $subrule) {
            $schema['properties'][$key] = [];
            $this->mutateParameterWithRule($subrule, $schema['properties'][$key]);
            $schema['properties'][$key] = $this->toEncodableSchema($schema['properties'][$key]);

            if (!$subrule->isOptional())
                $required[] = $key;
        }

        $schema['properties'] = $this->toEncodableSchema($schema['properties']);
        if (count($required))
            $schema['required'] = $required;
    }

    // json_encode writes an empty array as [], but an empty schema must be the object {}
    /**
     * @param array<string,mixed> $schema
     * @return array<string,mixed>|stdClass
     */
    protected function toEncodableSchema(array $schema): array|stdClass
    {
        return $schema ?: new stdClass();
    }

    /**
     * @param class-string<Model> $modelClass
     */
    protected function getModelKeySchema(string $modelClass): array {
        if (! $primaryKey = $modelClass::primaryKey())
            return [];

        /** @var ModelField|false $primaryField */
        $primaryField = $modelClass::fields()[$primaryKey] ?? false;
        if (! $primaryField)
            return [];

        $schema = [];
        $this->mutateParameterWithRule($primaryField->toRule(false), $schema);
        return $schema;
    }

    protected function mutateParameterFromRawData(mixed $data, array &$schema) {
        if ($data === []) {
            OpenAPIGenerationContext::getInstance()->log(" - Warning: used empty array data type on parameter");
            $schema = ['type' => 'array'];
        } else if (is_array($data) && Utils::isAssoc($data)) {
            $schema['type'] = 'object';
            $schema['properties'] ??= [];
            foreach ($data as $key => $subvalue) {
                $schema['properties'][$key] = [];
                $this->mutateParameterFromRawData($subvalue, $schema['properties'][$key]);
                $schema['properties'][$key] = $this->toEncodableSchema($schema['properties'][$key]);
            }
        } else if (is_array($data)) {
            $schema['type'] = 'array';
            $schema['items'] = [];
            $this->mutateParameterFromRawData($data[0], $schema['items']);
            $schema['items'] = $this->toEncodableSchema($schema['items']);
        } else if (is_string($data) && Autoloader::extends($data, Model::class)) {
            $schema = ['$ref' => $this->getRefForClass($data)];
        } else if (is_string($data)) {
            $schema = ['type' => 'string'];
        } else if (is_float($data)) {
            $schema = ['type' => 'number', 'format' => 'float',];
        } else if (is_int($data)) {
            $schema = ['type' => 'integer'];
        } else if (is_bool($data)) {
            $schema = ['type' => 'boolean'];
        } else if ($data instanceof DateTime) {
            $schema = ['type' => 'string', 'format' => 'date'];
        } else {
            OpenAPIGenerationContext::getInstance()->log(" - Warning: used 'any' data type on parameter");
            $schema = [];
        }
    }

    protected function getRequestRules(Route $route): ?Rule
    {
        $requestParameter = $route->getReflectionMethod()->getParameters()[0] ?? null;
        if (!$requestParameter)
            return null;

        $requestType = $this->getReflectionTypeName($requestParameter->getType());
        if (!$requestType || !Autoloader::extends($requestType, Request::class))
            return null;

        /** @var class-string<Request> $requestType */
        $rules = (new $requestType())->getObjectParam();
        return $rules instanceof AnyParam ? null : $rules;
    }

    protected function getReflectionTypeName(ReflectionNamedType|ReflectionUnionType|ReflectionIntersectionType|null $type): ?string
    {
        if (!$type)
            return null;

        if ($type instanceof ReflectionUnionType) {
            $type = $type->getTypes()[0];
        }
        else if ($type instanceof ReflectionIntersectionType) {
            $type = $type->getTypes()[0];
        }

        if (!$type instanceof ReflectionNamedType) {
            return null;
        }

        return $type->getName();
    }

}
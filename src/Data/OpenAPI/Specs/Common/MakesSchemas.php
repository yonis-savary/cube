<?php

namespace Cube\Data\OpenAPI\Specs\Common;

use Cube\Core\Autoloader;
use Cube\Data\Models\Model;
use Cube\Data\Models\ModelField;
use Cube\Data\OpenAPI\OpenAPIGenerationContext;
use Cube\Utils\Utils;
use Cube\Web\Http\Rules\ArrayParam;
use Cube\Web\Http\Rules\ObjectParam;
use Cube\Web\Http\Rules\Rule;
use DateTime;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;

trait MakesSchemas
{
    private function mutateParameterWithMethodType(?string $type, array &$schema) {
        if (!$type)
            return;

        if (Autoloader::extends($type, Model::class)) {
            $schema ??= [];
            $this->mutateParameterForModel($type, $schema);
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
            'time'     => ['type' => 'integer', 'pattern' => '\d{2}\-\d{2}\-\d{2}'],
            'datetime' => ['type' => 'string', 'format' => 'date-time'],
            'hex'      => ['type' => 'integer', 'pattern' => '[a-f0-9]+'],
            'uuid'     => ['type' => 'integer', 'format' => 'uuid'],
            default    => ['type' => 'string', 'pattern' => $slugType]
        };
    }

    protected function mutateParameterWithRule(Rule $rule, array &$schema) {
        if ($rule instanceof ObjectParam) {
            $schema['type'] = 'object';
            $schema['properties'] ??= [];
            foreach ($rule->getRules() as $key => $subrule) {
                $schema['properties'][$key] = [];
                $this->mutateParameterWithRule($subrule, $schema['properties'][$key]);
            }
        }
        else if ($rule instanceof ArrayParam) {
            $schema['type'] = 'array';
            $schema['items'] = [];
            $this->mutateParameterWithRule(
                $rule->getChildRule(),
                $schema['items']
            );
        }

        $meta = $rule->getMetadata();
        $type = $meta[Rule::META_TYPE] ?? false;
        if (!$type) {
            return;
        }

        if ($type === 'model') {
            $model = $meta[Rule::META_MODEL] ?? false;
            if (!$model)
                return;

            $this->mutateParameterForModel($model, $schema);
        }

        $schema = match ($type) {
            'integer'  => ['type' => 'integer'],
            'float'    => ['type' => 'number', 'format' => 'float',],
            'any'      => [],
            'string'   => ['type' => 'string'],
            'email'    => ['type' => 'email'],
            'boolean'  => ['type' => 'boolean'],
            'date'     => ['type' => 'string', 'format' => 'date'],
            'time'     => ['type' => 'integer', 'pattern' => '\d{2}\-\d{2}\-\d{2}'],
            'date-time'=> ['type' => 'string', 'format' => 'date-time'],
            'hex'      => ['type' => 'integer', 'pattern' => '[a-f0-9]+'],
            'uuid'     => ['type' => 'integer', 'format' => 'uuid'],
        };

        if (in_array($schema['type'] ?? '', ['number', 'integer'])) {
            if ($min = $meta[Rule::META_MIN] ?? false)
                $schema['minimum'] = $min;
            if ($max = $meta[Rule::META_MAX] ?? false)
                $schema['maximum'] = $max;
        } else {
            if ($min = $meta[Rule::META_MIN] ?? false)
                $schema['format_minimum'] = $min;
            if ($max = $meta[Rule::META_MAX] ?? false)
                $schema['format_maximum'] = $max;
        }


        if ($enum = $meta[Rule::META_ENUM] ?? false)
            $schema['enum'] = $enum;
    }

    /**
     * @param class-string<Model> $modelClass
     */
    protected function mutateParameterForModel(string $modelClass, array &$schema) {

        if (! $primaryKey = $modelClass::primaryKey())
            return;

        /** @var ModelField $primaryField */
        $primaryField = $modelClass::fields()[$primaryKey] ?? false;
        if (! $primaryField)
            return;

        $rule = $primaryField->toRule();
        return $this->mutateParameterWithRule($rule, $schema);
    }

    protected function mutateParameterFromRawData(mixed $data, array &$schema) {
        if (is_array($data)) {
            if (empty($data)) {
                OpenAPIGenerationContext::getInstance()->log(" - Warning: used empty array data type on parameter");
                $schema['type'] = 'array';
            }
            if (Utils::isAssoc($data))
            {
                $schema['type'] = 'object';
                $schema['properties'] ??= [];
                foreach ($data as $key => $subvalue) {
                    $schema['properties'][$key] = [];
                    $this->mutateParameterFromRawData($subvalue, $schema['properties'][$key]);
                }
            }
            else
            {
                $schema['type'] = 'array';
                $schema['items'] = [ [] ];
                $this->mutateParameterFromRawData($data[0], $schema['items'][0]);
            }
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
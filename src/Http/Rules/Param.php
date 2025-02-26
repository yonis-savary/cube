<?php

namespace Cube\Http\Rules;

use Cube\Core\Autoloader;
use Cube\Database\Database;
use Cube\Http\Request;
use Cube\Models\Model;
use Cube\Utils\Text;
use Cube\Utils\Utils;

class Param extends Rule
{
    protected mixed $value;

    public function __construct(bool $nullable = true)
    {
        if (!$nullable) {
            $this->withCondition(fn (mixed $value) => null !== $value, '{key} cannot be null');
        }
    }

    /**
     * Convert a number/string into an integer.
     */
    public static function integer(bool $nullable = true): self
    {
        return (new self($nullable))
            ->withValueCondition(fn ($value) => is_numeric($value), '{key} must be an integer, got {value}')
            ->withValueTransformer(fn ($value) => (int) $value)
        ;
    }

    /**
     * Convert a number/string into a float.
     */
    public static function float(bool $nullable = true): self
    {
        return (new self($nullable))
            ->withValueCondition(fn ($value) => is_numeric($value), '{key} must be a float, got {value}')
            ->withValueTransformer(fn ($value) => (float) $value)
        ;
    }

    public static function string(bool $trim = true, bool $nullable = true): self
    {
        $object = new self($nullable);

        if ($trim) {
            $object->withValueTransformer(fn ($x) => trim($x));
        }

        return $object;
    }

    public static function array(Rule|Validator $childValidator, bool $nullable = true): self
    {
        $childValidator = Validator::from($childValidator);

        $object = new self($nullable);

        $object->withSubValidator($childValidator);

        return $object;
    }

    public static function object(bool $nullable = true): self
    {
        $rule = new self($nullable);

        return $rule->withValueCondition(fn ($array) => Utils::isAssoc($array), '{key} must be an object, got {value}');
    }

    /**
     * Accept any email through filter_var().
     */
    public static function email(bool $nullable = true): self
    {
        return (new self($nullable))
            ->withValueCondition(fn ($value) => false !== filter_var($value, FILTER_VALIDATE_EMAIL), '{key} must be an email, got {value}')
        ;
    }

    /**
     * Accept any boolean (`"on"`, `"true"`, `"yes"`, `"1"`, `true` are considered `true`, any other value is `false`).
     */
    public static function boolean(bool $nullable = true): self
    {
        return (new self($nullable))
            ->withTransformer(fn (string $value) => is_bool($value) ? $value : in_array((string) $value, ['on', 'true', 'yes', '1']))
        ;
    }

    /**
     * Accept any url through filter_var().
     */
    public static function url(bool $nullable = true): self
    {
        return (new self($nullable))
            ->withValueCondition(fn ($value) => false !== filter_var($value, FILTER_VALIDATE_URL), '{key} must be an URL, got {value}')
        ;
    }

    /**
     * Accept any date with YYYY-MM-DD format.
     */
    public static function date(bool $nullable = true): self
    {
        return (new self($nullable))
            ->withValueCondition(fn (?string $value) => null === $value || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value ?? ''), '{key} must be a Date (yyyy-mm-dd), got {value}')
        ;
    }

    /**
     * Accept any date with YYYY-MM-DD HH:mm:ss format.
     */
    public static function datetime(bool $nullable = true): self
    {
        return (new self($nullable))
            ->withValueCondition(fn (string $value) => preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ?? ''), '{key} must be a datetime value (yyyy-mm-dd HH:MM:SS), got {value}')
        ;
    }

    /**
     * Accept any uuid value as xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx (8,4,4,4,12) with x any hexadecimal value.
     */
    public static function uuid(bool $nullable = true): self
    {
        return (new self($nullable))
            ->withValueCondition(fn (string $value) => preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value ?? ''), '{key} must be an UUID, got {value}')
        ;
    }

    /**
     * Fetch a model from its primary key value.
     */
    public static function model(string $modelClass, bool $explore = true, ?Database $database = null): self
    {
        if (!Autoloader::extends($modelClass, Model::class)) {
            throw new \InvalidArgumentException('$modelClass must extends Model');
        }

        // @var Model $modelClass
        return (new self())
            ->withValueTransformer(fn ($primaryKey) => $modelClass::find($primaryKey, $explore, $database))
            ->withCondition(
                fn (?Model $value) => null !== $value,
                Text::interpolate('{key} must be a valid id (or primary key value) in table {table}, got {value}', ['table' => $modelClass::table()])
            )
        ;
    }

    /**
     * Check if a value is included in a specified array.
     */
    public function inArray(array $array): self
    {
        return $this->withCondition(
            fn ($value) => in_array($value, $array),
            Text::interpolate('{key} must be in values {array}, got {value}', ['array' => join(',', $array)])
        );
    }

    /**
     * Check if the value is between limits.
     *
     * @param mixed $min
     * @param mixed $max
     */
    public function isBetween($min, $max, bool $canBeEqual = true): self
    {
        return $canBeEqual
            ? $this->withValueCondition(fn ($value) => $min <= $value && $value <= $max, "{key} must be between {$min} and {$max} (can be equal), got {value}")
            : $this->withValueCondition(fn ($value) => $min < $value && $value < $max, "{key} must be between {$min} and {$max} (cannot be equal), got {value}");
    }

    /**
     * Check if the value exists in a table as primary key.
     */
    public function isInTable(string $modelClass, ?Database $database = null)
    {
        if (!Autoloader::extends($modelClass, Model::class)) {
            throw new \InvalidArgumentException('$modelClass must extends Model');
        }

        // @var Model $modelClass
        return $this->withValueCondition(
            fn ($value) => $modelClass::exists($value, $database),
            Text::interpolate('{key} must be a valid id in table {table}, got {value}', ['table' => $modelClass::table()])
        );
    }

    public function validateRequest(Request $request, string $name): bool
    {
        $currentValue = $request->param($name);

        return $this->validateWithSteps($currentValue);
    }

    public function withSubValidator(Validator $validator): self
    {
        /** @var array|true $rule */
        $validatorResponse = null;

        return $this->withValueCondition(
            function (mixed $value) use (&$validator, &$validatorResponse) {
                if (Utils::isList($value)) {
                    $validatorResponse = $validator->validateArray($value);
                } else {
                    $validatorResponse = $validator->validateValue($value);
                }

                return true === $validatorResponse;
            },
            function () use (&$validatorResponse) {
                return $validatorResponse;
            }
        );
    }
}

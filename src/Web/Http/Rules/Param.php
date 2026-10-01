<?php

namespace Cube\Web\Http\Rules;

use Cube\Core\Autoloader;
use Cube\Data\Database\Database;
use Cube\Web\Http\Rules\Rule;
use Cube\Data\Models\Model;
use Cube\Data\Models\ModelField;
use Cube\Utils\Text;
use InvalidArgumentException;

class Param extends Rule
{
    protected mixed $value;

    public static function from(Rule|array $rule, bool $nullable=false): ObjectParam|ArrayParam|Param
    {
        if ($rule instanceof Rule)
            return $rule;

        return static::object($rule, $nullable);
    }

    public function __construct(bool $nullable = false)
    {
        $this->nullable = $nullable;
        $this->withCondition(fn(mixed $value) => $this->nullable || (null !== $value), '{key} cannot be null');
    }

    /**
     * Accept an integer, or a string writing one in decimal without spaces, inside PHP's integer range.
     */
    public static function integer(bool $nullable = false): static
    {
        return (new static($nullable))
            ->withValueCondition(
                fn ($value) => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) && false !== filter_var($value, FILTER_VALIDATE_INT)),
                '{key} must be an integer, got {value}'
            )
            ->withValueTransformer(fn ($value) => (int) $value)
            ->withMetadata([self::META_TYPE => 'integer'])
        ;
    }

    /**
     * Convert a number/string into a float.
     */
    public static function float(bool $nullable = false): static
    {
        return (new static($nullable))
            ->withValueCondition(fn ($value) => is_numeric($value), '{key} must be a float, got {value}')
            ->withValueTransformer(fn ($value) => is_numeric($value) ? (float) $value : $value)
            ->withMetadata([self::META_TYPE => 'float'])
        ;
    }

    public static function string(bool $trim = true, bool $nullable = false): static
    {
        $object = new static($nullable);

        $object->withValueCondition(fn ($value) => is_scalar($value) || $value instanceof \Stringable, '{key} must be a string, got {value}');

        if ($trim) {
            $object->withValueTransformer(fn ($value) => is_string($value) ? trim($value) : $value);
        }

        return $object->withMetadata([self::META_TYPE => 'string']);
    }

    public static function array(Rule|array $childRule, bool $nullable = false): ArrayParam
    {
        return new ArrayParam($childRule, $nullable);
    }

    public static function object(array $rules=[], bool $nullable = false): ObjectParam
    {
        return new ObjectParam($rules, $nullable);
    }

    public static function anyKeyObject(Rule|array $valueRule, bool $nullable = false): AnyKeyObjectParam
    {
        return new AnyKeyObjectParam($valueRule, $nullable);
    }

    /**
     * Accept any email through filter_var().
     */
    public static function email(bool $nullable = false): static
    {
        return (new static($nullable))
            ->withValueCondition(fn ($value) => false !== filter_var($value, FILTER_VALIDATE_EMAIL), '{key} must be an email, got {value}')
            ->withMetadata([self::META_TYPE => 'email'])
        ;
    }

    /**
     * Accept any boolean (`"on"`, `"true"`, `"yes"`, `"1"`, `true` are considered `true`, any other value is `false`).
     */
    public static function boolean(bool $nullable = false): static
    {
        return (new static($nullable))
            ->withValueCondition(fn ($value) => is_scalar($value), '{key} must be a boolean, got {value}')
            ->withValueTransformer(fn ($value) => is_bool($value) ? $value : in_array(strtolower((string) $value), ['on', 'true', 'yes', '1']))
            ->withMetadata([self::META_TYPE => 'boolean'])
        ;
    }

    /**
     * Accept any url through filter_var().
     */
    public static function url(bool $nullable = false): static
    {
        return (new static($nullable))
            ->withValueCondition(fn (mixed $value) => is_string($value) && preg_match('/^(.+?:\/\/)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&\/\/=]*)$/', $value ?? ''), '{key} must be an URL, got {value}')
            ->withMetadata([self::META_TYPE => 'string'])
        ;
    }

    /**
     * Accept any date with YYYY-MM-DD format.
     */
    public static function date(bool $nullable = false): static
    {
        return (new static($nullable))
            ->withValueCondition(function (mixed $value){
                if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches))
                    return false;

                list($_, $y, $m, $d) = $matches;
                return checkdate((int) $m, (int) $d, (int) $y);
            }, '{key} must be a Date (yyyy-mm-dd, an existing day), got [{value}]')
            ->withMetadata([self::META_TYPE => 'date'])
        ;
    }

    /**
     * Accept any date with YYYY-MM-DD HH:mm:ss format.
     * @param bool $addTimeIfMissing If `true`, will add `00:00:00` if timestamp is missing
     */
    public static function datetime(bool $nullable = false, bool $addTimeIfMissing=false): static
    {
        $rule = (new static($nullable));

        if ($addTimeIfMissing)
            $rule->withValueTransformer(function($value) {
                if (!is_string($value) || preg_match('/(\d{2}):(\d{2}):(\d{2})$/', $value))
                    return $value;

                return $value . " 00:00:00";
            });

        return $rule->withValueCondition(function (mixed $value){
            if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/', $value, $matches))
                return false;

            list($_, $y, $m, $d, $h, $mm, $s) = $matches;
            return
                checkdate((int) $m, (int) $d, (int) $y) &&
                (00 <= $h && $h<= 23) &&
                (00 <= $mm && $mm <= 59) &&
                (00 <= $s && $s <= 59);
        }, 
        '{key} must be a datetime value (yyyy-mm-dd HH:MM:SS, an existing day, HH=00-23, MM=00-59, SS=00-59), got [{value}]'
        )
            ->withMetadata([self::META_TYPE => 'date-time'])
        ;
    }

    /**
     * Accept any uuid value as xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx (8,4,4,4,12) with x any hexadecimal value.
     */
    public static function uuid(bool $nullable = false): static
    {
        return (new static($nullable))
            ->withValueCondition(
                fn($value)=> is_string($value) && (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', strtolower($value)),
                '{key} must be an UUID, got [{value}]'
            )
            ->withMetadata([self::META_TYPE => 'uuid'])
        ;
    }


    /**
     * @param class-string<Model> $modelClass
     */
    public static function model(
        string $modelClass,
        ?string $column=null,
        bool $nullable = false,
        array $with=[],
        ?Database $database=null
    ): Rule
    {
        $column ??= $modelClass::primaryKey();
        if (!$column) {
            throw new InvalidArgumentException('No $column given nor primary key in model');
        }

        /** @var ModelField|false $modelField */
        $modelField = $modelClass::fields()[$column] ?? false;
        if (!$modelField) {
            throw new InvalidArgumentException("Column $column not found");
        }

        $database ??= Database::getInstance();
        return $modelField->toRule($nullable)
            ->withValueTransformer(fn ($primaryKey) => $modelClass::findWhere([$column => $primaryKey], $with, $database))
            ->withCondition(
                fn (?Model $value) => (null !== $value) || $nullable,
                Text::interpolate('{key} must be a valid {column} in table {table}, got {value}', ['table' => $modelClass::table(), 'column' => $column])
            )
            ->withMetadata([self::META_TYPE => 'model', self::META_MODEL => $modelClass])
        ;
    }

    /**
     * Check if a value is included in a specified array.
     */
    public function inArray(array $array): static
    {
        return $this->withValueCondition(
            fn ($value) => in_array($value, $array),
            Text::interpolate('{key} must be in values {array}, got {value}', ['array' => join(',', $array)])
        )
        ->withMetadata([self::META_TYPE => 'string', self::META_ENUM => $array]);
    }

    /**
     * Check if the value is between limits.
     *
     * @param mixed $min
     * @param mixed $max
     */
    public function isBetween($min, $max, bool $canBeEqual = true): static
    {
        return ($canBeEqual
            ? $this->withValueCondition(fn ($value) => $min <= $value && $value <= $max, "{key} must be between {$min} and {$max} (can be equal), got {value}")
            : $this->withValueCondition(fn ($value) => $min < $value && $value < $max, "{key} must be between {$min} and {$max} (cannot be equal), got {value}")
        )
        ->withMetadata([self::META_MIN => $min, self::META_MAX => $max]);
    }


    /**
     * Check if the value exists in a table as primary key (or given column).
     * @param class-string<Model> $modelClass
     */
    public static function exists(
        string $modelClass,
        ?string $column=null,
        bool $nullable = false,
        ?Database $database=null
    ): Rule
    {
        $column ??= $modelClass::primaryKey();
        if (!$column) {
            throw new InvalidArgumentException('No $column given nor primary key in model');
        }

        /** @var ModelField|false $modelField */
        $modelField = $modelClass::fields()[$column] ?? false;
        if (!$modelField) {
            throw new InvalidArgumentException("Column $column not found");
        }

        $database ??= Database::getInstance();
        return $modelField->toRule($nullable)
            ->withValueCondition(
                function (mixed $columnValue) use ($modelClass, $column, $database) {
                    $result = $modelClass::findWhere([$column => $columnValue], [], $database);
                    return $result !== null;
                },
                Text::interpolate('{key} must be a valid {column} in table {table}, got {value}', ['table' => $modelClass::table(), 'column' => $column])
            )
            ->withMetadata([self::META_TYPE => 'model', self::META_MODEL => $modelClass])
        ;
    }
}

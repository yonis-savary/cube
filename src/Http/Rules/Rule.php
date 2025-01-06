<?php

namespace YonisSavary\Cube\Http\Rules;

use InvalidArgumentException;
use YonisSavary\Cube\Core\Autoloader;
use YonisSavary\Cube\Database\Database;
use YonisSavary\Cube\Models\Model;

class Rule extends AbstractRule
{

    const TYPE_CHECKER = "check";
    const TYPE_TRANSFORMER = "transform";

    protected array $errorMessages = [];
    protected array $steps = [];

    protected mixed $value;

    /**
     * Convert a number/string into an integer
     */
    public static function integer(): self
    {
        return (new self)
            ->withCondition(fn($value) => preg_match("/^[0-9]+$/", $value), "Value must be an integer")
            ->withTransformer(fn($value) => (int) $value);
    }

    /**
     * Convert a number/string into a float
     */
    public static function float(): self
    {
        return (new self)
            ->withCondition(fn($value) => preg_match("/^[0-9\.,]$/", $value), "Value must be a float")
            ->withTransformer(fn($value) => (float) $value);
    }

    /**
     * Accept any value (if `$acceptNull` is true, `null` is converted to `""`)
     */
    public static function string(bool $trim=true, bool $acceptNull=true): self
    {
        $object = new self;

        if ($acceptNull)
            $object->withTransformer(fn($x) => (string) $x);
        else
            $object->withCondition(fn($x) => $x !== null, "Value must be a string");

        if ($trim)
            $object->withTransformer(fn(string $x) => trim($x));

        return $object;
    }

    /**
     * Accept any email through filter_var()
     */
    public static function email(): self
    {
        return (new self)->withCondition(fn($value) => false !== filter_var($value, FILTER_VALIDATE_EMAIL), "Value must be an email");
    }

    /**
     * Accept any boolean (`"on"`, `"true"`, `"yes"`, `"1"`, `true` are considered `true`, any other value is `false`)
     */
    public static function boolean(): self
    {
        return (new self)->withTransformer(fn(string $value) => is_bool($value) ? $value : in_array((string) $value, ["on", "true", "yes", "1"]));
    }

    /**
     * Accept any url through filter_var()
     */
    public static function url(): self
    {
        return (new self)->withCondition(fn($value) => false !== filter_var($value, FILTER_VALIDATE_URL), "Value must be an URL");
    }

    /**
     * Accept any date with YYYY-MM-DD format
     */
    public static function date(): self
    {
        return (new self)
            ->withCondition(fn(string $value) => preg_match("/^\d{4}-\d{2}-\d{2}$/", $value ?? ''), "Value must be a Date (yyyy-mm-dd)");
    }

    /**
     * Accept any date with YYYY-MM-DD HH:mm:ss format
     */
    public static function datetime(): self
    {
        return (new self)
            ->withCondition(fn(string $value) => preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/", $value ?? ''), "Value must be a datetime value (yyyy-mm-dd HH:MM:SS)");
    }

    /**
     * Accept any uuid value as xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx (8,4,4,4,12) with x any hexadecimal value
     */
    public static function uuid(): self
    {
        return (new self)
            ->withCondition(fn(string $value) => preg_match("/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/", $value ?? ''), "Value must be an UUID");
    }

    /**
     * Fetch a model from its primary key value
     */
    public static function model(string $modelClass, bool $explore=true, Database $database=null): self
    {
        if (!Autoloader::extends($modelClass, Model::class))
            throw new InvalidArgumentException("\$modelClass must extends Model");

        /** @var Model $modelClass */
        return (new self)
            ->withCondition(fn($value) => $modelClass::exists($value, $database), "Value must be a valid id in table " . $modelClass::table())
            ->withTransformer(fn($primaryKey) => $modelClass::find($primaryKey, $explore, $database))
        ;
    }

    /**
     * Check if a value is included in a specified array
     */
    public function inArray(array $array): self
    {
        return $this->withCondition(fn($value) => in_array($value, $array), "Value must be in values (".join(",", $array).")");
    }


    /**
     * Check if the value is between limits
     */
    public function isBetween($min, $max, bool $canBeEqual=true): self
    {
        return $canBeEqual ?
            $this->withCondition(fn($value) => $min <= $value && $value <= $max, "Value must be between $min and $max (can be equal)"):
            $this->withCondition(fn($value) => $min < $value && $value < $max, "Value must be between $min and $max (cannot be equal)");
    }


    /**
     * Check if the value exists in a table as primary key
     */
    public function isInTable(string $modelClass, Database $database=null)
    {
        if (!Autoloader::extends($modelClass, Model::class))
            throw new InvalidArgumentException("\$modelClass must extends Model");

        /** @var Model $modelClass */
        return $this->withCondition(fn($value) => $modelClass::exists($value, $database), "Value must be a valid id in table " . $modelClass::table());
    }

    /**
     * Add a condition to the Validator, if the callback return `true`, it is considered as valid,
     * otherwise the errorMessage will be displayed to the user
     */
    public function withCondition(callable $callback, string $errorMessage): self
    {
        $this->steps[] = [self::TYPE_CHECKER, $callback, $errorMessage];
        return $this;
    }

    /**
     * Add a transform step that can be used to edit the value between conditions and/or other transformers
     */
    public function withTransformer(callable $callback): self
    {
        $this->steps[] = [self::TYPE_TRANSFORMER, $callback];
        return $this;
    }

    public function getErrorMessages(): array
    {
        return $this->errorMessages;
    }

    public function validate(mixed $value): bool
    {
        $currentValue = $value;
        $isValid = true;

        foreach ($this->steps as $step)
        {
            $type = $step[0];

            if ($type === self::TYPE_CHECKER)
            {
                $callback = $step[1];
                $errorMessage = $step[2];

                $stepValidity = (bool) ($callback($currentValue));
                $isValid &= $stepValidity;

                if (!$stepValidity)
                    $this->errorMessages[] = $errorMessage;
            }
            else if ($type === self::TYPE_TRANSFORMER)
            {
                $callback = $step[1];
                $currentValue = ($callback)($currentValue);
            }
        }

        $this->setValue($currentValue);

        return $isValid;
    }
}
<?php

namespace YonisSavary\Cube\Console;

use YonisSavary\Cube\Data\Bunch;

class Args
{
    protected array $values = [];

    public static function fromArgv(array $argv): self
    {
        $args = new Args;
        $currentArg = null;

        foreach ($argv as $arg)
        {
            if (str_starts_with($arg, "-"))
                $currentArg = $arg;
            else
                $args->addValue($currentArg, $arg);
        }

        return $args;
    }


    public function dump(): array
    {
        return $this->values;
    }

    public function toString(): string
    {
        $string = "";

        foreach ($this->values as $param => $values)
            $string .= $param . " " . join(" ", $values);

        return $string;
    }

    public function addValue(?string $parameter, string $value): self
    {
        $this->values[$parameter] ??= [];
        $this->values[$parameter][] = $value;

        return $this;
    }

    protected function makeStartWith(string &$param, string $mustStartWith): void
    {
        if (!str_starts_with($param, $mustStartWith))
            $param = $mustStartWith . $param;
    }

    public function has(string $short, string $long): bool
    {
        $this->makeStartWith($short, "-");
        $this->makeStartWith($long, "--");

        return array_key_exists($short, $this->values) ||
            array_key_exists($long, $this->values);
    }

    public function getValues(string $short=null, string $long=null): array
    {
        $this->makeStartWith($short, "-");
        $this->makeStartWith($long, "--");

        return array_merge(
            $this->values[$short] ?? [],
            $this->values[$long] ?? [],
        );
    }
    public function getValue(string $short=null, string $long=null, mixed $default=null): mixed
    {
        return $this->getValues($short, $long) ?? $default;
    }
}
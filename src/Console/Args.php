<?php

namespace YonisSavary\Cube\Console;

use YonisSavary\Cube\Utils\Text;

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
            {
                if (str_contains($arg, "="))
                {
                    list($param, $value) = explode("=", $arg);

                    $args->addValue($param, $value);
                    $currentArg = null;
                }
                else
                {
                    $currentArg = $arg;
                    $args->addParameter($arg);
                }
            }
            else
            {
                $args->addValue($currentArg, $arg);
            }
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

    public function addParameter(?string $parameter): void
    {
        $this->values[$parameter] ??= [];
    }

    public function addValue(?string $parameter, string $value): self
    {
        $this->values[$parameter] ??= [];
        $this->values[$parameter][] = $value;

        return $this;
    }

    public function has(?string $short=null, ?string $long=null): bool
    {
        $short = Text::startsWith($short ?? "", "-");
        $long = Text::startsWith($long ?? "", "--");

        return array_key_exists($short, $this->values) ||
            array_key_exists($long, $this->values);
    }

    public function getValues(?string $short=null, ?string $long=null): array
    {
        if ($short === null && $long === null)
            return $this->values[null] ?? [];

        $short ??= "";
        $long ??= "";

        $short = Text::startsWith($short, "-");
        $long = Text::startsWith($long, "--");

        return array_merge(
            $this->values[$short] ?? [],
            $this->values[$long] ?? [],
        );
    }
    public function getValue(?string $short=null, ?string $long=null, mixed $default=null): mixed
    {
        return ($this->getValues($short, $long)[0]) ?? $default;
    }
}
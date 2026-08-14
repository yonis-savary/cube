<?php

namespace Cube\Console;

use Cube\Data\Bunch;
use Cube\Utils\Text;

class Args
{
    protected array $values = [];

    public static function fromArgv(array $argv): self
    {
        $args = new Args();
        $currentArg = null;
        $locked = false;

        foreach ($argv as $arg) {
            if ($arg === '--') {
                $locked = true;
                $currentArg = null;
                continue;
            }

            if ($locked) {
                $args->addValue($currentArg, $arg);
                continue;
            }

            if (str_starts_with($arg, '-') && $arg !== '-' ) {
                if (str_contains($arg, '=')) {
                    list($param, $value) = explode('=', $arg, 2);

                    $args->addValue($param, $value);
                    $currentArg = null;
                } else {
                    $currentArg = $arg;
                    $args->addParameter($arg);
                }
            } else {
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
        return Bunch::unzip($this->values)
        ->map(fn($pair) =>  ($pair[0] ? ($pair[0] . " ") : '') . join(' ', $pair[1]) )
        ->join(' ');
    }

    public function addParameter(?string $parameter): void
    {
        $parameter ??= '';
        $this->values[$parameter] ??= [];
    }

    public function addValue(?string $parameter, string $value): self
    {
        $parameter ??= '';
        $this->values[$parameter] ??= [];
        $this->values[$parameter][] = $value;

        return $this;
    }

    public function has(?string $short = null, ?string $long = null): bool
    {
        $short = Text::startsWith($short ?? '', '-');
        $long = Text::startsWith($long ?? '', '--');

        return array_key_exists($short, $this->values)
            || array_key_exists($long, $this->values);
    }

    public function getValues(?string $short = null, ?string $long = null): array
    {
        if (null === $short && null === $long) {
            return $this->values[''] ?? [];
        }

        $short ??= '';
        $long ??= '';

        $short = Text::startsWith($short, '-');
        $long = Text::startsWith($long, '--');

        return array_merge(
            $this->values[$short] ?? [],
            $this->values[$long] ?? [],
        );
    }

    public function getValue(?string $short = null, ?string $long = null, mixed $default = null): mixed
    {
        return $this->getValues($short, $long)[0] ?? $default;
    }
}

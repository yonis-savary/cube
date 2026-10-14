<?php

namespace Cube\Console\Commands;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Utils\Path;
use Symfony\Component\Process\Process;

class Test extends Command
{
    public function getScope(): string
    {
        return "cube";
    }

    public function getHelp(): string
    {
        return "Run vendor/bin/phpunit from your project root, forwarding your arguments";
    }

    public function execute(Args $args): int
    {
        $bin = Path::relative('vendor/bin/phpunit');
        if (!file_exists($bin)) {
            echo "PHPUnit bin not found";
            return 1;
        }

        $process = new Process([$bin, ...$this->toPhpUnitArguments($args)], Path::getProjectPath(), timeout: null);
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        return $process->getExitCode();
    }

    /**
     * @return string[]
     */
    protected function toPhpUnitArguments(Args $args): array
    {
        $arguments = [];

        foreach ($args->dump() as $parameter => $values)
            array_push($arguments, ...array_filter([(string) $parameter, ...$values], fn (string $argument) => $argument !== ''));

        return $arguments;
    }
}

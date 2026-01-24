<?php

namespace Cube\Console\Commands;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Core\Autoloader;
use Cube\Utils\Path;
use Cube\Utils\Shell;
use Symfony\Component\Process\Process;

class Test extends Command
{
    public function getScope(): string
    {
        return "cube";
    }

    public function execute(Args $args): int
    {
        $bin = Path::relative('vendor/bin/phpunit');
        if (file_exists($bin)) {
            Shell::executeInDirectory($bin, Autoloader::getProjectPath(), function ($type, $buffer) {
                echo $buffer;
            });
            return 0;
        }

        echo "PHPUnit bin not found";
        return 1;
    }
}
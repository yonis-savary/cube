<?php

namespace Cube\Console\Commands\Migration;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Console\Commands\Model\Generate;
use Cube\Data\Database\MigrationManager;
use Cube\Utils\Console;

use function Cube\isProduction;

class Rollback extends Command
{
    public function getScope(): string
    {
        return 'migrate';
    }

    public function getHelp(): string
    {
        return "Revert the last applied migrations, one by default (eg: php do migrate:rollback --steps=3)";
    }

    public function execute(Args $args): int
    {
        $steps = $args->getValue(null, 'steps', '1');

        if (!ctype_digit($steps) || !(int) $steps)
            return $this->abort("--steps expects a positive integer, got [{$steps}]");

        $manager = MigrationManager::getInstance();
        $manager->setLoggingFunction(fn($m) => print($m . "\n"));
        $manager->rollback((int) $steps);

        if (!isProduction() && !$args->has('-s', '--skip-generation')) {
            Console::print("");
            return Generate::call();
        }

        return 0;
    }
}

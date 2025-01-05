<?php

namespace YonisSavary\Cube\Console\Commands;

use YonisSavary\Cube\Console\Args;
use YonisSavary\Cube\Console\Command;
use YonisSavary\Cube\Core\Autoloader;
use YonisSavary\Cube\Data\Bunch;
use YonisSavary\Cube\Utils\Console;

class Help extends Command
{
    public function getHelp(): string
    {
        return "Print the list of available commands";
    }

    public function execute(Args $args): int
    {
        Console::log("Here is the list of the command you can launch", "");
        Console::table(
            Bunch::of(Autoloader::classesThatExtends(Command::class))
            ->map(fn($class) => new $class)
            ->map(fn(Command $command) => [$command->getName(), $command->getHelp()])
            ->get(),
            ["Name", "Description"]
        );

        return 0;
    }
}
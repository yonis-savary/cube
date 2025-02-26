<?php

namespace Cube\Console\Commands;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Core\Autoloader;
use Cube\Data\Bunch;
use Cube\Utils\Console;

class Help extends Command
{
    public function getHelp(): string
    {
        return 'Print the list of available commands';
    }

    public function getScope(): string
    {
        return 'cube';
    }

    public function execute(Args $args): int
    {
        Console::print('Here is the list of the command you can launch', '');
        Console::table(
            Bunch::of(Autoloader::classesThatExtends(Command::class))
                ->map(fn ($class) => new $class())
                ->map(fn (Command $command) => [Console::withBlueColor($command->getFullIdentifier(), true), $command->getHelp()])
                ->sort(fn ($x) => $x[0])
                ->get(),
            [Console::withBlueColor('Name'), 'Description'],
            false
        );

        return 0;
    }
}

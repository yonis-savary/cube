<?php

namespace Cube\Console\Commands;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Core\Autoloader;
use Cube\Data\Bunch;
use Cube\Utils\Console;
use Cube\Utils\Shell;

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
        $identifier = $args->getValue();

        $commands = $identifier
            ? Shell::findCommand($identifier)
            : [];

        if (count($commands)) {
            $command = $commands[0];

            $manual = $command->getManual();
            if (is_string($manual))
                $manual = explode("\n", $manual);

            foreach ($manual as $line) {
                Console::print($line);
            }

            return 0;
        }

        if ($identifier)
            Console::print("Command [$identifier] not found");

        Console::print('Here is the list of the command you can launch', '');
        Console::table(
                Bunch::fromExtends(Command::class)
                ->map(fn ($command) => [Console::withBlueColor($command->getFullIdentifier(), true), $command->getHelp()])
                ->sort(fn ($x) => $x[0])
                ->get(),
            [Console::withBlueColor('Name'), 'Description'],
            false
        );

        return 0;
    }
}

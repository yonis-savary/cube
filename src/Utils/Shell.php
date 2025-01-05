<?php

namespace YonisSavary\Cube\Utils;

use Symfony\Component\Process\Process;
use YonisSavary\Cube\Console\Command;
use YonisSavary\Cube\Core\Autoloader;
use YonisSavary\Cube\Data\Bunch;

class Shell
{
    /**
     * @return array<Command>
     */
    public static function findCommand(string $identifier): array
    {
        $commands = Autoloader::classesThatExtends(Command::class);
        $commands = Bunch::of($commands);
        return $commands
            ->map(fn(string $class) => new $class)
            ->filter(fn(Command $command) => in_array($identifier, [$command->getFullIdentifier(), $command->getName()]) )
            ->get();
    }

    public static function launchInDirectory(string $command, string $directory): Process
    {
        $proc = Process::fromShellCommandline($command, $directory);
        $proc->start();
        return $proc;
    }

    public static function executeInDirectory(string $command, string $directory): Process
    {
        $proc = Process::fromShellCommandline($command, $directory);
        $proc->run();
        return $proc;
    }
}
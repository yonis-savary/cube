<?php

namespace Cube\Utils;

use Cube\Console\Command;
use Cube\Core\Autoloader;
use Cube\Data\Bunch;
use Cube\Http\Request;
use Cube\Http\Response;
use Symfony\Component\Process\Process;

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
            ->map(fn (string $class) => new $class())
            ->filter(fn (Command $command) => in_array($identifier, [$command->getFullIdentifier(), $command->getName()]))
            ->get()
        ;
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

    public static function logRequestAndResponseToStdOut(Request $request, Response $response): void
    {
        $status = $response->getStatusCode();

        $lineToLog = join(' ', [
            date('[D M j G:i:s Y]'),
            ($request->getIp() ?? '?.?.?.?').':'.$_SERVER['REMOTE_PORT'],
            "[{$status}]:",
            $request->getMethod(),
            $request->getPath(),
        ]);

        switch (((int) ($status / 100)) * 100) {
            case 100:
                $lineToLog = Console::withBlueColor($lineToLog);
                break;
            case 200:
                $lineToLog = Console::withGreenColor($lineToLog);
                break;
            case 300:
                $lineToLog = Console::withCyanColor($lineToLog);
                break;
            case 400:
                $lineToLog = Console::withYellowColor($lineToLog);
                break;
            case 500:
                $lineToLog = Console::withRedColor($lineToLog);
                break;
            default:
                break;
        }

        file_put_contents('php://stdout', $lineToLog."\n");
    }
}

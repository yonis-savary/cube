<?php

namespace Cube\Console\Commands\Web;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Utils\Console;
use Cube\Utils\Path;
use Symfony\Component\Process\Process;

class Serve extends Command
{
    protected ?Process $process = null;

    public function __destruct()
    {
        if (!$process = $this->process) {
            return;
        }

        if ($process->isRunning()) {
            Console::log('Exiting process before shutting down.');
            $process->stop();
        }
    }

    public function getScope(): string
    {
        return "web";
    }

    public function getHelp(): string
    {
        return "Launch integrated web server to serve your app";
    }

    public function execute(Args $args): int
    {
        $port = $args->getValues()[0] ?? '8000';
        $url = "localhost:{$port}";
        $publicDirectory = Path::relative('Public');

        Console::log("Starting web server at {$url} in directory {$publicDirectory}...");

        $process = $this->process = new Process(['php', '-S', $url, 'index.php'], $publicDirectory);
        $process->start();

        while ($process->isRunning()) {
            if ($std = $process->getIncrementalOutput()) {
                echo $std;
            }
            if ($stdErr = $process->getIncrementalErrorOutput()) {
                echo $stdErr;
            }

            usleep(1000);
        }

        if ($std = $process->getIncrementalOutput()) {
            echo $std;
        }
        if ($stdErr = $process->getIncrementalErrorOutput()) {
            echo $stdErr;
        }

        $code = $process->getExitCode();

        Console::log("Process ended with exit code {$code}");

        return $code;
    }
}

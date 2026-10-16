<?php

namespace Cube\Console\Commands\Routine;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Core\Autoloader;
use Cube\Utils\Console;
use Cube\Utils\Path;

class Generate extends Command
{
    public function getScope(): string
    {
        return 'routine';
    }

    public function getHelp(): string
    {
        return 'Generate the CRON lines launching routine:launch, with and without Docker';
    }

    public function execute(Args $args): int
    {
        $projectRoot = Path::getProjectPath();
        Console::log(
            '',
            'Put one of these lines in your Crontab :',
            'With the Docker stack of the server files',
            Console::withBlueColor("* * * * * cd \"{$projectRoot}\" && make launch-scheduler", true),
            'Without Docker',
            Console::withBlueColor("* * * * * cd \"{$projectRoot}\" && php do routine:launch", true),
            '',
        );

        return 0;
    }
}

<?php

namespace Cube\Console\Commands\Routine;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Core\Autoloader;
use Cube\Utils\Console;

class Generate extends Command
{
    public function getScope(): string
    {
        return "routine";
    }

    public function getHelp(): string
    {
        return "Generate a CRON syntax to launch routine:launch command";
    }

    public function execute(Args $args): int
    {
        $projectRoot = Autoloader::getProjectPath();
        Console::log(
            "",
            "Here is a command you can put in your Crontab :",
            Console::withBlueColor("* * * * * cd \"$projectRoot\" && php do routine:launch", true),
            "",
        );
        return 0;
    }
}
<?php

namespace Cube\Console\Commands\DataToObject;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Console\Commands\DataToObject\Classes\DataToObjectGenClass;
use Cube\Env\Storage;
use Cube\Utils\Console;

class Generate extends Command
{
    public function getScope(): string
    {
        return "dto";
    }

    public function execute(Args $args): int
    {
        $sampleData = readline("Sample data (JSON) ?");
        $sampleData = json_decode($sampleData, true, flags: JSON_THROW_ON_ERROR);

        $application = Console::chooseApplication();

        do
        {
            $rootName = readline("Root class name ? ");
        } while (!preg_match("/^[A-Z][a-z]+$/", $rootName));


        $generated = new DataToObjectGenClass($rootName, $sampleData);
        $generated->generateInto((new Storage($application))->child("Integration")->child($rootName));

        return 0;
    }
}
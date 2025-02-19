<?php

namespace Cube\Console\Commands\Model;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Database\Database;
use Cube\Env\Storage;
use Cube\Models\ModelGenerator;
use Cube\Utils\Console;

class Generate extends Command
{
    public function getHelp(): string
    {
        return "Generate model files inside your application";
    }

    public function getScope(): string
    {
        return "models";
    }

    public function execute(Args $args): int
    {
        $app = Console::chooseApplication();

        $generator = ModelGenerator::getInstance();

        $files = $generator->processDatabase(
            Database::getInstance(),
            (new Storage($app))->child("Models")
        );

        foreach ($files as $file)
            Console::log("Generated " . $file);

        return 0;
    }
}
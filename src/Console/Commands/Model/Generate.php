<?php

namespace YonisSavary\Cube\Console\Commands\Model;

use YonisSavary\Cube\Console\Args;
use YonisSavary\Cube\Console\Command;
use YonisSavary\Cube\Database\Database;
use YonisSavary\Cube\Env\Storage;
use YonisSavary\Cube\Models\ModelGenerator;
use YonisSavary\Cube\Utils\Console;

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
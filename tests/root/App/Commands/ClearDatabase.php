<?php

namespace App\Commands;

use YonisSavary\Cube\Console\Args;
use YonisSavary\Cube\Console\Command;
use YonisSavary\Cube\Core\Autoloader;
use YonisSavary\Cube\Env\Storage;
use YonisSavary\Cube\Models\DummyModel;
use YonisSavary\Cube\Models\Model;
use YonisSavary\Cube\Utils\Shell;

class ClearDatabase extends Command
{
    public function getScope(): string
    {
        return "app";
    }

    public function execute(Args $args): int
    {
        Storage::getInstance()->unlink("valid.sqlite");

        Shell::executeInDirectory("php do migrate", Autoloader::getProjectPath());

        return 0;
    }
}
<?php

namespace App\Commands;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Core\Autoloader;
use Cube\Env\Storage;
use Cube\Utils\Shell;

class ClearDatabase extends Command
{
    public function getScope(): string
    {
        return 'app';
    }

    public function execute(Args $args): int
    {
        Storage::getInstance()->unlink('valid.sqlite');

        Shell::executeInDirectory('php do migrate', Autoloader::getProjectPath());

        return 0;
    }
}

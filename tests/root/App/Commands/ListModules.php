<?php

namespace App\Commands;

use YonisSavary\Cube\Console\Args;
use YonisSavary\Cube\Console\Command;
use YonisSavary\Cube\Data\Bunch;
use YonisSavary\Cube\Database\Database;
use App\Models\Module;
use YonisSavary\Cube\Utils\Console;

class ListModules extends Command
{
    public function getScope(): string
    {
        return "app";
    }

    public function execute(Args $args): int
    {
        $list = Module::select()
            ->toBunch()
            ->map(fn(Module $x) => $x->label)
            ->join("\n");

        Console::log($list);
        return 0;
    }
}
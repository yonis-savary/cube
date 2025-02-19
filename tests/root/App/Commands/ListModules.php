<?php

namespace App\Commands;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Data\Bunch;
use Cube\Database\Database;
use App\Models\Module;
use Cube\Utils\Console;

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
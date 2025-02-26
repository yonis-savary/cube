<?php

namespace App\Commands;

use App\Models\Module;
use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Utils\Console;

class ListModules extends Command
{
    public function getScope(): string
    {
        return 'app';
    }

    public function execute(Args $args): int
    {
        $list = Module::select()
            ->toBunch()
            ->map(fn (Module $x) => $x->label)
            ->join("\n")
        ;

        Console::log($list);

        return 0;
    }
}

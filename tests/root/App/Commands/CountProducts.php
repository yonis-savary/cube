<?php

namespace App\Commands;

use App\Models\Product;
use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Utils\Console;

class CountProducts extends Command
{
    public function getScope(): string
    {
        return "app";
    }

    public function execute(Args $args): int
    {
        Console::log(
            count(
                Product::select()->fetch()
            )
        );
        return 0;
    }
}
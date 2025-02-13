<?php

namespace App\Commands;

use App\Models\Product;
use YonisSavary\Cube\Console\Args;
use YonisSavary\Cube\Console\Command;
use YonisSavary\Cube\Utils\Console;

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
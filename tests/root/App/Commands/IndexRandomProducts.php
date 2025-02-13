<?php

namespace App\Commands;

use App\Queues\ProductIndexer;
use YonisSavary\Cube\Console\Args;
use YonisSavary\Cube\Console\Command;
use YonisSavary\Cube\Utils\Console;

class IndexRandomProducts extends Command
{
    public function getScope(): string
    {
        return "app";
    }

    public function execute(Args $args): int
    {
        Console::withProgressBar(range(0, 999), function(){
            ProductIndexer::addProduct(uniqid("product-", true));
        });

        return 0;
    }
}
<?php

namespace App\Commands;

use App\Queues\ProductIndexer;
use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Utils\Console;

class IndexRandomProducts extends Command
{
    public function getScope(): string
    {
        return 'app';
    }

    public function execute(Args $args): int
    {
        Console::withProgressBar(range(0, 999), function () {
            ProductIndexer::addProduct(uniqid('product-', true));
        });

        return 0;
    }
}

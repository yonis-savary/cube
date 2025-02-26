<?php

namespace App\Queues;

use App\Models\Product;
use Cube\Routine\Queue;
use Cube\Utils\Console;

class ProductIndexer extends Queue
{
    public static function batchSize(): int
    {
        return 1000;
    }

    public static function addProduct(string $name)
    {
        return self::pushToQueue(['name' => $name]);
    }

    protected static function process($object): bool
    {
        Console::log('Inserting product :'.$object['name']);
        Product::insertArray($object);

        return true;
    }
}

<?php

namespace App\Queues;

use App\Models\Product;
use YonisSavary\Cube\Routine\AbstractQueue;
use YonisSavary\Cube\Utils\Console;

class ProductIndexer extends AbstractQueue
{
    public static function batchSize(): int
    {
        return 1000;
    }

    protected static function process($object): bool
    {
        Console::log("Inserting product :" . $object["name"]);
        Product::insertArray($object);

        return true;
    }

    public static function addProduct(string $name)
    {
        return self::pushToQueue(["name" => $name]);
    }
}
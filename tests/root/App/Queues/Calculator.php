<?php

namespace App\Queues;

use App\Queues\Calculator\Addition;
use Cube\Logger\Logger;
use Cube\Routine\Queue;

class Calculator extends Queue
{
    public static function batchSize(): int
    {
        return 1000;
    }

    public static function addAddition(int $a, int $b): void
    {
        self::pushToQueue(new Addition($a, $b));
    }

    /**
     * @param Addition $object
     */
    protected static function process($object): bool
    {
        Logger::getInstance()->info($object->a.' + '.$object->b.' = '.$object->getResult());

        return true;
    }
}

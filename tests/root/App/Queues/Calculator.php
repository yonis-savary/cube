<?php

namespace App\Queues;

use YonisSavary\Cube\Logger\Logger;
use YonisSavary\Cube\Routine\AbstractQueue;
use App\Queues\Calculator\Addition;

class Calculator extends AbstractQueue
{
    public static function batchSize(): int
    {
        return 1000;
    }

    /**
     * @param Addition $object
     */
    protected static function process($object): bool
    {
        Logger::getInstance()->info($object->a . " + " . $object->b .  " = " . $object->getResult());
        return true;
    }

    public static function addAddition(int $a, int $b): void
    {
        self::pushToQueue(new Addition($a, $b));
    }
}
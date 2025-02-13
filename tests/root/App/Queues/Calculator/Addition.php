<?php

namespace App\Queues\Calculator;

class Addition
{
    public function __construct(
        public readonly int $a,
        public readonly int $b,
    ){}

    public function getResult(): int
    {
        return $this->a + $this->b;
    }
}
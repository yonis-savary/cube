<?php

namespace Cube\Tests\Units\Events\Classes;

use Cube\Event\Event;

class ProductWasShipped extends Event
{
    public function __construct(
        public readonly string $reference
    ) {}
}

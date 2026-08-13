<?php

namespace Cube\Tests\Units\Core\Classes;

use Cube\Core\Component;

class Counter
{
    use Component;

    public function __construct(
        public string $tag = 'default'
    ) {}
}

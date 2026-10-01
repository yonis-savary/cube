<?php

namespace Cube\Tests\Units\Core\Classes;

use Cube\Tests\Units\Core\Contracts\CanSing;

class Canary implements CanSing
{
    public function __invoke(): string
    {
        return 'tweet';
    }
}

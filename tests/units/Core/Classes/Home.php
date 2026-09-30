<?php

namespace Cube\Tests\Units\Core\Classes;

use Cube\Tests\Units\Core\Contracts\Pet;

class Home
{
    public function __construct(
        public Pet $pet
    ) {}
}

<?php

namespace Cube\Tests\Units\Core\Classes;

use Cube\Tests\Units\Core\Contracts\Pet;

class Shelter
{
    /** @var Pet[] */
    public array $pets;

    public function __construct(
        Pet ...$pets
    ) {
        $this->pets = $pets;
    }
}

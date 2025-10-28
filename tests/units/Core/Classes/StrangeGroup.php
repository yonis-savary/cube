<?php

namespace Cube\Tests\Units\Core\Classes;

class StrangeGroup
{
    public function __construct(
        protected Bird $bird,
        protected Dragon $dragon,
        protected Zombie $zombie
    )
    {}
}
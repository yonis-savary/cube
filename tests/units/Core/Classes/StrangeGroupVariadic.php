<?php

namespace Cube\Tests\Units\Core\Classes;

class StrangeGroupVariadic
{
    public function __construct(
        Common ...$creatures
    )
    {}
}
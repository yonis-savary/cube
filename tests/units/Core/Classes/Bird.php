<?php

namespace Cube\Tests\Units\Core\Classes;

use Cube\Tests\Units\Core\Contracts\CanFitInAHouse;
use Cube\Tests\Units\Core\Contracts\CanFly;

class Bird extends Common implements CanFly, CanFitInAHouse {
}
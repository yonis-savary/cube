<?php

namespace Cube\Tests\Units\Core\Classes;

use Cube\Tests\Units\Core\Contracts\CanFly;
use Cube\Tests\Units\Core\Contracts\CanTalk;
use Cube\Tests\Units\Core\Contracts\CanWalk;

class Dragon extends Common implements CanFly, CanTalk, CanWalk {
}
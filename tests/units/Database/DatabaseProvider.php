<?php

namespace Cube\Tests\Units\Database;

use Cube\Database\Database;

interface DatabaseProvider
{
    public static function getDatabase(): Database;
}
<?php

namespace Cube\Tests\Units\Database;

use Cube\Database\Database;

class SQLiteProvider implements DatabaseProvider
{
    public static function getDatabase(): Database
    {
        return new Database('sqlite');
    }
}
<?php

namespace Cube\Data\Database;

/**
 * The drivers Cube supports out of the box, backed by their PDO name. Another DBMS needs no case here :
 * its `QueryBuilder`, `Plan` and `DatabaseAdapter` answer `supports()` for its PDO driver name.
 */
enum DatabaseDriver: string
{
    case SQLite = 'sqlite';
    case MySQL = 'mysql';
    case Postgres = 'pgsql';
}

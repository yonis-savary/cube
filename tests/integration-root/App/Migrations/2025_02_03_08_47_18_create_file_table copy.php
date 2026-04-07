<?php

/**
 * Test RawSQLMigration execution
 */

use Cube\Data\Database\Migration\RawSQLMigration;

return new RawSQLMigration(
    "CREATE TABLE file (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(200) NOT NULL,
        path VARCHAR(255) NOT NULL
    );
");
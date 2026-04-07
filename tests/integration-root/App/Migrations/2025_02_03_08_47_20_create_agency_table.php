<?php

/**
 * Test RawSQLMigration execution
 */

use Cube\Data\Database\Migration\RawSQLMigration;

return new RawSQLMigration(
    "CREATE TABLE agency (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(200) NOT NULL UNIQUE
    );

    CREATE TABLE agency_user (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        agency_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        FOREIGN KEY (agency_id) REFERENCES agency(id),
        FOREIGN KEY (user_id) REFERENCES user(id)
    );
");
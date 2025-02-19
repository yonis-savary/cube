<?php

use Cube\Database\Migration\Migration;

return new Migration(
    "CREATE TABLE module (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        label VARCHAR(50) NOT NULL UNIQUE
    );
    INSERT INTO module (label) VALUES ('product'), ('order'), ('crm'), ('admin');

    CREATE TABLE module_user (
        user INTEGER NOT NULL REFERENCES user(id),
        module INTEGER NOT NULL,
        FOREIGN KEY (module) REFERENCES module(id)
    );
    INSERT INTO module_user (user, module) VALUES (1, 4);
",
    "DROP TABLE module_user;
    DROP TABLE module;
");
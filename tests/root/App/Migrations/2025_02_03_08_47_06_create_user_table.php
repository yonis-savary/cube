<?php

use Cube\Database\Migration\Migration;

return new Migration(
    "CREATE TABLE user_type (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        label VARCHAR(50) NOT NULL UNIQUE
    );

    INSERT INTO user_type (label) VALUES ('admin'), ('user'), ('guest');

    CREATE TABLE user (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        login VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(100) NOT NULL,
        type INT NOT NULL REFERENCES user_type(id)
    );
    INSERT INTO user (login, password, type) VALUES ('root', '$2y$12\$CPWrjBtTfHIBDMcRA3yexu2.LnBP5dmqHcUWxAiJAljNI1TnD5Tri', 1);
",
    'DROP TABLE IF EXISTS user;
    DROP TABLE IF EXISTS user_type;
'
);

<?php

use Cube\Database\Migration\Migration;

return new Migration(
    "CREATE TABLE product (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        name VARCHAR(200) NOT NULL UNIQUE,
        price_dollar DECIMAL(10,5) NULL
    );

    CREATE TABLE product_manager (
        product INTEGER NOT NULL REFERENCES product(id),
        manager VARCHAR(200) NOT NULL,
        UNIQUE (product, manager)
    );
",
    "DROP TABLE product_manager;
    DROP TABLE product;
");
<?php

use Cube\Data\Database\Migration\Migration;

return new Migration(
    "CREATE TABLE should_not_be_created (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        label VARCHAR(5) NOT NULL
    );

    INSERT INTO should_not_be_created (inexistant_column) VALUES ('cannot be inserted');
",
    '
'
);

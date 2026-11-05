<?php

namespace Cube\Data\Database\Migration;

use Cube\Data\Database\Database;

class RawSQLMigration extends Migration
{
    public function __construct(
        public readonly string $upScript = '',
        public readonly string $downScript = '',
    ) {}

    public function up(Plan $plan, Database $database)
    {
        $database->exec($this->upScript);
    }

    public function down(Plan $plan, Database $database)
    {
        $database->exec($this->downScript);
    }
}

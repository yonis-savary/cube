<?php

namespace Cube\Data\Database\Migration;

use Cube\Data\Database\Database;

abstract class Migration
{
    abstract public function up(Plan $plan, Database $database);
    abstract public function down(Plan $plan, Database $database);
}

<?php

namespace Cube\Data\Database\Migration;

use Cube\Data\Database\Database;

abstract class Migration
{
    public function up(Plan $plan, Database $database) {}
    public function down(Plan $plan, Database $database) {}
}

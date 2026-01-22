<?php

use Cube\Data\Database\Database;
use Cube\Data\Database\Migration\Migration;
use Cube\Data\Database\Migration\Plan;
use Cube\Data\Models\ModelField;

return new class extends Migration
{
    public function up(Plan $plan, Database $database)
    {
        $plan->create('module', [
            ModelField::id(),
            ModelField::string('label', 50)->notNull()->unique()
        ]);

        $database->exec("INSERT INTO module (label) VALUES ('product'), ('order'), ('crm'), ('admin');");

        $plan->create('module_user', [
            ModelField::integer('user')->notNull()->references('user', 'id'),
            ModelField::integer('module')->notNull()->references('module', 'id'),
        ]);

        $database->exec("INSERT INTO module_user (user, module) VALUES (1, 4);");
    }

    public function down(Plan $plan, Database $database)
    {
        $plan->dropTable('module_user');
        $plan->dropTable('module');
    }
};
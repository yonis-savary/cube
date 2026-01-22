<?php

use Cube\Data\Database\Database;
use Cube\Data\Database\Migration\Migration;
use Cube\Data\Database\Migration\Plan;
use Cube\Data\Models\ModelField;

return new class extends Migration {
    public function up(Plan $plan, Database $database)
    {
        $plan->create('user_type', [
            ModelField::id(),
            ModelField::string('label', 50)->notNull()->unique()
        ]);

        $database->exec("INSERT INTO user_type (label) VALUES ('admin'), ('user'), ('guest')");

        $plan->create("user", [
            ModelField::id(),
            ModelField::string('login', 50)->notNull()->unique(),
            ModelField::string('password', 100)->notNull(),
            ModelField::integer('type')->references('user_type', 'id'),
        ]);

        $database->exec("INSERT INTO user (login, password, type) VALUES ('root', '$2y$12\$CPWrjBtTfHIBDMcRA3yexu2.LnBP5dmqHcUWxAiJAljNI1TnD5Tri', 1);");
    }

    public function down(Plan $plan, Database $database)
    {
        $plan->dropTable('user');
        $plan->dropTable('user_type');
    }
};
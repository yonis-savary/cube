<?php

use Cube\Data\Database\Database;
use Cube\Data\Database\Migration\Migration;
use Cube\Data\Database\Migration\Plan;
use Cube\Data\Models\ModelField;

return new class extends Migration
{
    public function up(Plan $plan, Database $database)
    {
        $plan->create('product', [
            ModelField::id(),
            ModelField::timestamp('created_at'), // TODO : CURRENT_TIMESTAMP
            ModelField::string('name', 200)->notNull()->unique(),
            ModelField::decimal('price_dollar', 10, 5)->nullable()
        ]);

        $plan->create('product_manager', [
            ModelField::integer('product')->notNull()->references('product', 'id'),
            ModelField::string('manager', 200)->notNull()
        ], 'UNIQUE (product, manager)');
    }

    public function down(Plan $plan, Database $database)
    {
        $plan->dropTable('product_manager');
        $plan->dropTable('product');
    }
};


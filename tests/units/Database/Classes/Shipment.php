<?php

namespace Cube\Tests\Units\Database\Classes;

use Cube\Data\Models\Model;
use Cube\Data\Models\ModelField;
use Cube\Data\Models\Relations\HasOne;
use Cube\Tests\Units\Models\Product;

/**
 * A HasOne without foreign key constraint, so a row can point to a product that does not exist
 *
 * @property int $id
 * @property string $reference
 * @property ?int $product
 * @property ?Product $_product
 */
class Shipment extends Model
{
    public static function table(): string
    {
        return 'shipment';
    }

    public static function fields(): array
    {
        return [
            'id' => ModelField::id(),
            'reference' => ModelField::string('reference', 20)->notNull(),
            'product' => ModelField::integer('product'),
        ];
    }

    public function _product(): HasOne
    {
        return $this->hasOne('_product', 'product', Product::class, 'id');
    }

    public static function relations(): array
    {
        return ['_product'];
    }
}

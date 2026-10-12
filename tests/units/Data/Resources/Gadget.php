<?php

namespace Cube\Tests\Units\Data\Resources;

use Cube\Data\Models\Model;
use Cube\Data\Models\ModelField;

class Gadget extends Model
{
    public static function table(): string
    {
        return 'gadget';
    }

    public static function fields(): array
    {
        return [
            'name' => (new ModelField('name'))->type('STRING'),
            'code' => ModelField::string('code', 4),
        ];
    }

    public static function relations(): array
    {
        return [];
    }
}

<?php

namespace Cube\Tests\Units\Data\OpenAPI\Models;

use Cube\Data\Models\Model;
use Cube\Data\Models\ModelField;

/**
 * @property int $id
 * @property string $name
 * @property string $last_update
 * @property ?string $released_at
 */
class Album extends Model
{
    public static function table(): string
    {
        return 'album';
    }

    public static function primaryKey(): string
    {
        return 'id';
    }

    /**
     * @return array<string,ModelField>
     */
    public static function fields(): array
    {
        return [
            'id' => (new ModelField('id'))->type('INTEGER')->autoIncrement()->notNull()->hasDefault(true),
            'name' => (new ModelField('name'))->type('STRING')->notNull()->hasDefault(false),
            'last_update' => (new ModelField('last_update'))->type('DATETIME')->notNull()->hasDefault(true),
            'released_at' => (new ModelField('released_at'))->type('DATE')->nullable(true)->hasDefault(true),
        ];
    }

    public static function relations(): array
    {
        return [];
    }
}

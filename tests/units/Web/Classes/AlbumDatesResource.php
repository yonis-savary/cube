<?php

namespace Cube\Tests\Units\Web\Classes;

use Cube\Data\Models\Model;
use Cube\Data\Models\ModelResource;
use Cube\Tests\Units\Data\OpenAPI\Models\Album;

/**
 * @extends ModelResource<Album>
 */
class AlbumDatesResource extends ModelResource
{
    public static function getModelClass(): string
    {
        return Album::class;
    }

    public function toArray(Model $model): array
    {
        return [
            'name' => $model->name,
            'updated' => $model->last_update,
            'released' => $model->released_at,
        ];
    }
}

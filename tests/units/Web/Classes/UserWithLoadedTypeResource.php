<?php

namespace Cube\Tests\Units\Web\Classes;

use Cube\Data\Models\Model;
use Cube\Data\Models\ModelResource;
use Cube\Tests\Units\Models\User;

/**
 * @extends ModelResource<User>
 */
class UserWithLoadedTypeResource extends ModelResource
{
    public static function getModelClass(): string
    {
        return User::class;
    }

    public function toArray(Model $model): array
    {
        return [
            'login' => $model->login,
            'type' => $model->_type?->label,
        ];
    }
}

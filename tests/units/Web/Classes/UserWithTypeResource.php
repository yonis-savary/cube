<?php

namespace Cube\Tests\Units\Web\Classes;

use Cube\Data\Models\Model;
use Cube\Data\Models\ModelResource;
use Cube\Tests\Units\Models\User;

/**
 * @extends ModelResource<User>
 */
class UserWithTypeResource extends ModelResource
{
    public static function getModelClass(): string
    {
        return User::class;
    }

    public function toArray(Model $model): array
    {
        return [
            'login' => $model->login,
            'type' => $model->load('_type')->_type?->label,
        ];
    }
}

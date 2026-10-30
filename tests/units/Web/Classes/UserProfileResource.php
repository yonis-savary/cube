<?php

namespace Cube\Tests\Units\Web\Classes;

use Cube\Data\Models\Model;
use Cube\Data\Models\ModelResource;
use Cube\Data\OpenAPI\Attributes\Nullable;
use Cube\Data\OpenAPI\Attributes\Optional;
use Cube\Tests\Units\Models\User;

/**
 * @extends ModelResource<User>
 */
#[Nullable(['nickname', 'address.city', 'tags.*'])]
#[Optional(['address', 'address.zip'])]
class UserProfileResource extends ModelResource
{
    public static function getModelClass(): string
    {
        return User::class;
    }

    public function toArray(Model $model): array
    {
        return [
            'id' => $model->id,
            'nickname' => $model->login,
            'address' => ['city' => $model->login, 'zip' => '75001'],
            'tags' => [$model->login],
        ];
    }
}

<?php

namespace Cube\Tests\Units\Web\Classes;

use Cube\Data\Models\Model;
use Cube\Data\Models\ModelResource;
use Cube\Data\OpenAPI\Attributes\Nullable;
use Cube\Tests\Units\Models\User;

/**
 * @extends ModelResource<User>
 */
#[Nullable(['nick'])]
class UserWithAMistypedKeyResource extends ModelResource
{
    public static function getModelClass(): string
    {
        return User::class;
    }

    public function toArray(Model $model): array
    {
        return [
            'nickname' => $model->login,
        ];
    }
}

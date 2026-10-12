<?php

namespace Cube\Tests\Units\Web\Classes;

use Cube\Data\Models\ModelResource;
use Cube\Tests\Units\Models\User;
use Cube\Web\ModelAPI\ModelAPI;

/**
 * @extends ModelAPI<User>
 */
class UserAPI extends ModelAPI
{
    public function getModelClass(): string
    {
        return User::class;
    }

    protected function getModelResource(): ?ModelResource
    {
        return new UserResource();
    }
}

<?php

namespace Cube\Tests\Units\Web\Classes;

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

    protected function getHiddenFields(): array
    {
        return ['password'];
    }
}

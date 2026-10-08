<?php

namespace App\Http\Apis;

use App\Models\User;
use Cube\Web\ModelAPI\ModelAPI;

/**
 * Written by hand: `models:generate --apis` must leave it untouched.
 *
 * @extends ModelAPI<User>
 */
class UserAPI extends ModelAPI
{
    public function getModelClass(): string
    {
        return User::class;
    }

    public function isWrittenByHand(): bool
    {
        return true;
    }
}

<?php

namespace App\Policies;

use App\Models\User;
use Cube\Data\Models\Model;
use Cube\Tests\Units\Models\User as ModelsUser;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Policy;

/**
 * @extends Policy<ModelsUser>
 */
class UserPolicy extends Policy
{
    protected static function getModelClass(): string
    {
        return ModelsUser::class;
    }

    public static function verify(Model|User $user, Request $request): Request|Response
    {
        // Cannot inspect root accounts
        if ($user->type === 1) {
            return Response::unauthorized();
        }
        return $request;
    }
}
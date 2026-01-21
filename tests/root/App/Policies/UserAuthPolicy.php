<?php

namespace App\Policies;

use Cube\Data\Models\Model;
use Cube\Web\AuthPolicy;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use App\Models\User;

class UserAuthPolicy extends AuthPolicy
{
    public static function authorize(Model|User $model, Model $user, Request $request): Request|Response
    {
        return $model->id() !== $user->id()
            ? $request
            : Response::unauthorized()
        ;
    }
}
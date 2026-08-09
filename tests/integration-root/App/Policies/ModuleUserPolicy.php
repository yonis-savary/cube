<?php

namespace App\Policies;

use Cube\Data\Models\Model;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Policy;
use App\Models\ModuleUser;

class ModuleUserPolicy extends Policy
{
    public static function verify(Model|ModuleUser $userModule, Request $request): Request|Response
    {
        return UserPolicy::verify(
            $userModule->load('_user')->_user,
            $request
        );
    }
}
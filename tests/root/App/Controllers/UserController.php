<?php

namespace App\Controllers;

use App\Models\User;
use App\Policies\UserPolicy;
use Cube\Web\Controller;
use Cube\Web\Http\Request;
use Cube\Web\ModelAPI\ModelAPI;
use Cube\Web\Router\Route;
use Cube\Web\Router\RouteGroup;
use Cube\Web\Router\Router;

class UserController extends Controller
{
    public function routes(Router $router): void
    {
        $router->addRoutes(
            Route::get("/user/{id}", [self::class, 'showUser'], [UserPolicy::class])
        );
    }

    public static function showUser(Request $request, User $user)
    {
        return $user;
    }
}

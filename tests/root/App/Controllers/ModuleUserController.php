<?php

namespace App\Controllers;

use App\Policies\ModuleUserPolicy;
use Cube\Web\Controller;
use Cube\Web\Http\Request;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;
use App\Models\ModuleUser;

class ModuleUserController extends Controller
{
    public function routes(Router $router): void
    {
        $router->addRoutes(
            Route::get("/module-user/{id}", [self::class, 'showModuleUser'], [ModuleUserPolicy::class])
        );
    }

    public static function showModuleUser(Request $request, ModuleUser $moduleUser)
    {
        return $moduleUser;
    }
}

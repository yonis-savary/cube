<?php

use App\Models\Product;
use YonisSavary\Cube\Web\ModelAPI;
use YonisSavary\Cube\Web\Router;

$router = Router::getInstance();

$router->group("/auto-api", callback: function(Router $router) {
    $router->addService(new ModelAPI(Product::class));
});
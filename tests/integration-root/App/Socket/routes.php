<?php

use App\Controllers\PingController;
use Cube\Web\Router\Route;

$router->addRoutes(
    Route::get('/ping', [PingController::class, 'ping'])
);

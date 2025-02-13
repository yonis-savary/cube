<?php

use YonisSavary\Cube\Core\Autoloader\Applications;
use YonisSavary\Cube\Database\DatabaseConfiguration;
use YonisSavary\Cube\Web\AssetServer;
use YonisSavary\Cube\Web\Router\RouterConfiguration;
use YonisSavary\Cube\Web\StaticServer;

use function Cube\env;

return [
    new Applications('App'),
    new DatabaseConfiguration(
        "sqlite",
        env("DB_FILENAME", "invalid.sqlite")
    ),

    new RouterConfiguration(
        services: [
            AssetServer::class,
            new StaticServer("App/Static")
        ]
    )
];
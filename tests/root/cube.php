<?php

use Cube\Core\Autoloader\Applications;
use Cube\Database\DatabaseConfiguration;
use Cube\Web\AssetServer;
use Cube\Web\Router\RouterConfiguration;
use Cube\Web\StaticServer;

use function Cube\env;

return [
    new Applications('App'),
    new DatabaseConfiguration(
        'sqlite',
        env('DB_FILENAME', 'invalid.sqlite')
    ),

    new RouterConfiguration(
        apis: [
            AssetServer::class,
            new StaticServer('App/Static'),
        ]
    ),
];

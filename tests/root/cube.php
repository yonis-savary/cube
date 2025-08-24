<?php

use Cube\Core\Autoloader\Applications;
use Cube\Data\Database\DatabaseConfiguration;
use Cube\Web\Helpers\AssetServer;
use Cube\Web\Router\RouterConfiguration;
use Cube\Web\Helpers\StaticServer;

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

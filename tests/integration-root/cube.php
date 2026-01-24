<?php

use Cube\Core\Autoloader\Applications;
use Cube\Data\Database\DatabaseConfiguration;
use Cube\Web\Helpers\AssetServer;
use Cube\Web\Router\RouterConfiguration;
use Cube\Web\Websocket\WebsocketConfiguration;
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

    new WebsocketConfiguration(
        '127.0.0.1:9991',
        '127.0.0.1:9992',
        'supersecret',
        'X-Api-Key',
        false
    ),
];

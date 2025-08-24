<?php

use Cube\Core\Autoloader\Applications;
use Cube\Core\Autoloader\AutoloaderConfiguration;
use Cube\Data\Database\DatabaseConfiguration;
use Cube\Web\Helpers\StaticServer;
use Cube\Web\Router\RouterConfiguration;

use function Cube\env;

return [
    // The list of your applications to load
    new Applications('App'),

    // Configure your sql connection here
    // new DatabaseConfiguration('sqlite', env('DB_FILE', 'database.sqlite')),

    // To improve performances, you can enable autoloading caching
    // new AutoloaderConfiguration(false),

    // You can serve your frontend application with a static file server
    // new RouterConfiguration(
    //     apis: [
    //         new StaticServer('FrontEnd/Public')
    //     ]
    // )
];

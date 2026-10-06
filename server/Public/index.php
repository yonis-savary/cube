<?php

use Cube\Core\Autoloader;
use Cube\Core\RequestContext;
use Cube\Env\Logger\Logger;
use Cube\Event\Events\PostRouting;
use Cube\Event\Events\PreRouting;
use Cube\Web\Http\Request;
use Cube\Utils\Shell;
use Cube\Web\Router\Router;

include_once "../vendor/autoload.php";

Autoloader::initialize();

RequestContext::oneShot(function() {

    (new PreRouting)->dispatch();

    $logger = Logger::getInstance();
    $router = Router::getInstance();
    $request = Request::fromGlobals()->logSelf($logger);
    $response = $router->route($request);

    (new PostRouting($router, $request, $response))
        ->dispatch();

    $response->logSelf($logger)->display();

    Shell::logRequestAndResponseToStdOut($request, $response);
});
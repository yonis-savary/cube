<?php

use Cube\Core\Autoloader;
use Cube\Http\Request;
use Cube\Utils\Shell;
use Cube\Web\Router\Router;

$loader = include_once "../vendor/autoload.php";

Autoloader::initialize(loader: $loader);

$request = Request::fromGlobals();
$request->logSelf();

$response = Router::getInstance()->route($request);

$response->logSelf();
$response->display();

Shell::logRequestAndResponseToStdOut($request, $response);

exit(0);
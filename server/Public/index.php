<?php

use Cube\Core\Autoloader;
use Cube\Http\Request;
use Cube\Utils\Shell;
use Cube\Web\Router\Router;

include_once "../vendor/autoload.php";

Autoloader::initialize();

$request = Request::fromGlobals();
$request->logSelf();

$response = Router::getInstance()->route($request);

$response->logSelf();
$response->display();

Shell::logRequestAndResponseToStdOut($request, $response);

exit(0);
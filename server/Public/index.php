<?php

use YonisSavary\Cube\Core\Autoloader;
use YonisSavary\Cube\Http\Request;
use YonisSavary\Cube\Utils\Shell;
use YonisSavary\Cube\Web\Router;

$loader = include_once "../vendor/autoload.php";

Autoloader::initialize(loader: $loader);

$request = Request::fromGlobals();
$request->logSelf();

$response = Router::getInstance()->route($request);

$response->logSelf();
$response->display();

Shell::logRequestAndResponseToStdOut($request, $response);

exit(0);
<?php

use Cube\Core\Autoloader;
use Cube\Web\Http\Request;
use Cube\Env\Logger\Logger;
use Cube\Web\Helpers\CubeServer;

set_time_limit(0);

$loader = include_once './vendor/autoload.php';

Autoloader::initialize(loader: $loader);

$server = new CubeServer(8000, null, Logger::getInstance());

(new Request('GET', $server->path('/ping')))->fetch(Logger::getInstance());

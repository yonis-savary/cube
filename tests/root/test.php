<?php

use YonisSavary\Cube\Core\Autoloader;
use YonisSavary\Cube\Http\Request;
use YonisSavary\Cube\Logger\Logger;
use YonisSavary\Cube\Web\CubeServer;

set_time_limit(0);

$loader = include_once "./vendor/autoload.php";

Autoloader::initialize(loader: $loader);

$server = new CubeServer(8000, null, Logger::getInstance());

(new Request("GET", $server->path("/ping")))->fetch(Logger::getInstance());
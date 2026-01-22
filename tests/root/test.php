<?php

use Cube\Core\Autoloader;
use Cube\Web\Http\Request;
use Cube\Env\Logger\Logger;
use Cube\Web\Helpers\CubeServer;

set_time_limit(0);

$loader = include_once './vendor/autoload.php';

Autoloader::initialize();


$r = (new Request("POST", "http://localhost:8000/auto-api/product", [], [
    'name' => 'some', 'price_dollar' => 10
]))->fetch();

print_r($r);
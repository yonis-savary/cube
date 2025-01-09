<?php

use YonisSavary\Cube\Core\Autoloader;

$loader = include_once (__DIR__ ."/../vendor/autoload.php");

Autoloader::initialize(__DIR__, $loader);
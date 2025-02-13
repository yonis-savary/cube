<?php

use YonisSavary\Cube\Configuration\Configuration;
use YonisSavary\Cube\Core\Autoloader;
use YonisSavary\Cube\Database\Database;
use YonisSavary\Cube\Utils\Path;

$loader = include_once (__DIR__ ."/../vendor/autoload.php");

Configuration::setInstance(new Configuration());

Autoloader::initialize(__DIR__, $loader);
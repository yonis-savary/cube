<?php

use Cube\Configuration\Configuration;
use Cube\Core\Autoloader;

$loader = include_once (__DIR__ ."/../vendor/autoload.php");

Configuration::setInstance(new Configuration());

Autoloader::initialize(__DIR__, $loader);
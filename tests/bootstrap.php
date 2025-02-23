<?php

use Cube\Configuration\Configuration;
use Cube\Core\Autoloader;
use Cube\Env\Environment;

$loader = include (__DIR__ ."/../vendor/autoload.php");
Configuration::setInstance(new Configuration());

Autoloader::initialize(__DIR__, $loader);
Environment::setInstance(new Environment(__DIR__ . "/../.env"));

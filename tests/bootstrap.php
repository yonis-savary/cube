<?php

use Cube\Env\Configuration;
use Cube\Core\Autoloader;
use Cube\Env\Environment;

chdir(__DIR__);
chdir("..");

include './vendor/autoload.php';

Configuration::setInstance(new Configuration());

Autoloader::initialize(realpath('.'));

$env = new Environment(null);
$env->set('QUEUE_REDIS_HOST', 'localhost');
Environment::setInstance($env);

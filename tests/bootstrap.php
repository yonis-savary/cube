<?php

use Cube\Env\Configuration;
use Cube\Core\Autoloader;
use Cube\Core\Autoloader\Applications;
use Cube\Env\Environment;
use Cube\Env\Storage;
use Cube\Test\TestContext;
use Cube\Utils\Path;

chdir(__DIR__);
chdir("..");

include './vendor/autoload.php';

Autoloader::initialize(realpath('.'));

$env = new Environment(null);
$env->set('QUEUE_REDIS_HOST', 'localhost');

Environment::setInstance($env);

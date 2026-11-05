<?php

use Cube\Core\Autoloader;
use Cube\Env\Environment;

chdir(__DIR__);
chdir('..');

include './vendor/autoload.php';

Autoloader::initialize(realpath('.'));
Autoloader::addToExploreMap('tests/units');
Autoloader::addToExploreMap('tests/integration-root/App');

$env = new Environment(null);
$env->set('QUEUE_REDIS_HOST', 'localhost');
$env->set('APP_KEY', 'cube-test-suite-app-key-of-32-bytes-or-more');

Environment::setInstance($env);

// Open session before any output, to avoid "headers already sent" errors
session_start();

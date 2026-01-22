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

Configuration::setInstance(new Configuration(
    new Applications(Path::join(__DIR__, 'TestApplication'))
));

Autoloader::initialize(realpath('.'));

$env = new Environment(null);
$env->set('QUEUE_REDIS_HOST', 'localhost');

TestContext::setInstance(
    new TestContext(
        Storage::getInstance()->child('Tests-'. date("Ymd-his") )
    )
);

Environment::setInstance($env);

<?php

use Cube\Configuration\Configuration;
use Cube\Core\Autoloader;
use Cube\Env\Environment;

chdir(__DIR__);
chdir("..");

include './vendor/autoload.php';

Configuration::setInstance(new Configuration());

Autoloader::initialize(realpath('.'));

Environment::setInstance(new Environment('./.env'));

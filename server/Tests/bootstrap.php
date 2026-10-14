<?php

use Cube\Core\Autoloader;
use Cube\Data\Database\Database;
use Cube\Test\TestContext;

require_once __DIR__. "/../vendor/autoload.php";

Autoloader::initialize(realpath(__DIR__ . '/..'));

TestContext::useTemporaryStorage();

$testDatabase = TestContext::getInstance()->createEmptyApplicationDatabase();
Database::setInstance($testDatabase);
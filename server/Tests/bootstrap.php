<?php

use Cube\Core\Autoloader;
use Cube\Data\Database\Database;
use Cube\Env\Storage;
use Cube\Test\TestContext;

require_once __DIR__. "/../vendor/autoload.php";

Autoloader::initialize(realpath(__DIR__ . '/..'));

$testStorage = Storage::getInstance()->child(uniqid('Tests-'));
Storage::setInstance($testStorage);

$testDatabase = TestContext::getInstance()->createEmptyApplicationDatabase();
Database::setInstance($testDatabase);
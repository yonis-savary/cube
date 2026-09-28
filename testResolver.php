<?php

use Cube\Core\Autoloader;
use Cube\Data\Database\Database;
use Cube\Data\Database\DatabaseConfiguration;
use Cube\Env\Configuration;
use Cube\Tests\Units\Models\User;

use function Cube\env;

require_once "./vendor/autoload.php";

Autoloader::initialize(__DIR__);

Database::setInstance(
    new Database('sqlite', 'testDB.db')
);

$db = Database::getInstance();

$query = User::select(['modules._module']);

echo $query->build();

$data = $query->fetch();

echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
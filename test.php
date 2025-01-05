<?php

use YonisSavary\Cube\Core\Autoloader;
use YonisSavary\Cube\Database\Database;
use YonisSavary\Cube\Database\Query;
use YonisSavary\Cube\Database\Query\FieldComparaison;
use YonisSavary\Cube\Env\Storage;
use YonisSavary\Cube\Env\Storage\StorageConfiguration;

require_once "./vendor/autoload.php";

Autoloader::resolveProjectPath(__DIR__);

$db = new Database();

$db->query("CREATE TABLE product(id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(100) UNIQUE)");

Query::insert("product")
->insertField(["name"])
->values(["meuble"], ["planche"], ["chaise"], ["ordinateur"], ["t-shirt"])
->fetch($db);

Query::select("product")
->selectField("id", "product", "subtable&anotherone&product.id")
->selectField("name", "product")
->fetch($db);

//print_r(StorageConfiguration::resolve());
<?php

namespace YonisSavary\Cube\Web\Router;

use YonisSavary\Cube\Web\Router;

abstract class Service
{
    public function __construct(){}

    abstract public function routes(Router $router): void;
}
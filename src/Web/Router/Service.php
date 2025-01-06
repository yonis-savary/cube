<?php

namespace YonisSavary\Cube\Web\Router;

use YonisSavary\Cube\Web\Route;

abstract class Service
{
    public function __construct(){}

    /** @var array<Route> */
    abstract public function routes(): array;
}
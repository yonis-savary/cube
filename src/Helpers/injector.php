<?php

use Cube\Core\Injector;

if (!function_exists('inject')) {
    /**
     * @template T
     * @param class-string<T> $class
     * @return T
     */
    function inject(string $class, mixed $contructorArgs=[]): mixed {
        return Injector::instanciate($class, $contructorArgs);
    }
}
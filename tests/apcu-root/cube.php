<?php

use Cube\Core\Autoloader\Applications;
use Cube\Core\Autoloader\AutoloaderConfiguration;

return [
    new Applications('App'),
    new AutoloaderConfiguration(cached: true),
];

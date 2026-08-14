<?php

use Cube\Console\Args;

require_once './vendor/autoload.php';

print_r(
    Args::fromArgv(['--', '-x'])->toString()
);
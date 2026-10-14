<?php

use Cube\Web\Servers\UnixSocketServer;
use React\EventLoop\Loop;

include __DIR__ . '/../../../../bootstrap.php';

$socketPath = $argv[1];

Loop::futureTick(function () use ($socketPath) {
    echo file_exists($socketPath) ? "socket created\n" : "socket missing\n";
    Loop::stop();
});

(new UnixSocketServer($socketPath))->serve();

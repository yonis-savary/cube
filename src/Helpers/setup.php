<?php

use Cube\Core\Injector;
use Cube\Env\Logger\Logger;
use Psr\Log\LoggerInterface;

/**
 * When asked for a PSR LoggerInterface, we provide Cube's logger
 */
Injector::getInstance()->provide(
    LoggerInterface::class,
    Logger::class
);
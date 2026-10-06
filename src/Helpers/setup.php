<?php

use Cube\Core\Autoloader;
use Cube\Core\Injector;
use Cube\Env\Logger\Logger;
use Cube\Event\EventDispatcher;
use Cube\Event\Events;
use Cube\Event\Events\PostDeployment;
use Psr\Log\LoggerInterface;

Injector::getInstance()->asGlobalInstance(function($i) {
    $i->provide(LoggerInterface::class, Logger::class);
    $i->provide(EventDispatcher::class, Events::class);
});

PostDeployment::on(fn() => Autoloader::cleanCache() );
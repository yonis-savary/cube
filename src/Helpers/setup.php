<?php

use Cube\Core\Autoloader;
use Cube\Core\Injector;
use Cube\Env\Configuration;
use Cube\Env\Logger\Logger;
use Cube\Event\EventDispatcher;
use Cube\Event\Events;
use Cube\Event\Events\PostDeployment;
use Cube\Web\Router\Router;
use Psr\Log\LoggerInterface;

Injector::getInstance()->asGlobalInstance(function($i) {
    $i->provide(LoggerInterface::class, Logger::class);
    $i->provide(EventDispatcher::class, Events::class);
});

PostDeployment::on(fn() => Autoloader::cleanCache() );
PostDeployment::on(fn() => Router::cleanCache() );
PostDeployment::on(fn() => (new Configuration())->identify(Configuration::DEFAULT_IDENTIFIER)->removeFromCache() );
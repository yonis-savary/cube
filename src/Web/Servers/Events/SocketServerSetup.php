<?php

namespace Cube\Web\Servers\Events;

use Cube\Event\Event;
use Cube\Web\Router\Router;

/**
 * Event used to configure the Socker Server Routes
 * (Reading Apps routes directly would be dangerous)
 */
class SocketServerSetup extends Event
{
    public function __construct(
        public readonly Router $router
    ) {}
}
